<?php

class AdminController extends BaseController
{
    public function indexAction()
    {
        $this->view->text = BaseMemcache::i()->get('admintext');

        $db = BaseDao::i()->db;
        $select = $db->select()
            ->from(OrderDao::TABLE_ORDERS, ['SUM(commission) as commission', 'SUM(price) as price'])
            ->where('state = ?', OrderDao::STATE_EXECUTED)
            ->where('user_payment_id > 0')
            ->where('executer_payment_id > 0');
        $data = $db->fetchRow($select);

        $this->view->income = ['price' => 0, 'commission' => 0];
        if (!empty($data)) {
            $this->view->income = $data;
        }

        BaseMemcache::i()->delete('admintext');
    }

    public function testAction()
    {
        $this->norender();
    }

    public function debugAction()
    {
        $debug = (int)$this->p('debug', 0);
        $res = BaseService::i()->setCookie('debug', $debug, BaseService::TIME_YEAR);
        BaseMemcache::i()->set(
            'admintext',
            $debug ? _g('Debug enabled') : _g('Debug disabled'),
            60
        );
        $this->redirect('/admin/');
    }

    public function clearmemcacheAction()
    {
        $res = BaseMemcache::i()->mc->flush();
        BaseMemcache::i()->set(
            'admintext',
            $res ? _g('Memcache cleared') : _g('Error'),
            60
        );
        $this->redirect('/admin/');
    }

    public function recoverAction()
    {
        $this->norender();

        // Restoring data consistency after failure
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 60);

        $count = 0;

        // TODO: It's possible that order is deleted, but money is no unholded, we have to do just fixUserHold

        $count += $this->fixCreatedOrders();

        $from = strtotime('-60 minutes'); $to = time(); // Time interval where we had problems
        $count += $this->fixPayments($from, $to);

        $count += $this->fixExecutedOrders();

        BaseMemcache::i()->set(
            'admintext',
            $count == 0 ? _g('Everything is OK') : _f('Fixed %s errors', $count),
            60
        );
        $this->redirect('/admin/');
    }

    // Errors when order is completed, but money transaction is not done
    protected function fixExecutedOrders($limit = 1000)
    {
        $db = BaseDao::i()->db;

        $count = 0;
        $select = $db->select()->from(OrderDao::TABLE_ORDERS)
            ->where('state = ?', OrderDao::STATE_EXECUTED)
            ->where('user_payment_id = 0 OR executer_payment_id = 0')
            ->limit($limit);
        $data = $db->fetchAll($select);
        foreach ($data as $order) {
            $orderId = $order['id'];

            if ($order['user_payment_id'] == 0) {
                if (LockDao::i()->lock(LockDao::USER, $order['user_id'])) {
                    $payment = UserDao::i()->getUserOrderPayment($order['user_id'], $orderId);
                    $userPaymentId = empty($payment) ?
                        UserDao::i()->updateMoney($order['user_id'], -$order['price'], -$order['price'], $orderId) :
                        $payment['id'];

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
                    $executerPaymentId = empty($payment) ?
                        UserDao::i()->updateMoney($order['executer_id'], $price, 0, $orderId) :
                        $payment['id'];

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

    // Errors when order is created, but money in not holded
    protected function fixCreatedOrders($limit = 1000)
    {
        $db = BaseDao::i()->db;

        $fixedUsers = $fixedOrders = [];
        $select = $db->select()->from(OrderDao::TABLE_ORDERS)
            ->where('state = ?', OrderDao::STATE_NEW)
            ->where('user_payment_id = ?', 0)
            ->limit($limit); // If user_payment_id = 0, then order creation did not finish and money in not holded
        $data = $db->fetchAll($select);
        foreach ($data as $order) {
            $userId = $order['user_id'];
            $orderId = $order['id'];

            if (empty($fixedUsers[$userId])) {
                if ($this->fixUserHold($userId)) {
                    $fixedUsers[$userId] = 1;
                }
            }

            if (!empty($fixedUsers[$userId])) { // user hold fixed, updated order
                $fixedOrders[$orderId] = OrderDao::i()->_updateRaw($orderId, [
                    'user_payment_id' => 1,
                ]);
            }
        }

        return count($fixedOrders);
    }

    // Errors when there is a transaction, but no money
    protected function fixPayments($from, $to, $limit = 1000)
    {
        $db = BaseDao::i()->db;

        $fixedCash = [];
        for ($i = 0; $i < UserDao::TABLE_PAYMENTS_SHARDS; $i++) {
            $table = UserDao::TABLE_PAYMENTS . "_{$i}";

            $select = $db->select()->from($table)->where('? <= inserted', $from)
                ->where('inserted <= ?', $to)
                ->limit($limit); // Select all transactions from this period
            $payments = $db->fetchAll($select);
            $userIds = array_column($payments, 'user_id');
            $userIds = array_unique($userIds);

            if (empty($userIds)) continue;

            $lastPayments = [];
            foreach ($payments as $payment) { // Map user_id => id from latest transaction
                if (empty($lastPayments[$payment['user_id']])) $lastPayments[$payment['user_id']] = $payment['id'];
                $lastPayments[$payment['user_id']] = max($lastPayments[$payment['user_id']], $payment['id']);
            }

            $select = $db->select()->from(UserDao::TABLE_USER)
                ->where('id IN (?)', $userIds); // Select all users from transaction
            $users = $db->fetchAll($select);

            foreach ($users as $user) {
                // If user transaction is not consistent with latest transaction from table,
                // then something gone wrong and we need to fix balance
                if ($user['payment_id'] < $lastPayments[$user['id']]) {
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
            $select = $db->select()->from(OrderDao::TABLE_ORDERS, 'SUM(price) as price')
                ->where('user_id = ?', $userId)
                ->where('state = ?', OrderDao::STATE_NEW);
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
UPDATE orders SET state = 1, executer_id = 0, user_payment_id = 0, executer_payment_id = 0, executed = 0, updated = inserted;
TRUNCATE TABLE payments_0;
TRUNCATE TABLE payments_1;
TRUNCATE TABLE payments_2;
TRUNCATE TABLE payments_3;
UPDATE users SET cash = 20000000, hold = 10000000, payment_id = 0, updated = inserted;
// http://orderist.smdmitry.com/admin/recover/
UPDATE users SET cash = hold;
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
         *
        UPDATE orders SET price = IF(
        ROUND((RAND() * (3-0))+0) = 0,
        (ROUND((RAND() * (15-0))+0)) * 1000,
        IF(ROUND((RAND() * (2-0))+0) = 0, (ROUND((RAND() * (30-0))+0)) * 50, (ROUND((RAND() * (30-0))+0)) * 100)
        ) * 100, commission = CEIL(price * 0.18 / 100) * 100;
         * UPDATE orders SET price = price + commission;
         * UPDATE orders SET price = 10000, commission = 100 WHERE price = 0;
         * SELECT * FROM orders WHERE price = 0;
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
