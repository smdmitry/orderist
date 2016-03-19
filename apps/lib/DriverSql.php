<?php

class DriverSql extends DriverDBQuery
{
    /**
     * @return DriverSelect
     */
    public static function select()
    {
        return new DriverSelect();
    }

    public static function qqInsertMulty($binds, &$sqlCols = null)
    {
        $sqlVals = [];
        foreach ($binds as $bind) {
            $sqlVals[] = self::qqInsert($bind, $sqlCols);
        }
        $sql = '('.implode('), (', $sqlVals).')';
        return $sql;
    }

    public static function qqInsert($bind, &$sqlCols=null)
    {
        $cols = [];
        $vals = [];
        foreach ($bind as $col => $val) {
            $cols[] = self::quoteIdentifier($col);
            if ($val instanceof DriverExpr) {
                $vals[] = $val->__toString();
                unset($bind[$col]);
            } else {
                $vals[] = self::quote($val);
            }
        }

        $sqlCols = implode(', ', $cols);
        $sqlVals = implode(', ', $vals);

        return $sqlVals;
    }

    public static function qqUpdate($bind)
    {
        $set = [];
        foreach ($bind as $col => $val) {
            if ($val instanceof DriverExpr) {
                $val = $val->__toString();
                unset($bind[$col]);
            } else {
                $val = self::quote($val);
            }
            $set[] = self::quoteIdentifier($col) . ' = ' . $val;
        }

        $sql = implode(', ', $set);
        return $sql;
    }

    public static function sqlInsert($table, array $bind, $duplicate = '', $insertIgnore = false)
    {
        $cols = $vals = [];

        foreach ($bind as $col => $val) {
            $cols[] = self::quoteIdentifier($col);
            if ($val instanceof DriverExpr) {
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

    public static function sqlInsertMultiple($table, array $binds)
    {
        $cols = $vals = [];

        foreach ($binds as $k => $bind) {
            foreach ($bind as $col => $val) {
                $cols[] = self::quoteIdentifier($col);

                if ($val instanceof DriverExpr) {
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

    public static function sqlReplaceMultiple($table, array $binds)
    {
        $cols = [];

        foreach ($binds as $k => $bind) {
            foreach ($bind as $col => $val) {
                $cols[] = self::quoteIdentifier($col);

                if ($val instanceof DriverExpr) {
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

        $sql = "REPLACE INTO "
            . self::quoteIdentifier($table)
            . ' (' . implode(', ', $cols) . ') '
            . 'VALUES ('. implode('), (', $sqlVals) .')';

        return $sql;
    }

    public static function sqlInsertMultipleIgnore($table, array $binds, &$dbName = null)
    {
        $cols = $vals = [];

        foreach ($binds as $k => $bind) {
            foreach ($bind as $col => $val) {
                $cols[] = self::quoteIdentifier($col);

                if ($val instanceof DriverExpr) {
                    $val = $val->__toString();
                } else {
                    $val = self::quote($val);
                }

                $binds[$k][$col] = $val;
            }
        }

        $cols = array_unique($cols);

        $sqlVals = [];
        foreach ($binds as $bind) {
            $sqlVals[] = implode(',', $bind);
        }

        $sql = "INSERT IGNORE INTO "
            . self::quoteIdentifier($table)
            . ' (' . implode(', ', $cols) . ') '
            . 'VALUES ('. implode('), (', $sqlVals) .')';

        return $sql;
    }

    public static function sqlInsertMultipleUpdate($table, array $binds, $duplicate = false)
    {
        $cols = $vals = [];

        foreach ($binds as $k => $bind) {
            foreach ($bind as $col => $val) {
                $cols[] = self::quoteIdentifier($col);

                if ($val instanceof DriverExpr) {
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


        if ($duplicate) {
            $sql .= ' ON DUPLICATE KEY UPDATE ';
            if (is_array($duplicate)) {
                $vals = array();
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


    public static function sqlUpdate($table, array $bind, $where = '')
    {
        $set = [];
        foreach ($bind as $col => $val) {
            if ($val instanceof DriverExpr) {
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

    public static function sqlDelete($table, $where = '')
    {
        return "DELETE FROM "
            . self::quoteIdentifier($table)
            . (($where) ? " WHERE ($where)" : '');
    }

    public static function sqlReplace($table, array $bind)
    {
        $cols = $vals = [];

        foreach ($bind as $col => $val) {
            $cols[] = self::quoteIdentifier($col);
            if ($val instanceof DriverExpr) {
                $vals[] = $val->__toString();
                unset($bind[$col]);
            } else {
                $vals[] = self::quote($val);
            }
        }
        $sql = 'REPLACE INTO '.self::quoteIdentifier($table).' ('.implode(', ', $cols).') VALUES ('.implode(', ', $vals).')';
        return $sql;
    }

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
        } elseif ($value instanceof DriverExpr) {
            return $value->__toString();
        }
        return "'" . self::escape($value) . "'";
    }

    public static function escape($value)
    {
        return addcslashes($value, "\000\n\r\\'\"\032");
    }

    public static function quoteInto($text, $value, $type = null, $count = null)
    {
        if ($count === null) {
            return str_replace('?', self::quote($value, $type), $text);
        } else {
            while ($count > 0) {
                if (mb_strpos($text, '?') != false) {
                    $text = self::substr_replace($text, self::quote($value, $type), mb_strpos($text, '?'), 1);
                }
                --$count;
            }
            return $text;
        }
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

    public static function qq($subject, $values = '', $identifiers = array())
    {
        if (!is_array($values)) {
            $values = array($values);
        }
        if (!is_array($identifiers)) {
            $identifiers = array($identifiers);
        }

        if (!empty($values)) {
            for ($i = 0; $i < mb_strlen($subject); $i++) {
                $char = self::getChar($subject, $i);
                if ($char == '?' && !empty($values)) {
                    $value = self::quote(array_shift($values));
                    $subject = self::substr_replace($subject, $value, $i, 1);
                    $i += mb_strlen($value);
                }
            }
        }

        if (!empty($identifiers)) {
            for ($i = 0; $i < mb_strlen($subject); $i++) {
                $char = self::getChar($subject, $i);
                if ($char == '@' && !empty($identifiers)) {
                    $value = self::quoteIdentifier(array_shift($identifiers));
                    $subject = self::substr_replace($subject, $value, $i, 1);
                    $i += mb_strlen($value);
                }
            }
        }

        return $subject;
    }

    protected static function substr_replace($str, $replace, $start, $length = null)
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

    protected static function getChar($str, $index)
    {
        if (!is_scalar($str)) {
            return isset($str[$index]) ? $str[$index] : null;
        }

        return mb_substr($str, $index, 1);
    }
}
