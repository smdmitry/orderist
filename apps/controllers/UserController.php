<?php

class UserController extends BaseController
{
	const ORDERS_PER_PAGE = 5;
    const PAYMENTS_PER_PAGE = 10;

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
		$orders = OrderDao::i()->getUserOrders($this->USER['id'], $state, self::ORDERS_PER_PAGE);
        $orders = OrderDao::i()->prepareOrders($orders);

		$this->view->state = $state;
		$this->view->orders = $orders;

		$this->view->navTab = self::TAB_MY_ORDERS;
	}
	public function getorderspageAction()
	{
        if (!$this->USER) {
            return $this->ajaxSuccess(['redirect' => '/orders/']);
        }

		$lastOrderId = (int)$this->p('last_order_id', 0);
		$state = (int)$this->p('state', 0);

		$orders = OrderDao::i()->getUserOrders($this->USER['id'], $state, self::ORDERS_PER_PAGE, $lastOrderId);
        $orders = OrderDao::i()->prepareOrders($orders);
		$this->view->orders = $orders;

		$hasNext = count($orders) >= self::ORDERS_PER_PAGE;

		$data = [
			'html' => $this->renderView('index/orders_block'),
			'has_next' => $hasNext,
		];

		$this->ajaxSuccess($data);
	}

	public function cashAction()
	{
        if (!$this->USER) {
            return $this->ajaxSuccess(['redirect' => '/']);
        }

		$this->view->navTab = self::TAB_CASH;
        $this->view->payments = UserDao::i()->getUserPayments($this->USER['id'], self::PAYMENTS_PER_PAGE);
	}
    public function getpaymentspageAction()
    {
        if (!$this->USER) {
            return $this->ajaxSuccess(['redirect' => '/']);
        }

        $lastPaymentId = (int)$this->p('last_payment_id', 0);

        $payments = UserDao::i()->getUserPayments($this->USER['id'], self::PAYMENTS_PER_PAGE, $lastPaymentId);
        $this->view->payments = $payments;

        $hasNext = count($payments) >= self::PAYMENTS_PER_PAGE;

        $data = [
            'html' => $this->renderView('user/payments_block'),
            'has_next' => $hasNext,
        ];

        $this->ajaxSuccess($data);
    }

	public function signupAction()
	{
		if ($this->USER) {
			return $this->ajaxSuccess(['redirect' => '/orders/']);
		}
		$filter = new \Phalcon\Filter();

		$name = $filter->sanitize($this->p('user_name', ''), 'string');
		$email = $filter->sanitize($this->p('user_email', ''), 'email');
		$password = $this->p('user_password', '');

		if ($this->p('name') || $this->p('email')) {
			return $this->ajaxSuccess(['error' => 'Введите Ваше имя и Email']);
		}

		if ($this->p('submit')) {
			$res = UserDao::i()->addUser($name, $email, $password);

			if (!$res || !empty($res['errors'])) {
				return $this->ajaxSuccess(['errors' => $res['errors']]);
			} else {
				$user = UserDao::i()->getById($res);

				BaseAuth::i()->authUser($user);
				BaseMailer::i()->sendByType($user, BaseMailer::TYPE_SIGNUP);

				return $this->ajaxSuccess(['redirect' => '/orders/']);
			}
		}

		$data = [
			'html' => $this->renderView('user/singup_popup'),
		];

		return $this->ajaxSuccess($data);
	}

	public function loginAction()
	{
        if ($this->USER) {
            return $this->ajaxSuccess(['redirect' => '/orders/']);
        }

		$filter = new \Phalcon\Filter();

		$email = $filter->sanitize($this->p('user_email', ''), 'email');
		$password = $this->p('user_password', '');

		if ($this->p('submit')) {
			$user = UserDao::i()->getByEmailPassword($email, $password);

			if ($user) {
				BaseAuth::i()->authUser($user);
				return $this->ajaxSuccess(['redirect' => '/orders/']);
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
        $amount = (int)$this->p('amount');

        if (UserDao::i()->lock($this->USER['id'])) {
            if ($amount == 0) {
                if ($this->USER['cash'] != 0) {
                    UserDao::i()->addMoney($this->USER['id'], -$this->USER['cash'], -$this->USER['hold']);
                }
            } else {
                UserDao::i()->addMoney($this->USER['id'], $amount);
            }

            UserDao::i()->unlock($this->USER['id']);
        }

        $user = UserDao::i()->getById($this->USER['id']);
        $this->updateBaseData([
            'cash' => UserDao::i()->getField($user, 'cash'),
            'hold' => UserDao::i()->getField($user, 'hold'),
        ]);

        return $this->ajaxSuccess();
    }
}
