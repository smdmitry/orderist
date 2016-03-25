<?php

class OrderController extends BaseController
{
    // TODO: тут кеша нет, но это редкий Action, поэтому можно и без него
    public function orderAction()
    {
        $orderId = (int)$this->p('id');
        $order = OrderDao::i()->getById($orderId);
        $userId = $this->USER ? $this->USER['id'] : 0;

        if (!$order) {
            $this->view->error = 'Ошибка, заказ не найден!';
            return;
        }

        $canView = $userId && ($order['user_id'] == $userId || $order['executer_id'] == $userId);
        if ($order['state'] != OrderDao::STATE_NEW && !$canView) {
            $this->view->error = 'Ошибка, у вас нет доступа к этому заказу!';
            return;
        }

        $this->view->order = reset(OrderDao::i()->prepareOrders([$order]));
        $this->view->isMe = $this->USER && $order['user_id'] == $this->USER['id'];
    }

    public function createpopupAction()
    {
        if (!$this->USER) {
            return $this->ajaxError([
                'type' => 'auth',
                'error' => 'Для создания заказов вам необходимо войти в свой профиль или зарегистрироваться на сайте.',
            ]);
        }

        $this->view->commission = OrderDao::COMMISSION;

        if (BaseService::i()->isRoundingEnabled()) {
            $this->view->cthreshold = OrderDao::CTHRESHOLD;
        } else {
            $this->view->cthreshold = 0;
        }

        $data = [
            'html' => $this->renderView('order/popups/create'),
        ];

        $this->ajaxSuccess($data);
    }

    public function createAction()
    {
        if (!$this->isAjax()) {
            return $this->redirect('/?create=1');
        }

        $user = $this->USER;
        if (!$user) {
            return $this->ajaxError(['error' => 'auth']);
        }

        if (!$this->checkCSRF()) {
            return $this->ajaxError(['type' => 'csrf']);
        }

        $title = BaseService::i()->filterText($this->p('order_title'));
        $description = BaseService::i()->filterText($this->p('order_description'), true);

        $orderPayment = $this->p('order_payment');
        $orderPrice = $this->p('order_price');
        $executerPrice = $this->p('order_executer_price');
        $userPrice = $this->p('order_user_price');

        if ($orderPayment == 'executer') {
            $orderPrice = (int)round($orderPrice * 100, 0);
            $price = (int)ceil($orderPrice / (1 - OrderDao::COMMISSION));
        } else {
            $price = (int)round($orderPrice * 100, 0);
        }

        $commission = (int)ceil($price * OrderDao::COMMISSION);
        $executer = $price - $commission;

        if ($price && BaseService::i()->isRoundingEnabled()) {
            $cmul = $commission / $price; // Комиссия, рассчитанная точно

            // Мы просто поверим тому, что пришло от фронта, если наша комиссия вписывается в пределы
            $price = (int)round($userPrice * 100, 0);
            $executer = (int)round($executerPrice * 100, 0);
            $commission = $price - $executer;

            $mul = $commission / $price;
            if (abs($cmul - $mul) > OrderDao::CTHRESHOLD || $mul > $cmul) { // Сравним с точной комиссией
                $thresholdError = true;
            }
        }

        $errors = [];
        if (mb_strlen($title) < 1) {
            $errors[] = 'Введите заголовок заказа!';
        } else if (mb_strlen($title) > 160) {
            $errors[] = 'Сликом длинный заголовок, будьте лаконичней!';
        }
        if ($price == 0) {
            $errors[] = 'Укажите стоимость заказа!';
        } elseif ($price <= 1 || $commission <= 0) {
            $errors[] = 'Cлишком низкая стоимость заказа!';
        }
        if (empty($errors) && $price - $commission <= 0) {
            $errors[] = 'Ну нельзя же так, чтобы исполнитель ничего не получил!';
        }
        if ($price != (int)round($userPrice * 100, 0) || $executer != (int)round($executerPrice * 100, 0) || !empty($thresholdError)) {
            $errors[] = 'Ой, что-то мы не так рассчитали вам оплату заказа. Это наш косяк, просто попробуйте указать другую сумму.';
            // Сюда мы не должны попадать при нормальной работе и надо бы залогировать ошибку
        }
        if (!empty($errors)) {
            return $this->ajaxError([
                'errors' => $errors,
            ]);
        }

        //$this->debugSleep();

        $userId = $this->USER['id'];
        if (LockDao::i()->lock(LockDao::USER, $userId)) { // Залочим баланс юзера
            $this->USER = UserDao::i()->getById($userId, 1); // Мало ли к этому моменту юзер уже изменился

            // Спокойно проверяем хватит ли денег, зная что никто не обновит баланс пока есть лок
            $cash = $this->USER['cash'] - $this->USER['hold'];
            if ($price > $cash) {
                LockDao::i()->unlock(LockDao::USER, $userId);
                $need = ($price - $cash) / 100;
                return $this->ajaxError([
                    'error' => "Ой, вам на хватает <b>{$need} руб.</b> для создания заказа!<br>Попробуйте снизить стоимость или <b><a href=\"/user/cash/\" onclick=\"orderist.order.createPopup.addCash('". (int)($need*100) ."'); return false;\">пополнить счет</a></b>.",
                ]);
            }

            $orderId = OrderDao::i()->addOrder($userId, $title, $description, $price, $commission);
            if ($orderId) {
                $updated = UserDao::i()->updateMoney($userId, 0, $price, $orderId); // А тут внутри юзер обновится, вместе с мемкешом
                if ($updated) {
                    OrderDao::i()->_updateRaw($orderId, [
                        'user_payment_id' => 1,
                    ]);
                } else {
                    // Сюда мы не должны попадать при нормальной работе и надо бы залогировать ошибку и потом смотреть что произошло и обновить баланс юзера
                }
            } else {
                // Сюда мы не должны попадать при нормальной работе и надо бы залогировать ошибку
            }

            LockDao::i()->unlock(LockDao::USER, $userId);

            $this->USER = UserDao::i()->getById($userId); // Не забываем получить себе обновленного юзера
        }

        $res = !empty($orderId) && $orderId;
        if ($res) {
            $this->updateUserData();
            BaseWS::i()->send(0, ['type' => 'order', 'action' => 'created', 'id' => $orderId]);
        }

        return $res ? $this->ajaxSuccess() : $this->ajaxError([
            'error' => 'Произошла ошибка, попробуйте ещё раз.',
        ]);
    }

    public function executeAction()
    {
        if (!$this->isAjax()) {
            return $this->redirect('/');
        }

        if (!$this->USER) {
            return $this->ajaxError([
                'type' => 'auth',
                'error' => 'Для выполнения заказов вам необходимо войти в свой профиль или зарегистрироваться на сайте.',
            ]);
        }

        if (!$this->checkCSRF()) {
            return $this->ajaxError(['type' => 'csrf']);
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
                'error' => 'Мы обнаружили, что вы пытаетесь выполнить свой же заказ.<br/>
                Не стоит этого делать! Но если вы хотели удалить свой заказ, то это можно сделать на странице <a href="/user/orders/">Мои заказы</a>, либо
                можно <a href="/user/orders/" onclick="orderist.order.deleteConfirm(\''. $orderId .'\'); event.stopPropagation(); return false;">удалить прямо сейчас</a>.',
            ]);
        }

        if ($order['state'] != OrderDao::STATE_NEW) {
            return $this->ajaxError([
                'order' => 'disabled',
                'error' => 'Извините, но этот заказ уже выполнен кем-то другим!',
            ]);
        }

        //$this->debugSleep();

        $res = false;

        // TODO: тут есть вероятность дэдлока, но он будет не вечным, а до секунды, поэтому забъем
        if (LockDao::i()->lock(LockDao::USER, $order['user_id'])) {
            if (LockDao::i()->lock(LockDao::USER, $this->USER['id'])) {
                $res = OrderDao::i()->execute($order, $this->USER['id']);
                if ($res) {
                    $price = $order['price'] - $order['commission'];
                    $userPaymentId = UserDao::i()->updateMoney($order['user_id'], -$order['price'], -$order['price'], $orderId);
                    $executerPaymentId = UserDao::i()->updateMoney($this->USER['id'], $price, 0, $orderId);
                    if ($userPaymentId && $executerPaymentId) {
                        OrderDao::i()->_updateRaw($order['id'], [
                            'user_payment_id' => $userPaymentId,
                            'executer_payment_id' => $executerPaymentId,
                        ]);
                    } else {
                        // Сюда мы не должны попадать при нормальной работе и надо бы залогировать ошибку и потом смотреть что произошло и обновить баланс юзера
                    }
                } else {
                    // Сюда мы не должны попадать при нормальной работе и надо бы залогировать ошибку
                }

                LockDao::i()->unlock(LockDao::USER, $this->USER['id']);
            }
            LockDao::i()->unlock(LockDao::USER, $order['user_id']);
        }

        if ($res) {
            $this->USER = UserDao::i()->getById($this->USER['id']);
            $this->updateUserData();

            if (OrderDao::i()->needOrderSocketUpdate($order)) {
                BaseWS::i()->send(0, ['type' => 'order', 'action' => 'executed', 'id' => $orderId]);
            } else {
                BaseWS::i()->send($order['user_id'], ['type' => 'order', 'action' => 'executed', 'id' => $orderId]);
            }
        }

        return $res ? $this->ajaxSuccess() : $this->ajaxError([
            'error' => 'Произошла ошибка, попробуйте ещё раз.',
        ]);
    }

    public function deleteAction()
    {
        if (!$this->isAjax()) {
            return $this->redirect('/');
        }

        if (!$this->USER) {
            return $this->ajaxError(['error' => 'auth']);
        }

        if (!$this->checkCSRF()) {
            return $this->ajaxError(['type' => 'csrf']);
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

        //$this->debugSleep();

        $res = false;
        if (LockDao::i()->lock(LockDao::USER, $this->USER['id'])) {
            $res = OrderDao::i()->delete($order, $this->USER['id']);

            if ($res) {
                if (!UserDao::i()->updateMoney($this->USER['id'], 0, -$order['price'], $orderId)) {
                    // Сюда мы не должны попадать при нормальной работе и надо бы залогировать ошибку и потом смотреть что произошло и обновить баланс юзера
                }
            }

            LockDao::i()->unlock(LockDao::USER, $this->USER['id']);
        }

        if ($res) {
            $this->USER = UserDao::i()->getById($this->USER['id']);
            $this->updateUserData();

            if (OrderDao::i()->needOrderSocketUpdate($order)) {
                BaseWS::i()->send(0, ['type' => 'order', 'action' => 'deleted', 'id' => $orderId]);
            } else {
                BaseWS::i()->send($order['user_id'], ['type' => 'order', 'action' => 'deleted', 'id' => $orderId]);
            }
        }

        return $res ? $this->ajaxSuccess() : $this->ajaxError([
            'error' => 'Произошла ошибка, попробуйте ещё раз.',
        ]);
    }
}
