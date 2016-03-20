<?php

class DbDriverBase extends DbDriverConnection
{
    public static function quote($value)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_float($value)) {
            return sprintf('%F', $value);
        } elseif (is_array($value)) {
            foreach ($value as &$val) {
                $val = self::quote($val);
            }
            return implode(', ', $value);
        } elseif ($value instanceof DbDriverExpr) {
            return $value->__toString();
        }
        return "'" . self::escape($value) . "'";
    }

    private static function escape($value)
    {
        return addcslashes($value, "\000\n\r\\'\"\032");
    }

    public static function quoteIdentifier($value)
    {
        if (mb_strpos($value, '*') !== false || mb_strpos($value, '(') !== false) return $value;
        if (mb_strpos($value, '.') !== false) {
            $vals = explode('.', $value);
            foreach ($vals as &$v) $v = self::quoteIdentifier($v);
            return implode('.', $vals);
        }
        $q = '`';
        return ($q . str_replace("$q", "$q$q", $value) . $q);
    }

    public function fetchAssoc($select)
    {
        return $this->_fetchAbstract(__FUNCTION__, $select);
    }

    public function fetchAll($select)
    {
        return $this->_fetchAbstract(__FUNCTION__, $select);
    }

    public function fetchRow($select)
    {
        return $this->_fetchAbstract(__FUNCTION__, $select);
    }

    public function fetchCol($select)
    {
        return $this->_fetchAbstract(__FUNCTION__, $select);
    }

    public function fetchOne($select)
    {
        return $this->_fetchAbstract(__FUNCTION__, $select);
    }

    public function writequery($table, $query)
    {
        return $this->queryWrite($query, '', $table);
    }

    public function insert($table, array $bind)
    {
        $query = $this->sqlInsert($table, $bind, '');
        return $this->queryWrite($query, $table);
    }

    public function insertMultiple($table, array $bindArray)
    {
        $query = $this->sqlInsertMultiple($table, $bindArray);
        return $this->queryWrite($query, $table);
    }

    public function insertd($table, array $bind, $duplicate = '')
    {
        $query = $this->sqlInsert($table, $bind, $duplicate);
        return $this->queryWrite($query, $table);
    }

    public function update($table, array $bind, $where = '')
    {
        $query = $this->sqlUpdate($table, $bind, $where);
        return $this->queryWrite($query, '', $table);
    }

    public function delete($table, $where = '')
    {
        $query = $this->sqlDelete($table, $where);
        return $this->queryWrite($query, '', $table);
    }

    private function _fetchAbstract($function, $select)
    {
        if (is_object($select)) {
            $query = $select->__toString();
        } else {
            $query = $select;
        }
        return parent::$function($query);
    }

    // --

    /**
     * @return DbDriverSelect
     */
    public function select()
    {
        return new DbDriverSelect();
    }

    /**
     * @return DbDriverExpr
     */
    public function expr($subject, $values = '')
    {
        return new DbDriverExpr($this->qq($subject, $values));
    }

    public function sqlInsert($table, array $bind, $duplicate = '', $insertIgnore = false)
    {
        $cols = $vals = [];

        foreach ($bind as $col => $val) {
            $cols[] = self::quoteIdentifier($col);
            if ($val instanceof DbDriverExpr) {
                $vals[] = $val->__toString();
                unset($bind[$col]);
            } else {
                $vals[] = self::quote($val);
            }
        }

        $sql = 'INSERT '.(!$insertIgnore ? '' : 'IGNORE ').'INTO '
            . self::quoteIdentifier($table)
            . ' (' . implode(', ', $cols) . ') '
            . 'VALUES (' . implode(', ', $vals) . ')';

        if (!$insertIgnore && $duplicate) {
            $sql .= ' ON DUPLICATE KEY UPDATE ';
            if (is_array($duplicate)) {
                $vals = [];
                foreach ($duplicate as $col => $val) {
                    $vals[] = self::quoteIdentifier($col) . ' = ' . self::quote($val);
                }
                $sql .= implode(', ', $vals);
            } else {
                $sql .= $duplicate;
            }
        }

        return $sql;
    }

    public function sqlInsertMultiple($table, array $binds)
    {
        $cols = $vals = [];

        foreach ($binds as $k => $bind) {
            foreach ($bind as $col => $val) {
                $cols[] = self::quoteIdentifier($col);

                if ($val instanceof DbDriverExpr) {
                    $val = $val->__toString();
                } else {
                    $val = self::quote($val);
                }

                $binds[$k][$col] = $val;
            }
        }

        $cols = array_unique($cols);

        foreach ($binds as $bind) {
            $sqlVals[] = implode(',', $bind);
        }

        $sql = "INSERT INTO "
            . self::quoteIdentifier($table)
            . ' (' . implode(', ', $cols) . ') '
            . 'VALUES ('. implode('), (', $sqlVals) .')';

        return $sql;
    }

    public function sqlUpdate($table, array $bind, $where = '')
    {
        $set = [];
        foreach ($bind as $col => $val) {
            if ($val instanceof DbDriverExpr) {
                $val = $val->__toString();
                unset($bind[$col]);
            } else {
                $val = self::quote($val);
            }
            $set[] = self::quoteIdentifier($col) . ' = ' . $val;
        }

        $sql = "UPDATE "
            . self::quoteIdentifier($table)
            . ' SET ' . implode(', ', $set)
            . (($where) ? " WHERE ($where)" : '');

        return $sql;
    }

    public function sqlDelete($table, $where = '')
    {
        return "DELETE FROM "
        . self::quoteIdentifier($table)
        . (($where) ? " WHERE ($where)" : '');
    }

    public function qq($subject, $values = '', $identifiers = [])
    {
        if (!is_array($values)) {
            $values = [$values];
        }
        if (!is_array($identifiers)) {
            $identifiers = [$identifiers];
        }

        if (!empty($values)) {
            for ($i = 0; $i < mb_strlen($subject); $i++) {
                $char = $this->getChar($subject, $i);
                if ($char == '?' && !empty($values)) {
                    $value = self::quote(array_shift($values));
                    $subject = $this->substr_replace($subject, $value, $i, 1);
                    $i += mb_strlen($value);
                }
            }
        }

        if (!empty($identifiers)) {
            for ($i = 0; $i < mb_strlen($subject); $i++) {
                $char = $this->getChar($subject, $i);
                if ($char == '@' && !empty($identifiers)) {
                    $value = self::quoteIdentifier(array_shift($identifiers));
                    $subject = $this->substr_replace($subject, $value, $i, 1);
                    $i += mb_strlen($value);
                }
            }
        }

        return $subject;
    }

    private function substr_replace($str, $replace, $start, $length = null)
    {
        $stringLength = mb_strlen($str);

        if ($start < 0) {
            $start = max(0, $stringLength + $start);
        } elseif ($start > $stringLength) {
            $start = $stringLength;
        }
        if ($length < 0) {
            $length = max(0, $stringLength - $start + $length);
        } elseif ((is_null($length) === true) || ($length > $stringLength)) {
            $length = $stringLength;
        }
        if (($start + $length) > $stringLength) {
            $length = $stringLength - $start;
        }

        return mb_substr($str, 0, $start) . $replace . mb_substr($str, $start + $length, $stringLength - $start - $length);
    }

    private function getChar($str, $index)
    {
        if (!is_scalar($str)) {
            return isset($str[$index]) ? $str[$index] : null;
        }

        return mb_substr($str, $index, 1);
    }
}

class DbDriverExpr
{
    protected $string = '';

    public function __construct($string)
    {
        $this->string = $string;
    }

    public function __toString()
    {
        return $this->string;
    }
}
