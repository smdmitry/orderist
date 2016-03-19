<?php

use Phalcon\Mvc\Controller;

class BaseController extends Controller
{
    const TAB_ORDERS = 1;
    const TAB_MY_ORDERS = 2;
    const TAB_CASH = 3;
    const TAB_USER = 4;

    protected $USER = null;
    protected $baseData = [];

    protected function initialize()
    {
        $user = BaseAuth::i()->getAuthUser();

        $this->USER = $user;
        $this->view->USER = $user;

        $this->view->e = new Phalcon\Escaper();
    }

    protected function ajaxSuccess($data = null)
    {
        $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_NO_RENDER);

        $result = [
            'res' => 1,
            'data' => $data,
        ];
        if (!empty($this->baseData)) {
            $result['base'] = $this->baseData;
        }

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
        echo $this->view->getPartial($partialPath);
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
        $this->view->USER = $this->USER;
        $this->view->navTab = 0;
        $this->view->staticDomain = 'orderist.smd.im';
    }

    public function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    public function updateBaseData($data)
    {
        $this->baseData = array_merge($this->baseData, $data);
    }
}
