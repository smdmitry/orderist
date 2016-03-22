<?php

class AdminController extends BaseController
{
    public function indexAction()
    {
        $this->view->text = BaseMemcache::i()->get('admintext');
        BaseMemcache::i()->delete('admintext');
    }

    public function testAction()
    {
        $this->norender();
    }

    public function debugAction()
    {
        $debug = (int)$this->p('debug', 0);
        $res = BaseService::i()->setCookie('debug', $debug, 3600);
        BaseMemcache::i()->set('admintext', $debug ? 'Отладка включена' : 'Отладка выключена', 60);
        $this->redirect('/admin/');
    }

    public function clearmemcacheAction()
    {
        $res = BaseMemcache::i()->mc->flush();
        BaseMemcache::i()->set('admintext', $res ? 'Memcache очищен' : 'Ошибка', 60);
        $this->redirect('/admin/');
    }

    public function recoverAction()
    {
        // Тут будет восстановление после сбоя
        $res = true;
        BaseMemcache::i()->set('admintext', $res ? 'Всё ОК' : 'Исправили пару ошибок', 60);
        $this->redirect('/admin/');
    }
}
