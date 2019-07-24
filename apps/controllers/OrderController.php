<?php

class OrderController extends BaseController
{
    // TODO: no cache here, but is rare location so ok without it
    public function orderAction()
    {
        $orderId = (int)$this->p('id');
        $order = OrderDao::i()->getById($orderId);
        $userId = $this->USER ? $this->USER['id'] : 0;

        if (!$order) {
            $this->view->error = _g('Error, order not found!');
            return;
        }

        $canView = $userId && ($order['user_id'] == $userId || $order['executer_id'] == $userId);
        if ($order['state'] != OrderDao::STATE_NEW && !$canView) {
            $this->view->error = _g('Error, you don\'t have access to this order!');
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
                'error' => _g('To create orders you need to login or signup.'),
            ]);
        }

        $this->view->commission = OrderDao::COMMISSION;

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

        $errors = [];
        if (mb_strlen($title) < 1) {
            $errors[] = _g('Provide order title!');
        } else if (mb_strlen($title) > 160) {
            $errors[] = _g('Title is too long!');
        }
        if ($price == 0) {
            $errors[] = _g('Provide order price!');
        } elseif ($price <= 1 || $commission <= 0) {
            $errors[] = _g('Price is too low!');
        }
        if (empty($errors) && $price - $commission <= 0) {
            $errors[] = _g('Executer will receive nothing!');
        }
        if (
            $price != (int)round($userPrice * 100, 0) ||
            $executer != (int)round($executerPrice * 100, 0)
        ) {
            $errors[] = _g('Oops, there is error in price calculation.');
            // Log error here, this branch in not reachable under normal usage
        }
        if (!empty($errors)) {
            return $this->ajaxError([
                'errors' => $errors,
            ]);
        }

        //$this->debugSleep();

        $userId = $this->USER['id'];
        if (LockDao::i()->lock(LockDao::USER, $userId)) { // Lock users balance
            $this->USER = UserDao::i()->getById($userId, 1); // Get updated user info

            // Check if user has enough cash, knowing that balance is locked
            $cash = $this->USER['cash'] - $this->USER['hold'];
            if ($price > $cash) {
                LockDao::i()->unlock(LockDao::USER, $userId);
                $need = ($price - $cash) / 100;
                return $this->ajaxError([
                    'error' => _f('order_not_enough_money', $need, (int)($need*100)),
                ]);
            }

            $orderId = OrderDao::i()->addOrder($userId, $title, $description, $price, $commission);
            if ($orderId) {
                // User is updated here (incl memcache data)
                $updated = UserDao::i()->updateMoney($userId, 0, $price, $orderId);
                if ($updated) {
                    OrderDao::i()->_updateRaw($orderId, [
                        'user_payment_id' => 1,
                    ]);
                } else {
                    // Log error here, this branch in not reachable under normal usage
                    // Need to update users balance
                }
            } else {
                // Log error here, this branch in not reachable under normal usage
            }

            LockDao::i()->unlock(LockDao::USER, $userId);

            $this->USER = UserDao::i()->getById($userId); // Update global user
        }

        $res = !empty($orderId) && $orderId;
        if ($res) {
            $this->updateUserData();
            BaseWS::i()->send(0, ['type' => 'order', 'action' => 'created', 'id' => $orderId]);
        }

        return $res ? $this->ajaxSuccess() : $this->ajaxError([
            'error' => _g('Error, please try again.'),
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
                'error' => _g('To execute orders you need to login or signup.'),
            ]);
        }

        if (!$this->checkCSRF()) {
            return $this->ajaxError(['type' => 'csrf']);
        }

        $orderId = (int)$this->p('order_id');
        $order = OrderDao::i()->getById($orderId);

        if (empty($order)) {
            return $this->ajaxError([
                'error' => _g('Error, order does not exist!'),
            ]);
        }

        if ($order['user_id'] == $this->USER['id']) {
            return $this->ajaxError([
                'error' => _f('order_is_yours', $orderId),
            ]);
        }

        if ($order['state'] != OrderDao::STATE_NEW) {
            return $this->ajaxError([
                'order' => 'disabled',
                'error' => _g('Sorry, this order was executed by someone else!'),
            ]);
        }

        //$this->debugSleep();

        $res = false;

        // TODO: Deadlock possibily here, but short-term (1 sec)
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
                        // Log error here, this branch in not reachable under normal usage
                        // Need to update users balance
                    }
                } else {
                    // Log error here, this branch in not reachable under normal usage
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
            'error' => _g('Error, please try again.'),
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
                'error' => _g('Error, order does not exist!'),
            ]);
        }

        if ($order['user_id'] != $this->USER['id']) {
            return $this->ajaxError([
                'error' => _g('Do not try to delete not your orders!'),
            ]);
        }

        if ($order['state'] != OrderDao::STATE_NEW) {
            return $this->ajaxError([
                'order' => 'disabled',
                'error' => _g('Sorry, you can not delete completed order!'),
            ]);
        }

        //$this->debugSleep();

        $res = false;
        if (LockDao::i()->lock(LockDao::USER, $this->USER['id'])) {
            $res = OrderDao::i()->delete($order, $this->USER['id']);

            if ($res) {
                if (!UserDao::i()->updateMoney($this->USER['id'], 0, -$order['price'], $orderId)) {
                    // Log error here, this branch in not reachable under normal usage
                    // Need to update users balance
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
            'error' => _g('Error, please try again.'),
        ]);
    }
}
