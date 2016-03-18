<?php

class IndexController extends BaseController
{
	const ORDERS_PER_PAGE = 5;

	public function indexAction()
	{
		$this->_prepareOrders();
	}

	public function ordersAction()
	{
		$this->_prepareOrders();
		$this->view->navTab = self::TAB_ORDERS;
	}

	public function getpageAction()
	{
		$lastOrderId = $this->p('last_order_id');
		$orders = OrderDao::i()->getOrders(OrderDao::STATE_NEW, self::ORDERS_PER_PAGE, $lastOrderId);
		$this->view->orders = $orders;

		$hasNext = count($orders) >= self::ORDERS_PER_PAGE;

		$data = [
			'html' => $this->renderView('index/orders_block'),
			'has_next' => $hasNext,
		];

		$this->ajaxSuccess($data);
	}

	protected function _prepareOrders()
	{
		$orders = OrderDao::i()->getOrders(OrderDao::STATE_NEW, self::ORDERS_PER_PAGE);
		$this->view->orders = $orders;
	}
}
