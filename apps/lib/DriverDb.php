<?php

class DriverDb extends DriverSql
{
    public static function writequery($table, $query)
    {
        return self::queryWrite($query, '', $table);
    }

    public static function insert($table, array $bind, $duplicate = '')
    {
        $query = self::sqlInsert($table, $bind, $duplicate);
        return parent::insert($query, '', $table);
    }

    public static function insertMultiple($table, array $bindArray)
    {
        $query = self::sqlInsertMultiple($table, $bindArray);
        return parent::insert($query, '', $table);
    }

    public static function insertd($table, array $bind, $duplicate = '')
    {
        $query = self::sqlInsert($table, $bind, $duplicate);
        return parent::insert($query, '', $table);
    }

    public static function update($table, array $bind, $where = '')
    {
        $query = self::sqlUpdate($table, $bind, $where);
        return self::queryWrite($query, '', $table);
    }

    public static function delete($table, $where = '')
    {
        $query = self::sqlDelete($table, $where);
        return self::queryWrite($query, '', $table);
    }

    private static function _fetchAbstract($function, $select)
    {
        if (is_object($select)) {
            $query = $select->__toString();
        } else {
            $query = $select;
        }
        return parent::$function($query);
    }

    public static function fetchCol($select)
    {
        return self::_fetchAbstract(__FUNCTION__, $select);
    }

    public static function fetchAll($select)
    {
        return self::_fetchAbstract(__FUNCTION__, $select);
    }

    public static function fetchOne($select)
    {
        return self::_fetchAbstract(__FUNCTION__, $select);
    }

    public static function fetchRow($select)
    {
        return self::_fetchAbstract(__FUNCTION__, $select);
    }

    public static function fetchAssoc($select)
    {
        return self::_fetchAbstract(__FUNCTION__, $select);
    }
}
