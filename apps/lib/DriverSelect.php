<?php

class DriverSelect
{
    private $string = [];

    public function __toString()
    {
        $query = "SELECT ";

        if (isset($this->string['distinct']) && $this->string['distinct']) {
            $query .= "{$this->string['distinct']} ";
        }

        $query .= implode(', ', $this->string['columns']);

        $table = $this->_tableQuoted($this->string['from']['name']);
        $query .= " FROM {$table}";

        if (isset($this->string['use_index']) && $this->string['use_index']) {
            $query .= " USE INDEX(`{$this->string['use_index']}`)";
        }

        if (isset($this->string['force_index']) && $this->string['force_index']) {
            $query .= " FORCE INDEX(`{$this->string['force_index']}`)";
        }

        if (isset($this->string['join']) && !empty($this->string['join'])) {
            $join = implode("\n ", $this->string['join']);
            $query .= "\n {$join}";
        }

        if (isset($this->string['where']) && !empty($this->string['where'])) {
            $where = implode(') AND (', $this->string['where']);
            $query .= " WHERE ({$where})";
        }

        if (isset($this->string['group']) && $this->string['group']) {
            $query .= " {$this->string['group']}";
        }

        if (isset($this->string['having']) && $this->string['having']) {
            $query .= " HAVING {$this->string['having']}";
        }

        if (isset($this->string['order']) && $this->string['order']) {
            $order = implode(', ', $this->string['order']);
            $query .= " ORDER BY {$order}";
        }

        if (isset($this->string['limit']) && $this->string['limit']) {
            $query .= " {$this->string['limit']}";
        }

        return $query;
    }

    /**
     * @return DriverSelect
     */
    public function having($having)
    {
        $this->string['having'] = $having;
        return $this;
    }

    /**
     * @param int $count
     * @param int $offset
     * @return DriverSelect
     */
    public function limit($count, $offset = 0)
    {
        $count = (int)$count;
        $offset = (int)$offset;
        $this->string['limit'] = "LIMIT $count" . ($offset ? ' OFFSET ' . $offset : '');
        return $this;
    }

    /**
     * @return DriverSelect
     */
    public function order($spec)
    {
        if (is_array($spec)) {
            foreach ($spec as $i => &$v) $v = DriverSql::quoteIdentifier($v);
            $spec = implode(', ', $spec);
        } elseif ($spec instanceof DriverExpr) {
            $spec = $spec->__toString();
        } else {
            $spec = DriverSql::quoteIdentifier($spec);
        }
        $spec = str_ireplace(array(' ASC`', ' DESC`'), array('` ASC', '` DESC'), $spec);
        if (!mb_stripos($spec, 'ASC') && !mb_stripos($spec, 'DESC')) $spec .= ' ASC';

        if (!isset($this->string['order'])) {
            $this->string['order'] = [];
        }
        $this->string['order'][] = $spec;
        return $this;
    }

    /**
     * @return DriverSelect
     */
    public function group($spec)
    {
        if (is_array($spec)) {
            foreach ($spec as $i => &$v) $v = DriverSql::quoteIdentifier($v);
            $spec = implode(', ', $spec);
        } elseif ($spec instanceof DriverExpr) {
            $spec = $spec->__toString();
        } else {
            $spec = DriverSql::quoteIdentifier($spec);
        }

        $this->string['group'] = "GROUP BY $spec";
        return $this;
    }

    /**
     * @return DriverSelect
     */
    public function useIndex($indexName)
    {
        $this->string['use_index'] = $indexName;
        return $this;
    }

    /**
     * @return DriverSelect
     */
    public function forceIndex($indexName)
    {
        $this->string['force_index'] = $indexName;
        return $this;
    }

    /**
     * @return DriverSelect
     */
    public function from($name, $cols = ['*'])
    {
        if (!isset($this->string['from'])) {
            $this->string['from'] = [];
        }
        $this->string['from']['name'] = $name;
        $this->columns($cols, $name);
        return $this;
    }

    public function distinct()
    {
        $this->string['distinct'] = 'DISTINCT';
        return $this;
    }

    private function _tableQuoted($name)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) $tableSql = DriverSql::quoteIdentifier($v)." AS ".DriverSql::quoteIdentifier($k);
        } elseif ($name instanceof DriverExpr) {
            $tableSql = $name->__toString();
        } else {
            $tableSql = DriverSql::quoteIdentifier($name);
        }
        return $tableSql;
    }

    /**
     * @return DriverSelect
     */
    public function columns($cols, $table = null)
    {
        if (is_array($table)) {
            $_table = ''; foreach ($table as $k => $v) $_table = $k;
            $table = $_table;
        }
        if ($cols) {
            if (!is_array($cols)) $cols = [$cols];
            foreach ($cols as $ascol => $expr) {
                if (is_int($ascol)) {
                    if ($expr instanceof DriverExpr) {
                        $col = $expr->__toString();
                    } else {
                        $col = ($table && !mb_strpos($expr, '(') && !mb_strpos($expr, '.') ? DriverSql::quoteIdentifier($table)."." : '')
                            . DriverSql::quoteIdentifier($expr);
                    }
                } else {
                    $col = ($table && !mb_strpos($expr, '(') && !mb_strpos($expr, '.') ? DriverSql::quoteIdentifier($table).".".Driver_Sql::quoteIdentifier($expr) : $expr)
                        . " AS ".DriverSql::quoteIdentifier($ascol);
                }
                if (!isset($this->string['columns'])) {
                    $this->string['columns'] = [];
                }
                $this->string['columns'][] = $col;
            }
        }
        return $this;
    }

    /**
     * @return DriverSelect
     */
    private function _join($type, $name, $cond, $cols = ['*'])
    {
        $tableSql = $this->_tableQuoted($name);

        if ($type == 'left') $join = "LEFT JOIN";
        elseif ($type == 'inner') $join = "INNER JOIN";

        if (!isset($this->string['join'])){
            $this->string['join'] = [];
        }
        $this->string['join'][] = "$join $tableSql ON $cond";
        $this->columns($cols, $name);
        return $this;
    }

    /**
     * @return DriverSelect
     */
    public function joinLeft($name, $cond, $cols = ['*'])
    {
        return $this->_join('left', $name, $cond, $cols);
    }

    /**
     * @return DriverSelect
     */
    public function join($name, $cond, $cols = ['*'])
    {
        return $this->_join('inner', $name, $cond, $cols);
    }

    /**
     * @return DriverSelect
     */
    public function joinInner($name, $cond, $cols = ['*'])
    {
        return $this->_join('inner', $name, $cond, $cols);
    }

    /**
     * @return DriverSelect
     */
    public function where($cond, $value = null)
    {
        if (is_array($value)) {
            foreach ($value as $i => &$v) $v = DriverSql::quote($v);
            $value = implode(', ', $value);
        } elseif ($value instanceof DriverExpr) {
            $value = $value->__toString();
        } else {
            $value = DriverSql::quote($value);
        }
        $cond = str_replace('?', $value, $cond);
        if (!isset($this->string['where'])){
            $this->string['where'] = [];
        }
        $this->string['where'][] = $cond;
        return $this;
    }
}

