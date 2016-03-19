<?php

class DriverDBQuery
{
    /**
     * @var \PDO
     */
    private static $_connection;
    private static $_config;

    public function __construct($config)
    {
        self::$_config = $config;
    }

    private static function _connect()
    {
        if (!empty(self::$_connection)) {
            return self::$_connection;
        }

        $user = self::$_config['username'];
        $password = self::$_config['password'];
        $host = self::$_config['host'];
        $base = self::$_config['dbname'];
        $options = self::$_config['options'];


        $dsn = "mysql:dbname={$base};host={$host}";
        if (!empty(self::$_config['charset'])) {
            $dsn .= ';charset=' . self::$_config['charset'];
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
        self::$_connection = $connection;

        return true;
    }

    private static function connect()
    {
        if (!self::_connect()) {
            $mess = "Can`t connect to DB";
            throw new Exception($mess);
        }

        return true;
    }

    public static function fetchCol($query)
    {
        return self::querySelect(__FUNCTION__, $query);
    }

    public static function fetchAll($query)
    {
        return self::querySelect(__FUNCTION__, $query);
    }

    public static function fetchOne($query)
    {
        return self::querySelect(__FUNCTION__, $query);
    }

    public static function fetchRow($query)
    {
        return self::querySelect(__FUNCTION__, $query);
    }

    public static function fetchPairs($query)
    {
        return self::querySelect(__FUNCTION__, $query);
    }

    public static function fetchAssoc($query)
    {
        return self::querySelect(__FUNCTION__, $query);
    }

    public static function insert($query, $table = null)
    {
        return self::queryWrite($query, $table);
    }

    public static function update($query, $table = null)
    {
        return self::queryWrite($query, $table);
    }

    public static function delete($query, $table = null)
    {
        return self::queryWrite($query, $table);
    }

    private static function querySelect($func, $query)
    {
        self::connect();

        $result = [];
        try {
            $sth = self::$_connection->prepare($query);
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
                $result = array();
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

    public static function queryWrite($query, $table = null)
    {
        self::connect();

        $count = $tries = 0;
        do {
            $retry = false;
            try {
                $sth = self::$_connection->prepare($query);
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

    public static function lastInsertId()
    {
        return self::$_connection->lastInsertId();
    }

    public static function queryFoundRows()
    {
        $sth = self::$_connection->prepare('SELECT FOUND_ROWS()');
        $sth->execute();
        $res = $sth->fetchColumn(0);
        return $res;
    }

    public static function escapeString($string = '')
    {
        self::connect();
        return self::$_connection->quote($string);
    }
}
