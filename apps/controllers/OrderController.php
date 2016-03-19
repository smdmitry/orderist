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
            return $this->ajaxError([
                'error' => 'У вас недостаточно средств на счету, попробуйте снизить стоимость заказа!',
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

        if (UserDao::i()->lock($user['id'])) {
            $user = UserDao::i()->getById($user['id']);
            $cash = $user['cash'] - $user['hold'];
            if ($price > $cash) {
                UserDao::i()->unlock($user['id']);
                return $this->ajaxError([
                    'error' => 'Ошибка, недостаточно денег на счету',
                ]);
            }

            $orderId = OrderDao::i()->addOrder($user['id'], $title, $description, $price);
            if ($orderId) {
                UserDao::i()->addMoney($user['id'], 0, $price, $orderId);
            }

            UserDao::i()->unlock($user['id']);
        }

        $user = UserDao::i()->getById($user['id']);
        $this->updateBaseData([
            'cash' => UserDao::i()->getField($user, 'cash'),
            'hold' => UserDao::i()->getField($user, 'hold'),
        ]);

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
        if (UserDao::i()->lock($order['user_id'])) {
            if (UserDao::i()->lock($this->USER['id'])) {
                $res = OrderDao::i()->execute($orderId, $this->USER['id']);

                if ($res) {
                    $price = $order['price'] - $order['commission'];
                    UserDao::i()->addMoney($order['user_id'], -$order['price'], -$order['price'], $orderId);
                    UserDao::i()->addMoney($this->USER['id'], $price, 0, $orderId);
                }

                UserDao::i()->unlock($this->USER['id']);
            }
            UserDao::i()->unlock($order['user_id']);
        }

        $user = UserDao::i()->getById($this->USER['id']);
        $this->updateBaseData([
            'cash' => UserDao::i()->getField($user, 'cash'),
        ]);

        return $res ? $this->ajaxSuccess() : $this->ajaxError([
            'error' => 'Произошла ошибка, попробуйте ещё раз.',
        ]);
    }
}
