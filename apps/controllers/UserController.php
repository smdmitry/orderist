<?php

class UserController extends BaseController
{
	const ORDERS_PER_PAGE = 10;
    const PAYMENTS_PER_PAGE = 30;

	public function indexAction()
	{
		$this->view->navTab = self::TAB_USER;
	}

	public function ordersAction()
	{
		if (!$this->USER) {
            return $this->redirect('/');
        }

		$state = (int)$this->p('state', OrderDao::STATE_NEW);
		$this->_prepareOrders($state);

		$this->view->navTab = self::TAB_MY_ORDERS;
	}
	public function getorderspageAction()
	{
        if (!$this->USER) {
			return $this->redirect('/');
        }

		$lastOrderId = (int)$this->p('last_order_id', 0);
		$state = (int)$this->p('state', 0);

		$this->_prepareOrders($state, $lastOrderId);

		$data = [
			'html' => $this->renderView('index/orders_block'),
			'has_next' => $this->view->hasNext,
		];

		$this->ajaxSuccess($data);
	}
	protected function _prepareOrders($state, $lastOrderId = 0)
	{
		$orders = OrderDao::i()->getUserOrders($this->USER['id'], $state, self::ORDERS_PER_PAGE, $lastOrderId);
		$orders = OrderDao::i()->prepareOrders($orders);

		$this->view->state = $state;
		$this->view->orders = $orders;
		$this->view->isMe = true;
		$this->view->hasNext = count($orders) >= self::ORDERS_PER_PAGE;

		return $orders;
	}

	public function cashAction()
	{
        if (!$this->USER) {
            return $this->redirect('/');
        }

		$this->_preparePayments();
		$this->view->navTab = self::TAB_CASH;
        $this->view->payments = UserDao::i()->getUserPayments($this->USER['id'], self::PAYMENTS_PER_PAGE);
	}
    public function getpaymentspageAction()
    {
        if (!$this->USER) {
			return $this->redirect('/');
        }

        $lastPaymentId = (int)$this->p('last_payment_id', 0);
		$this->_preparePayments($lastPaymentId);

        $data = [
            'html' => $this->renderView('user/payments_block'),
            'has_next' => $this->view->hasNext,
        ];

        $this->ajaxSuccess($data);
    }
	protected function _preparePayments($lastPaymentId = 0)
	{
		$payments = UserDao::i()->getUserPayments($this->USER['id'], self::PAYMENTS_PER_PAGE, $lastPaymentId);
		$this->view->payments = $payments;
		$this->view->hasNext = count($payments) >= self::PAYMENTS_PER_PAGE;
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
			$this->debugSleep();

			$password = $password ? $password : substr(md5(uniqid()), 0, 12);
			$res = UserDao::i()->addUser($name, $email, $password);

			if (!$res || !empty($res['errors'])) {
				return $this->ajaxSuccess(['errors' => $res['errors']]);
			} else {
				$user = UserDao::i()->getById($res);

				BaseAuth::i()->authUser($user);
				BaseMailer::i()->sendByType($user, BaseMailer::TYPE_SIGNUP, [
                    'password' => $password,
                ]);

				return $this->redirect('/');
			}
		}

		$data = [
			'html' => $this->renderView('user/singup_popup'),
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
				return $this->ajaxSuccess(['error' => 'Неправильный Email или пароль.']);
			}
		}

		$data = [
			'html' => $this->renderView('user/login_popup'),
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

		$this->updateUserData(false);

		return $this->ajaxSuccess();
	}

	/**
	 * Нужно для проверки авторизации юзера из NodeJS сервера
	 */
	public function authAction()
	{
		if ($this->USER) {
			return $this->ajaxSuccess(['user_id' => $this->USER['id']]);
		}

		return $this->ajaxSuccess();
	}
}
