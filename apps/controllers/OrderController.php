<?php

class OrderController extends BaseController
{
    public function createpopupAction()
    {
        if (!$this->USER) {
            return $this->ajaxError(['error' => 'auth']);
        }

        $this->view->commission = 0.18;

        $data = [
            'html' => $this->renderView('order/create_popup'),
        ];

        $this->ajaxSuccess($data);
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
                    $price = $order['price'] - $order['comission'];
                    UserDao::i()->addMoney($order['user_id'], -$order['price'], -$order['price'], $orderId);
                    UserDao::i()->addMoney($this->USER['id'], $price, 0, $orderId);
                }

                UserDao::i()->unlock($this->USER['id']);
            }
            UserDao::i()->unlock($order['user_id']);
        }

        return $res ? $this->ajaxSuccess() : $this->ajaxError([
            'error' => 'Произошла ошибка, попробуйте ещё раз.',
        ]);
    }
}
