<?php

class BaseMemcache extends \Phalcon\DI\Injectable
{
    /**
     * @var Memcache
     */
    public $mc;

    private $CACHED_TTL = 1;
    private $cacheData   = []; // статический кеш на время фомирования страницы
    private $cacheDelete = []; // статический кеш на удаление ключей
    private $cachedTS    = []; // время жизни для ключей статического кеша
    private $cachedDelTS  = []; // время жизни для ключей статического кеша

    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance; }
    private function __construct()
    {
        if (DEBUG) finfo("Memcache connect");
        $this->mc = new Memcache();
        $this->mc->addserver('127.0.0.1', 11211);
    }

    private function _get($key)
    {
        if (DEBUG) {
            $log = is_array($key) ? ('mget ' . implode(',', $key)) : ('get ' . $key);
            finfo("Memcache: {$log}");
        }
        return $this->mc->get($key);
    }

    private function _set($key, $value, $ttl)
    {
        if (DEBUG) finfo("Memcache: set {$key}");
        return $this->mc->set($key, $value, 0, $ttl);
    }

    public function add($key, $value, $ttl)
    {
        if (DEBUG) finfo("Memcache: add {$key}");
        return $this->mc->add($key, $value, 0, $ttl);
    }

    private function _delete($key)
    {
        if (DEBUG) finfo("Memcache: delete {$key}");
        return $this->mc->delete($key);
    }

    public function get($keys)
    {
        $arrayRequested = is_array($keys);

        if (!$keys) {
            return $arrayRequested ? [] : '';
        }

        $time = microtime(true);
        $keysEmpty = 0;

        // проверяем в статическом кеше
        if ($arrayRequested) {
            $value = $delkey = [];

            foreach ($keys as $key) {
                if (array_key_exists($key, $this->cacheDelete) && ($time - $this->cachedDelTS[$key] < $this->CACHED_TTL)) {
                    $value[$key] = false;
                } else {
                    if (array_key_exists($key, $this->cacheData)
                        && isset($this->cachedTS[$key])
                        && ($time - $this->cachedTS[$key] < $this->CACHED_TTL)
                    ) {
                        $value[$key] = $this->cacheData[$key];
                    } else {
                        $delkey[] = $key;
                    }
                }
            }

            // удаляем ключи найденные в статическом кеше
            $keys = $delkey;
            if (empty($keys)) {
                $keysEmpty = 1;
            }
        } else {
            if (array_key_exists($keys, $this->cacheDelete) && ($time - $this->cachedDelTS[$keys] < $this->CACHED_TTL)) {
                $value = false;
            } else {
                if (array_key_exists($keys, $this->cacheData)
                    && isset($this->cachedTS[$keys])
                    && ($time - $this->cachedTS[$keys] < $this->CACHED_TTL)
                ) {
                    $value = $this->cacheData[$keys];
                    $keysEmpty = 1;
                }
            }
        }

        // ищем в mmcache
        if (!$keysEmpty) {
            if ($arrayRequested) {
                $mcResult = (array)$this->_get($keys);
            } else {
                $mcResult = $this->_get($keys);
            }

            // заносим в статический кеш
            if ($arrayRequested) {
                $value += $mcResult;
                $this->cacheData += $value;
                $this->cachedTS  += array_fill_keys(array_keys($mcResult), $time);
            } else {
                $value = $mcResult;
                $this->cacheData[$keys] = $value;
                $this->cachedTS[$keys] = $time;
            }
        }

        return $value;
    }

    public function set($key, $value, $seconds = 900)
    {
        $seconds = $seconds <= 2592000 ? (int)$seconds : 2592000; // мемкеш не ставит ключик, если указано время больше 30 дней
        $time = microtime(true);

        $result = $this->_set($key, $value, $seconds);

        // заносим в статический кеш
        $this->cacheData[$key] = $value;
        $this->cachedTS[$key] = $time;
        unset($this->cacheDelete[$key], $this->cachedDelTS[$key]);

        return $result;
    }

    public function delete($key)
    {
        $time = microtime(true);

        if (isset($this->cacheDelete[$key]) && ($time - $this->cachedDelTS[$key] < $this->CACHED_TTL)) return true;
        $result = $this->_delete($key);

        // удаляем из статического кеша
        unset($this->cacheData[$key], $this->cachedTS[$key]);
        $this->cacheDelete[$key] = true;
        $this->cachedDelTS[$key] = $time;

        return $result;
    }

    public function flushStaticCache()
    {
        $this->cacheData = $this->cachedTS = $this->cacheDelete = $this->cachedDelTS = [];
    }
}