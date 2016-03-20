<?php

class OrderController extends BaseController
{
    public function createpopupAction()
    {
        if (!$this->USER) {
            return $this->ajaxError(['error' => 'auth']);
        }

        $this->view->commission = OrderDao::COMMISSION;

        $data = [
            'html' => $this->renderView('order/create_popup'),
        ];

        $this->ajaxSuccess($data);
    }

    public function createAction()
    {
        $user = $this->USER;
        if (!$user) {
            return $this->ajaxError(['error' => 'auth']);
        }

        $title = $this->p('order_title');
        $description = $this->p('order_description');
        $price = $this->p('order_price');

        $filter = new \Phalcon\Filter();

        $title = $filter->sanitize($title, 'string');
        $description = $filter->sanitize($description, 'string');
        $price = $filter->sanitize($price, 'float');
        $price = (int)floor($price * 100);

        $cash = $user['cash'] - $user['hold'];
        if ($price > $cash) {
            $need = ($price - $cash) / 100;
            return $this->ajaxError([
                'error' => "Вам на хватает {$need} руб., для создания заказа, попробуйте снизить стоимость!",
            ]);
        }

        $errors = [];
        if (mb_strlen($title) < 3) {
            $errors[] = 'Введите заголовок заказа!';
        }
        if ($price == 0) {
            $errors[] = 'Укажите стоимость заказа!';
        } elseif ($price <= 1) {
            $errors[] = 'Cлишком низкая стоимость заказа!';
        }
        if (!empty($errors)) {
            return $this->ajaxError([
                'errors' => $errors,
            ]);
        }

        if (LockDao::i()->lock(LockDao::USER, $user['id'])) {
            $user = UserDao::i()->getById($user['id']);
            $cash = $user['cash'] - $user['hold'];
            if ($price > $cash) {
                LockDao::i()->unlock(LockDao::USER, $user['id']);
                $need = ($price - $cash) / 100;
                return $this->ajaxError([
                    'error' => "Вам на хватает {$need} руб., для создания заказа, попробуйте снизить стоимость!",
                ]);
            }

            $commission = ceil($price * OrderDao::COMMISSION);
            $orderId = OrderDao::i()->addOrder($user['id'], $title, $description, $price, $commission);
            if ($orderId) {
                UserDao::i()->updateMoney($user['id'], 0, $price, $orderId);
            }

            LockDao::i()->unlock(LockDao::USER, $user['id']);
        }

        $user = UserDao::i()->getById($user['id']);
        $this->updateUserData($user);

        return !empty($orderId) && $orderId ? $this->ajaxSuccess() : $this->ajaxError([
            'error' => 'Произошла ошибка, попробуйте ещё раз.',
        ]);
    }

    public function executeAction()
    {
        if (!$this->USER) {
            return $this->ajaxError(['error' => 'auth']);
        }

        $orderId = (int)$this->p('order_id');
        $order = OrderDao::i()->getById($orderId);

        if (empty($order)) {
            return $this->ajaxError([
                'error' => 'Ошибка, такого заказа не существует!',
            ]);
        }

        if ($order['user_id'] == $this->USER['id']) {
            return $this->ajaxError([
                'error' => 'Мы обнаружили, что вы пытаетесь выполнить свой же заказ.
                Не стоит этого делать, лучше зайдите на страницу <a href="/user/orders/">Мои заказы</a> и удалите его оттуда.',
            ]);
        }

        if ($order['state'] != OrderDao::STATE_NEW) {
            return $this->ajaxError([
                'order' => 'disabled',
                'error' => 'Извините, но этот заказ уже выполнен кем-то другим!',
            ]);
        }

        $res = false;
        if (LockDao::i()->lock(LockDao::USER, $order['user_id'])) {
            if (LockDao::i()->lock(LockDao::USER, $this->USER['id'])) {
                $res = OrderDao::i()->execute($orderId, $this->USER['id']);

                if ($res) {
                    $price = $order['price'] - $order['commission'];
                    UserDao::i()->updateMoney($order['user_id'], -$order['price'], -$order['price'], $orderId);
                    UserDao::i()->updateMoney($this->USER['id'], $price, 0, $orderId);
                }

                LockDao::i()->unlock(LockDao::USER, $this->USER['id']);
            }
            LockDao::i()->unlock(LockDao::USER, $order['user_id']);
        }

        $user = UserDao::i()->getById($this->USER['id']);
        $this->updateUserData($user);

        return $res ? $this->ajaxSuccess() : $this->ajaxError([
            'error' => 'Произошла ошибка, попробуйте ещё раз.',
        ]);
    }

    public function deleteAction()
    {
        if (!$this->USER) {
            return $this->ajaxError(['error' => 'auth']);
        }

        $orderId = (int)$this->p('order_id');
        $order = OrderDao::i()->getById($orderId);

        if (empty($order)) {
            return $this->ajaxError([
                'error' => 'Ошибка, такого заказа не существует!',
            ]);
        }

        if ($order['user_id'] != $this->USER['id']) {
            return $this->ajaxError([
                'error' => 'Мы обнаружили, что вы пытаетесь удалить чужой заказ. Не стоит этого делать!',
            ]);
        }

        if ($order['state'] != OrderDao::STATE_NEW) {
            return $this->ajaxError([
                'order' => 'disabled',
                'error' => 'Извините, но нельзя удалить уже выполненный заказ!',
            ]);
        }

        $res = false;
        if (LockDao::i()->lock(LockDao::USER, $this->USER['id'])) {
            $res = OrderDao::i()->delete($orderId, $this->USER['id']);

            if ($res) {
                UserDao::i()->updateMoney($this->USER['id'], 0, -$order['price'], $orderId);
            }

            LockDao::i()->unlock(LockDao::USER, $this->USER['id']);
        }

        $user = UserDao::i()->getById($this->USER['id']);
        $this->updateUserData($user);

        return $res ? $this->ajaxSuccess() : $this->ajaxError([
            'error' => 'Произошла ошибка, попробуйте ещё раз.',
        ]);
    }
}
