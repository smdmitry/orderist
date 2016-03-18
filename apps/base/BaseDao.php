<?php

class BaseDao
{
    /**
     * @var \Phalcon\Db\Adapter\Pdo\Mysql
     */
    protected $db;

    public static function i() {static $i; $i = new static(); return $i;}

    private function __construct()
    {
        $this->db = \Phalcon\DI::getDefault()->getDb();
    }

    protected function implodeBind($array)
    {
        $data = [];
        foreach ($array as $value) {
            $data[] = is_int($value) ? $value : $this->db->escapeString($value);
        }
        return implode(',', $data);
    }
}
