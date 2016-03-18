<?php

class OrderController extends BaseController
{
    public function createpopupAction()
    {
        if (!$this->USER) {
            return $this->ajaxError(['error' => 'auth']);
        }

        $this->view->commission = 0.18;

        $data = [
            'html' => $this->renderView('order/create_popup'),
        ];

        $this->ajaxSuccess($data);
    }
}
