<?php

class AdminController extends BaseController
{
    public function indexAction()
    {
        $this->view->text = BaseMemcache::i()->get('admintext');
        BaseMemcache::i()->delete('admintext');
    }

    public function testAction()
    {
        $this->norender();
    }

    public function debugAction()
    {
        $debug = (int)$this->p('debug', 0);
        $res = BaseService::i()->setCookie('debug', $debug, 3600);
        BaseMemcache::i()->set('admintext', $debug ? 'Отладка включена' : 'Отладка выключена', 60);
        $this->redirect('/admin/');
    }

    public function clearmemcacheAction()
    {
        $res = BaseMemcache::i()->mc->flush();
        BaseMemcache::i()->set('admintext', $res ? 'Memcache очищен' : 'Ошибка', 60);
        $this->redirect('/admin/');
    }

    public function recoverAction()
    {
        $this->norender();

        // Тут будет восстановление после сбоя
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 60);

        $count = 0;

        // TODO: возможна ещё ситуация, когда заказ удалили, но деньги мы не разморозили, надо просто fixUserHold сделать

        $count += $this->fixCreatedOrders();

        $from = strtotime('-60 minutes'); $to = time(); // Задаем временной интервал в котором были проблемы
        $count += $this->fixPayments($from, $to);

        $count += $this->fixExecutedOrders();

        BaseMemcache::i()->set('admintext', $count == 0 ? 'Всё ОК' : 'Исправили '. $count .' ошибок', 60);
        $this->redirect('/admin/');
    }

    // Ошибки когда заказ исполнился, а транзакция денег не добавилась
    protected function fixExecutedOrders()
    {
        $db = BaseDao::i()->db;

        $count = 0;
        $select = $db->select()->from(OrderDao::TABLE_ORDERS)->where('state = ?', OrderDao::STATE_EXECUTED)->where('user_payment_id = 0 OR executer_payment_id = 0')->limit(100);
        $data = $db->fetchAll($select);
        foreach ($data as $order) {
            $orderId = $order['id'];

            if ($order['user_payment_id'] == 0) {
                if (LockDao::i()->lock(LockDao::USER, $order['user_id'])) {
                    $payment = UserDao::i()->getUserOrderPayment($order['user_id'], $orderId);
                    $userPaymentId = empty($payment) ? UserDao::i()->updateMoney($order['user_id'], -$order['price'], -$order['price'], $orderId) : $payment['id'];

                    if ($userPaymentId) {
                        OrderDao::i()->_updateRaw($orderId, [
                            'user_payment_id' => $userPaymentId,
                        ]);
                        $count++;
                    }
                    LockDao::i()->unlock(LockDao::USER, $order['user_id']);
                }
            }
            if ($order['executer_payment_id'] == 0) {
                if (LockDao::i()->lock(LockDao::USER, $order['executer_id'])) {
                    $price = $order['price'] - $order['commission'];

                    $payment = UserDao::i()->getUserOrderPayment($order['executer_id'], $orderId);
                    $executerPaymentId = empty($payment) ? UserDao::i()->updateMoney($order['executer_id'], $price, 0, $orderId) : $payment['id'];

                    if ($executerPaymentId) {
                        OrderDao::i()->_updateRaw($orderId, [
                            'executer_payment_id' => $executerPaymentId,
                        ]);
                        $count++;
                    }
                    LockDao::i()->unlock(LockDao::USER, $order['executer_id']);
                }
            }
        }

        return $count;
    }

    // Ошибки когда заказ создался, а деньги в холд не ушли
    protected function fixCreatedOrders()
    {
        $db = BaseDao::i()->db;

        $fixedUsers = $fixedOrders = [];
        $select = $db->select()->from(OrderDao::TABLE_ORDERS)->where('state = ?', OrderDao::STATE_NEW)->where('user_payment_id = ?', 0)->limit(100); // Если user_payment_id = 0, значит до конца заказ не создался и деньги не заморожены
        $data = $db->fetchAll($select);
        foreach ($data as $order) {
            $userId = $order['user_id'];
            $orderId = $order['id'];

            if (empty($fixedUsers[$userId])) {
                if ($this->fixUserHold($userId)) {
                    $fixedUsers[$userId] = 1;
                }
            }

            if (!empty($fixedUsers[$userId])) { // hold юзера пофиксили, обновим заказ
                $fixedOrders[$orderId] = OrderDao::i()->_updateRaw($orderId, [
                    'user_payment_id' => 1,
                ]);
            }
        }

        return count($fixedOrders);
    }

    // Ошибки когда транзакция есть, а денег то нет
    protected function fixPayments($from, $to)
    {
        $db = BaseDao::i()->db;

        $fixedCash = [];
        for ($i = 0; $i < UserDao::TABLE_PAYMENTS_SHARDS; $i++) {
            $table = UserDao::TABLE_PAYMENTS . "_{$i}";

            $select = $db->select()->from($table)->where('? <= inserted', $from)->where('inserted <= ?', $to)->limit(100); // Выбрали все транзакции за этот период
            $payments = $db->fetchAll($select);
            $userIds = array_column($payments, 'user_id');
            $userIds = array_unique($userIds);

            if (empty($userIds)) continue;

            $lastPayments = [];
            foreach ($payments as $payment) { // Построим соответствие user_id => id последней транзакции
                if (empty($lastPayments[$payment['user_id']])) $lastPayments[$payment['user_id']] = $payment['id'];
                $lastPayments[$payment['user_id']] = max($lastPayments[$payment['user_id']], $payment['id']);
            }

            $select = $db->select()->from(UserDao::TABLE_USER)->where('id IN (?)', $userIds); // Выберем всех юзеров из транзакций
            $users = $db->fetchAll($select);

            foreach ($users as $user) {
                if ($user['payment_id'] < $lastPayments[$user['id']]) { // Если в юзере id транзакции не совпадает с последней из таблицы тарнзакци, значит что-то пошло не так и надо фиксить баланс
                    $fixedCash[$user['id']] = $this->fixUserCash($user['id']);
                }
            }
        }

        return count($fixedCash);
    }

    protected function fixUserCash($userId)
    {
        $res = false;
        if (LockDao::i()->lock(LockDao::USER, $userId)) {
            $update = UserDao::i()->getUserUpdateByPayments($userId);
            $res = UserDao::i()->_update($userId, [
                'cash' => !empty($update['cash']) ? $update['cash'] : 0,
                'payment_id' => !empty($update['payment_id']) ? $update['payment_id'] : 0,
            ]);
            LockDao::i()->unlock(LockDao::USER, $userId);
        }
        return $res;
    }

    protected function fixUserHold($userId)
    {
        $res = false;
        $db = BaseDao::i()->db;

        if (LockDao::i()->lock(LockDao::USER, $userId)) {
            $select = $db->select()->from(OrderDao::TABLE_ORDERS, 'SUM(price) as price')->where('user_id = ?', $userId)->where('state = ?', OrderDao::STATE_NEW);
            $price = $db->fetchOne($select);

            $res = UserDao::i()->_update($userId, [
                'hold' => $price,
                'updated' => time(),
            ]);

            LockDao::i()->unlock(LockDao::USER, $userId);
        }
        return $res;
    }

    public function stuffAction()
    {
        return;

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 60);

        $data = file_get_contents('data.txt');
        $data = unserialize($data);

        foreach ($data as $record) {
            $price = rand(1, 1000000);
            //OrderDao::i()->addOrder(1, $record['title'], $record['desc'], $price, (int)ceil($price * 0.18));
        }

        /*
         *
DELETE FROM orders WHERE id > 1649;
DELETE FROM users WHERE id > 10;
UPDATE orders SET state = 1, executer_id = 0, user_payment_id = 0, executer_payment_id = 0, updated = inserted;
TRUNCATE TABLE payments_0;
TRUNCATE TABLE payments_1;
TRUNCATE TABLE payments_2;
TRUNCATE TABLE payments_3;
UPDATE users SET cash = 20000000, hold = 10000000, payment_id = 0, updated = inserted;
         */

        /*
         *
         * UPDATE orders SET inserted = (1456790400 - 35*24*60*60) + id * 3000 + greatest(0,round((rand()) * 1000));
UPDATE orders SET updated =  inserted;

        UPDATE users SET inserted = (1456790400 - 40*24*60*60) + id * 3000 + greatest(0,round((rand()) * 1000));
UPDATE users SET updated =  inserted;
         *
         */

        /**
         * UPDATE orders SET price = IF(
        ROUND((RAND() * (3-0))+0) = 0,
        (ROUND((RAND() * (15-0))+0)) * 1000,
        IF(ROUND((RAND() * (2-0))+0) = 0, (ROUND((RAND() * (30-0))+0)) * 50, (ROUND((RAND() * (30-0))+0)) * 100)
        ) * 100, commission = CEIL(price * 0.18 / 100) * 100;
         * UPDATE orders SET price = price + commission;
         */

        //UPDATE orders SET user_id = 1, price = RAND() * 100000, commission = RAND() * 1000, state = 1, executer_id = 0, user_payment_id = 0, executer_payment_id = 0, updated = inserted;
        //TRUNCATE TABLE payments_0;TRUNCATE TABLE payments_1;TRUNCATE TABLE payments_2;TRUNCATE TABLE payments_3;

        /*$select = $db->select()->from(OrderDao::TABLE_ORDERS);
        $data = $db->fetchAll($select);
        foreach ($data as $order) {
            $price = rand() % 5 == 0 ? rand(1, 50000*100) : rand(1, 50000) * 100;
            $commission = (int)ceil($price * 0.18);

            $db->update(OrderDao::TABLE_ORDERS, [
                'price' => $price,
                'commission' => $commission,
            ], $db->qq('id = ?', $order['id']));
        }*/
    }
}
