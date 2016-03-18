<?php

class UserController extends BaseController
{
	const ORDERS_PER_PAGE = 5;

	public function indexAction()
	{
		$this->view->navTab = self::TAB_USER;
	}

	public function ordersAction()
	{
		$userId = 1;

		$state = (int)$this->p('state', OrderDao::STATE_NEW);
		$orders = OrderDao::i()->getUserOrders($userId, $state, self::ORDERS_PER_PAGE);

		$this->view->state = $state;
		$this->view->orders = $orders;

		$this->view->navTab = self::TAB_MY_ORDERS;
	}
	public function getorderspageAction()
	{
		$userId = 1;

		$lastOrderId = (int)$this->p('last_order_id', 0);
		$state = (int)$this->p('state', 0);

		$orders = OrderDao::i()->getUserOrders($userId, $state, self::ORDERS_PER_PAGE, $lastOrderId);
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
		$this->view->navTab = self::TAB_CASH;
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
}
