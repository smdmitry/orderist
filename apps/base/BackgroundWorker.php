<?php

class BackgroundWorker extends \Phalcon\DI\Injectable
{
    protected $_jobs = [];

    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance;}
    protected function __construct() {}

    public function hasJob()
    {
        return !empty($this->_jobs);
    }

    public function doJob()
    {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        session_write_close();

        foreach ($this->_jobs as $job) {
            call_user_func_array($job[0], $job[1]);
        }
    }

    public function addJob($function, $params = [], $id = null)
    {
        if (DEBUG) {
            return call_user_func_array($function, $params);
        }

        if ($id === null) {
            $this->_jobs[] = [$function, $params];
        } else {
            $this->_jobs[] = [$function, $params];
        }

        return $this;
    }
}