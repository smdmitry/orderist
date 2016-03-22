<?php

class BaseDao
{
    /**
     * @var DbDriverBase
     */
    public $db;

    public static function i() {static $i; $i = new static(); return $i;}

    private function __construct()
    {
        $this->db = \Phalcon\DI::getDefault()->getDb();
    }

    public function getDataByIdsWithMemcache($dataIds, $dbGetFunction, $mckey, $mctime, $defaultValue = 0)
    {
        $dataIds = is_array($dataIds) ? $dataIds : [$dataIds];

        $keys = [];
        foreach ($dataIds as $dataId) {
            $keys[$dataId] = $mckey . $dataId;
        }

        $cachedRaw = BaseMemcache::i()->get($keys);
        $cached = [];

        $prefixLen = strlen($mckey);
        foreach ($cachedRaw as $key => $value) {
            if ($value === false) {
                unset($cachedRaw[$key]);
            } else {
                $key = substr($key, $prefixLen);
                $cached[$key] = $value;
            }
        }

        $keysForDb = array_diff_key($keys, $cached);
        foreach ($keysForDb as $key => &$record) {
            $record = $key;
        } unset($record);

        if (count($keysForDb) > 0) {
            $dataFromDb = call_user_func($dbGetFunction, $keysForDb);
        } else {
            $dataFromDb = [];
        }

        foreach ($dataFromDb as $id => $row) {
            BaseMemcache::i()->add($mckey . $id, $row, $mctime);
        }

        $keysNotFoundInDb = array_diff_key($keysForDb, $dataFromDb);
        foreach ($keysNotFoundInDb as $id => $row) {
            BaseMemcache::i()->add($mckey . $id, $defaultValue, $mctime);
            $dataFromDb[$id] = $defaultValue;
        }

        $allData = $cached + $dataFromDb;

        $result = [];
        foreach ($dataIds as $dataId) {
            if (!isset($allData[$dataId])) {
                trigger_error("Index $dataId not exists.");
            }
            $result[$dataId] = $allData[$dataId];
        }

        return $result;
    }
}
