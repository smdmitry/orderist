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
		$this->_prepareOrders((int)$this->p('last_order_id', 0));

		$data = [
			'html' => $this->renderView('index/orders_block'),
			'has_next' => $this->view->hasNext,
			'next_offset' => (int)$this->p('offset', 0) + count($this->view->orders),
		];

		$this->ajaxSuccess($data);
	}

	protected function _prepareOrders($lastOrderId = 0)
	{
		$orders = OrderDao::i()->getNewOrders(self::ORDERS_PER_PAGE, $lastOrderId);
		$orders = OrderDao::i()->prepareOrders($orders);
		$this->view->orders = $orders;
		$this->view->hasNext = count($orders) >= self::ORDERS_PER_PAGE;
		return $orders;
	}
}
