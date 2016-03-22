<?php

class DbDriverConnection
{
    /**
     * @var \PDO
     */
    private $_connection;
    private $_config;

    public function __construct($config)
    {
        $this->_config = $config;
    }

    public function fetchAssoc($query)
    {
        return $this->querySelect(__FUNCTION__, $query);
    }

    public function fetchAll($query)
    {
        return $this->querySelect(__FUNCTION__, $query);
    }

    public function fetchRow($query)
    {
        return $this->querySelect(__FUNCTION__, $query);
    }

    public function fetchCol($query)
    {
        return $this->querySelect(__FUNCTION__, $query);
    }

    public function fetchOne($query)
    {
        return $this->querySelect(__FUNCTION__, $query);
    }

    private function querySelect($func, $query)
    {
        $this->connect();

        $result = [];
        try {
            if (DEBUG && substr($query, 0, 8) != 'EXPLAIN ') {
                flog("MySQL: {$query}");
                $res = BaseDao::i()->db->fetchAll('EXPLAIN ' . $query);
                fwarn("MySQL EXPLAIN: " . json_encode($res));
            }
            $sth = $this->_connection->prepare($query);
            $sth->execute();

            if ($func == 'fetchCol') {
                $result = $sth->fetchAll(PDO::FETCH_COLUMN, 0);
            } elseif ($func == 'fetchAll') {
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($func == 'fetchOne') {
                $result = $sth->fetchColumn(0);
            } elseif ($func == 'fetchRow') {
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                $result = @$result[0];
            } elseif ($func == 'fetchPairs') {
                $result = [];
                while ($row = $sth->fetch(PDO::FETCH_NUM)) {
                    $result[$row[0]] = $row[1];
                }
            } elseif ($func == 'fetchAssoc') {
                $result = [];
                while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                    $tmp = array_values(array_slice($row, 0, 1));
                    $result[$tmp[0]] = $row;
                }
            }
        } catch (PDOException $e) {
            throw new Exception($e->getMessage() . '; ' . $query);
        }

        return $result;
    }

    public function queryWrite($query, $table = null)
    {
        $this->connect();

        $count = $tries = 0;
        do {
            $retry = false;
            try {
                if (DEBUG) flog("MySQL: {$query}");
                $sth = $this->_connection->prepare($query);
                $sth->execute();
                $count = $sth->rowCount();
            } catch (PDOException $e) {
                if ($tries < 10 && ($e->errorInfo[1] == 1213)) {
                    $retry = true;

                    $trigger = [];
                    $trigger[] = __METHOD__;
                    $trigger[] = "[table = $table]";
                    $trigger[] = "tries[$tries]";
                    $trigger[] = $e->getCode();
                    $trigger[] = $e->errorInfo[1];
                    $trigger[] = isset($e->errorInfo[2]) ? $e->errorInfo[2] : "";

                    trigger_error(implode(' ', $trigger), E_USER_WARNING);
                    usleep(10000);
                } else {
                    throw new Exception($e->getMessage() . '; ' . $query, (int)$e->getCode(), $e);
                }
                $tries++;
            }
        } while ($retry);

        return $count;
    }

    public function lastInsertId()
    {
        return $this->_connection->lastInsertId();
    }

    private function connect()
    {
        if (!empty($this->_connection)) {
            return $this->_connection;
        }

        $user = $this->_config['username'];
        $password = $this->_config['password'];
        $host = $this->_config['host'];
        $base = $this->_config['dbname'];
        $options = $this->_config['options'];

        $dsn = "mysql:dbname={$base};host={$host}";
        if (!empty($this->_config['charset'])) {
            $dsn .= ';charset=' . $this->_config['charset'];
        }

        try {
            $connection = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $exception) {
            sleep(1);
            try {
                $connection = new PDO($dsn, $user, $password, $options);
            } catch (PDOException $exception) {
                throw new Exception($host . ':' . $base . ' - ' . $exception->getMessage());
            }
        }

        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->query('USE ' . $base . ';');
        $this->_connection = $connection;

        if (DEBUG) flog("MySQL connect");

        return true;
    }
}
