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

        $this->view->USER = $this->USER = $user;

        $this->view->e = new Phalcon\Escaper();

        $this->view->navTab = 0;
        $this->view->staticDomain = \Phalcon\DI::getDefault()->getConfig()['static'];
        $this->view->requestParams = $this->p('*');

        if (!array_key_exists('debug', $_COOKIE)) {
            BaseService::i()->setCookie('debug', 1, 365 * 24 * 60 * 60);
        }
    }

    protected function checkCSRF()
    {
        $token = $this->p('simpletoken', (isset($_SERVER['HTTP_X_SIMPLE_TOKEN']) ? $_SERVER['HTTP_X_SIMPLE_TOKEN'] : false));
        return BaseService::i()->checkCSRFToken($token);
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

        $result = [
            'res' => 0,
            'data' => $data,
        ];

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

    public function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    public function updateUserData($event = true)
    {
        $user = $this->USER;
        if (!empty($user)) {
            $cash = UserDao::i()->getField($user, 'cash');
            $hold = UserDao::i()->getField($user, 'hold');

            $this->updateBaseData([
                'cash' => $cash,
                'hold' => $hold,
            ]);

            if ($event) {
                BaseWS::i()->send($user['id'], ['type' => 'cash', 'cash' => $cash, 'hold' => $hold]);
            }
        }
    }

    private function updateBaseData($data)
    {
        $this->baseData = array_merge($this->baseData, $data);
    }

    protected function debugSleep($data)
    {
        if (DEBUG && $this->isAjax() && empty($data['html'])) {
            usleep(rand() % 2 ? rand(1, 1000) : rand(1000000, 3000000));
        }
    }
}
