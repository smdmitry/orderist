<?php

class IndexController extends BaseController
{
	const ORDERS_PER_PAGE = 10;

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
		$orders = $this->_prepareOrders($lastOrderId);

		$hasNext = count($orders) >= self::ORDERS_PER_PAGE;

		$data = [
			'html' => $this->renderView('index/orders_block'),
			'has_next' => $hasNext,
		];

		$this->ajaxSuccess($data);
	}

	protected function _prepareOrders($lastOrderId = 0)
	{
		$orders = OrderDao::i()->getOrders(OrderDao::STATE_NEW, self::ORDERS_PER_PAGE, $lastOrderId);
		$orders = OrderDao::i()->prepareOrders($orders);
		$this->view->orders = $orders;
		return $orders;
	}
}