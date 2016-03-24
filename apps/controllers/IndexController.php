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
        $this->view->navTab = self::TAB_ORDERS;

        $this->_prepareOrders((int)$this->p('last_order_id', 0));

        if ($this->isAjax()) {
            $data = [
                'html' => $this->renderView('order/blocks/orders'),
                'has_next' => $this->view->hasNext,
                'next_offset' => $this->view->nextOffset,
            ];

            $this->ajaxSuccess($data);
        }
	}
	protected function _prepareOrders($lastOrderId = 0)
	{
        $orders = OrderDao::i()->getOrders(['state' => OrderDao::STATE_NEW], ['id', 'DESC'], self::ORDERS_PER_PAGE, 0, $lastOrderId);
        $orders = OrderDao::i()->prepareOrders($orders);

        $this->view->orders = $orders;
        $this->view->hasNext = count($orders) >= self::ORDERS_PER_PAGE;
        $this->view->nextOffset = (int)$this->p('offset', 0) + count($orders);

        return $orders;
	}
}
