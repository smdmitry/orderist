<?php

class AdminController extends BaseController
{
    public function indexAction()
    {
        $this->norender();
        echo '<a target="_blank" href="/admin/debug/?debug=1">Включить отладку</a><br>';
        echo '<a target="_blank" href="/admin/debug/?debug=0">Выключить отладку</a><br>';
        echo '<a target="_blank" href="/admin/clearmemcache/">Очистить Memcache</a><br>';
        echo '<a target="_blank" href="/adminer.php">Администрирование MySQL</a><br>';
    }

    public function testAction()
    {
        $this->norender();
    }

    public function debugAction()
    {
        $debug = (int)$this->p('debug', 0);
        $res = BaseService::i()->setCookie('debug', $debug, 3600);
        $this->ajaxSuccess(['res' => $res, 'debug' => $debug]);
    }

    public function clearmemcacheAction()
    {
        $res = BaseMemcache::i()->mc->flush();
        $this->ajaxSuccess(['res' => $res]);
    }

    public function recoverAction()
    {
        // Тут будет восстановление после сбоя
    }
}
