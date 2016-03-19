<?php

class BaseDao
{
    /**
     * @var DbDriverBase
     */
    protected $db;

    public static function i() {static $i; $i = new static(); return $i;}

    private function __construct()
    {
        $this->db = \Phalcon\DI::getDefault()->getDb();
    }
}
