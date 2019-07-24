<?php

class UserController extends BaseController
{
	const ORDERS_PER_PAGE = 10;
    const PAYMENTS_PER_PAGE = 30;

	public function indexAction()
	{
		$this->view->navTab = self::TAB_USER;
	}

    public function profileAction()
    {
        $id = $this->p('id');
        $this->view->viewUser = UserDao::i()->getById($id);
    }

	public function ordersAction()
	{
		if (!$this->USER) {
            return $this->redirect('/');
        }

		$this->view->navTab = self::TAB_MY_ORDERS;

		$state = (int)$this->p('state', OrderDao::STATE_NEW);
		$lastOrderId = (int)$this->p('last_order_id', 0);
		$firstTime = (int)$this->p('first_time', 0);
		$offset = (int)$this->p('offset', 0);

		$this->_prepareOrders($state, $offset, $lastOrderId, $firstTime);

		if ($this->isAjax()) {
			$data = [
				'html' => $this->renderView('order/blocks/orders'),
				'has_next' => $this->view->hasNext,
				'next_offset' => $this->view->nextOffset,
			];

			$this->ajaxSuccess($data);
		}
	}
	protected function _prepareOrders($state, $offset = 0, $lastOrderId = 0, $firstTime = 0)
	{
		if (!$state) {
			$orders = OrderDao::i()->getOrders(
			    ['user_id' => $this->USER['id']],
                ['id', 'DESC'],
                self::ORDERS_PER_PAGE,
                0,
                $lastOrderId
            );
		} elseif ($state == OrderDao::STATE_NEW) {
			$orders = OrderDao::i()->getOrders(
			    ['user_id' => $this->USER['id'], 'state' => OrderDao::STATE_NEW],
                ['id', 'DESC'],
                self::ORDERS_PER_PAGE,
                0,
                $lastOrderId
            );
        } else if ($state == OrderDao::STATE_EXECUTED) {
			$orders = OrderDao::i()->getOrders(
			    ['user_id' => $this->USER['id'], 'state' => OrderDao::STATE_EXECUTED],
                ['executed', 'DESC'],
                self::ORDERS_PER_PAGE,
                $offset,
                $firstTime
            );
        } else if ($state == OrderDao::FAKE_STATE_EXECUTER) {
			$orders = OrderDao::i()->getOrders(
			    ['executer_id' => $this->USER['id'], 'state' => OrderDao::STATE_EXECUTED],
                ['executed', 'DESC'],
                self::ORDERS_PER_PAGE,
                $offset,
                $firstTime
            );
		}
		$orders = OrderDao::i()->prepareOrders($orders);

		$this->view->state = $state;
		$this->view->orders = $orders;
		$this->view->isMe = $state == OrderDao::FAKE_STATE_EXECUTER ? false : true;
		$this->view->hasNext = count($orders) >= self::ORDERS_PER_PAGE;
		$this->view->nextOffset = (int)$this->p('offset', 0) + count($orders);

		return $orders;
	}

	public function cashAction()
	{
        if (!$this->USER) {
            return $this->redirect('/');
        }

		$this->view->navTab = self::TAB_CASH;

		$this->_preparePayments((int)$this->p('last_payment_id', 0));

		if ($this->isAjax()) {
			$data = [
				'html' => $this->renderView('user/blocks/payments'),
				'has_next' => $this->view->hasNext,
				'next_offset' => $this->view->nextOffset,
			];

			$this->ajaxSuccess($data);
		}
	}
	protected function _preparePayments($lastPaymentId = 0)
	{
		$payments = UserDao::i()->getUserPayments($this->USER['id'], self::PAYMENTS_PER_PAGE, $lastPaymentId);
		$this->view->payments = $payments;
		$this->view->hasNext = count($payments) >= self::PAYMENTS_PER_PAGE;
		$this->view->nextOffset = (int)$this->p('offset', 0) + count($payments);
		return $payments;
	}

	public function signupAction()
	{
		if (!$this->isAjax()) {
			return $this->redirect('/');
		}

		if ($this->USER) {
			return $this->redirect('/orders/');
		}

		$name = BaseService::i()->filterText($this->p('user_name', ''));
		$email = $this->p('user_email', '');
		$password = $this->p('user_password', '');

		if ($this->p('submit')) {
			$password = $password ? $password : substr(md5(uniqid()), 0, 12);
			$res = UserDao::i()->addUser($name, $email, $password);

			if (!$res || !empty($res['errors'])) {
                // Existing email and password
                $user = UserDao::i()->getByEmail($email);
                if ($user && password_verify($password, $user['password'])) {
                    BaseAuth::i()->authUser($user);
                    return $this->redirect('/');
                }

				return $this->ajaxSuccess(['errors' => $res['errors']]);
			} else {
				$this->debugSleep(1); // Sorry for this, but I had to show the kitty somehow :)

				$user = UserDao::i()->getById($res);

				BaseAuth::i()->authUser($user);
				BaseMailer::i()->sendByType($user, BaseMailer::TYPE_SIGNUP, [
                    'password' => $password,
                ]);

				return $this->redirect('/');
			}
		}

		$data = [
			'html' => $this->renderView('user/popups/signup'),
		];

		return $this->ajaxSuccess($data);
	}

	public function loginAction()
	{
		if (!$this->isAjax()) {
			return $this->redirect('/');
		}

        if ($this->USER) {
			return $this->redirect('/orders/');
        }

		$email = $this->p('user_email', '');
		$password = $this->p('user_password', '');

		if ($this->p('submit')) {
			$this->debugSleep();

			$user = UserDao::i()->getByEmail($email);

			if ($user && password_verify($password, $user['password'])) {
				BaseAuth::i()->authUser($user);
				return $this->redirect('/orders/');
			} else {
				return $this->ajaxSuccess(['error' => _g('Wrong Email or password.')]);
			}
		}

		$data = [
			'html' => $this->renderView('user/popups/login'),
		];

		return $this->ajaxSuccess($data);
	}

	public function logoutAction()
	{
		BaseAuth::i()->logout();
		return $this->redirect('/');
	}

    public function addcashAction()
    {
		if (!$this->USER) {
			return $this->redirect('/');
		}

		if (!$this->checkCSRF()) {
			return $this->ajaxError(['type' => 'csrf']);
		}

        $amount = (int)$this->p('amount');

		$amount = $amount > 100000000 ? 100000000 : $amount;

        if (LockDao::i()->lock(LockDao::USER, $this->USER['id'])) {
            if ($amount == 0) {
				UserDao::i()->updateMoney($this->USER['id'], -$this->USER['cash'], -$this->USER['hold']);
            } else {
                UserDao::i()->updateMoney($this->USER['id'], $amount);
            }

			LockDao::i()->unlock(LockDao::USER, $this->USER['id']);
        }

        $this->USER = UserDao::i()->getById($this->USER['id']);
        $this->updateUserData();

        return $this->ajaxSuccess();
    }

	public function getcashAction()
	{
		if (!$this->USER) {
			return $this->redirect('/');
		}

		$this->updateUserData();

		return $this->ajaxSuccess();
	}

	/**
	 * NodeJS server needs this to check if user is authentificated
	 */
	public function authAction()
	{
		if ($this->USER) {
			return $this->ajaxSuccess(['user_id' => $this->USER['id']]);
		}

		return $this->ajaxSuccess();
	}
}
