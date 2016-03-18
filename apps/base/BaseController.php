<?php

use Phalcon\Mvc\Controller;

class BaseController extends Controller
{
    protected function ajaxSuccess($data = null)
    {
        $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_NO_RENDER);

        $result = array(
            'res' => 1,
            'data' => $data,
        );

        header('Content-Type: application/json');

        $response = new \Phalcon\Http\Response();
        $response->setJsonContent($result);
        $response->send();

        return true;
    }

    protected function ajaxError($data = null)
    {
        $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_NO_RENDER);

        $result = array(
            'res' => 0,
            'data' => $data,
        );

        header('Content-Type: application/json');

        $response = new \Phalcon\Http\Response();
        $response->setJsonContent($result);
        $response->send();

        return false;
    }

    protected function norender()
    {
        $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_NO_RENDER);
        return false;
    }

    protected function renderView($partialPath)
    {
        ob_start();
        $this->view->partial($partialPath);
        $html = ob_get_contents();
        ob_clean();

        return $html;
    }

    protected function p($param = '*', $default = null)
    {
        $params = $this->request->get();
        $dispatcherParams = $this->dispatcher->getParams();

        $fullparams = array_merge($params, $dispatcherParams);

        return $param == '*' ? $fullparams : (isset($fullparams[$param]) ? $fullparams[$param] : $default);
    }

    protected function redirect($url, $code = 303)
    {
        return $this->response->redirect($url, true, $code)->sendHeaders();
    }

    public function beforeExecuteRoute($dispatcher)
    {

    }
}
