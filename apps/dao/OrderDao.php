<?php

class OrderDao extends BaseDao
{
    const TABLE_ORDERS = 'orders';

    const STATE_NEW = 1;
    const STATE_EXECUTED = 2;
    const STATE_DELETED = 3;
    const FAKE_STATE_EXECUTER = 3;

    const COMMISSION = 0.10;
    const CTHRESHOLD = 0.2;

    const CACHE_ORDERS_LIMIT = IndexController::ORDERS_PER_PAGE;
    const NEW_ORDERS_MCKEY = 'orders_new';
    const NEW_ORDERS_MCTIME = 60;

    const EXECUTER_ORDERS_MCKEY = 'executed_orders:';
    const USER_ORDERS_MCKEY = 'user_orders:';
    const USER_ORDERS_MCTIME = BaseService::TIME_HOUR;

    public function getById($id)
    {
        $ids = $this->getByIds([$id]);
        return reset($ids);
    }

    public function getByIds($ids)
    {
        if (empty($ids)) {
            return [];
        }

        foreach ($ids as &$id) {
            $id = (int)$id;
        } unset($id);

        $select = $this->db->select()->from(self::TABLE_ORDERS)->where('id IN (?)', [$ids]);
        return $this->db->fetchAssoc($select);
    }

    public function prepareOrders($orders)
    {
        if (array_key_exists('id', $orders)) {
            $orders = [$orders];
        }

        $userIds = [];
        foreach ($orders as $k => $record) {
            $userIds[$record['user_id']] = $record['user_id'];
            if ($record['state'] == self::STATE_EXECUTED) {
                $userIds[$record['executer_id']] = $record['executer_id'];
            }
        }

        $users = UserDao::i()->getByIds($userIds);

        foreach ($orders as $k => $record) {
            $orders[$k]['user'] = $users[$record['user_id']];
            $orders[$k]['description'] = str_replace(["\r", "\n"], ['', '<br>'], $orders[$k]['description']);

            if (!empty($record['executer_id']) && !empty($users[$record['executer_id']])) {
                $orders[$k]['executer'] = $users[$record['executer_id']];
            }
        }

        return $orders;
    }

    public function getOrders($filter, $sort, $limit = 10, $offset = 0, $sortId = 0)
    {
        if (!$sortId && !$offset && $limit <= self::CACHE_ORDERS_LIMIT) {
            if (!empty($filter['state']) && empty($filter['user_id']) && empty($filter['executer_id']) && $filter['state'] == self::STATE_NEW) {
                $mckey = self::NEW_ORDERS_MCKEY;
                $mctime = self::NEW_ORDERS_MCTIME;
            } else if (!empty($filter['user_id'])) {
                $state = !empty($filter['state']) ? $filter['state'] : 0;
                $mckey = self::USER_ORDERS_MCKEY . $state . '_' . $filter['user_id'];
                $mctime = self::USER_ORDERS_MCTIME;
            } else if (!empty($filter['executer_id']) && !empty($filter['state']) && $filter['state'] == self::STATE_EXECUTED) {
                $mckey = self::EXECUTER_ORDERS_MCKEY . $filter['executer_id'];
                $mctime = self::USER_ORDERS_MCTIME;
            }

            if (!empty($mckey) && !empty($mctime)) {
                $data = BaseMemcache::i()->get($mckey);
                if ($data === false) {
                    $data = $this->_getOrders($filter, $sort, self::CACHE_ORDERS_LIMIT);
                    BaseMemcache::i()->set($mckey, $data, $mctime);
                }
                return array_slice($data, 0, $limit, true);
            }
        }

        return $this->_getOrders($filter, $sort, $limit, $offset, $sortId);
    }
    protected function _getOrders($filter, $sort, $limit = 10, $offset = 0, $sortId = 0)
    {
        $limit = (int)$limit;

        $select = $this->db->select()->from(self::TABLE_ORDERS)->order($sort[0] . ' ' . $sort[1])->limit($limit, $offset);
        if (isset($filter['user_id'])) {
            $select->where('user_id = ?', (int)$filter['user_id']);
        }
        if (isset($filter['executer_id'])) {
            $select->where('executer_id = ?', (int)$filter['executer_id']);
        }
        if (isset($filter['state'])) {
            $select->where('state = ?', $filter['state']);
        }

        if ($sortId) {
            $type = $sort[0] == 'executed' ? ' <= ' : ' < ';
            $select->where($sort[0] . $type . '?', $sortId);
        }

        return $this->db->fetchAssoc($select);
    }

    public function addOrder($userId, $title, $description, $price, $commission)
    {
        $res = $this->db->insert(self::TABLE_ORDERS, [
            'user_id' => $userId,
            'state' => self::STATE_NEW,
            'price' => $price,
            'commission' => $commission,
            'title' => $title,
            'description' => $description,
            'inserted' => time(),
            'updated' => time(),
        ]);
        if ($res) {
            $this->clearNewOrdersCache();
            $this->clearUserOrdersCache($userId, self::STATE_NEW);
        }
        return $res ? $this->db->lastInsertId() : false;
    }

    public function execute($order, $userId)
    {
        $orderId = (int)$order['id'];
        $state = self::STATE_NEW;

        $res = $this->db->update(self::TABLE_ORDERS, [
            'state' => self::STATE_EXECUTED,
            'executer_id' => $userId,
            'user_payment_id' => 0,
            'executer_payment_id' => 0,
            'updated' => time(),
            'executed' => time(),
        ], $this->db->qq("id = ? AND state = ?", [$orderId, $state]));

        if ($res) {
            $this->clearNewOrdersCache($orderId);
            $this->clearUserOrdersCache($order['user_id']);
            $this->clearExecuterOrdersCache($userId);
        }

        return $res;
    }

    public function _updateRaw($orderId, $data)
    {
        return $this->db->update(self::TABLE_ORDERS, $data, $this->db->qq('id = ?', $orderId));
    }

    public function delete($order)
    {
        $orderId = (int)$order['id'];
        $state = self::STATE_NEW;

        $res = $this->db->update(self::TABLE_ORDERS, [
            'state' => self::STATE_DELETED,
            'updated' => time(),
        ], $this->db->qq("id = ? AND state = ?", [$orderId, $state]));

        if ($res) {
            $this->clearNewOrdersCache($orderId);
            $this->clearUserOrdersCache($order['user_id'], $state);
        }

        return $res;
    }

    public function needOrderSocketUpdate($order)
    {
        $minOrderId = BaseMemcache::i()->get('order_new_min');
        if ($minOrderId === false) {
            $select = $this->db->select()->from(self::TABLE_ORDERS, 'MIN(id) as id')->where('state = ?', self::STATE_NEW)->order('id DESC')->limit(1000);
            $minOrderId = (int)$this->db->fetchOne($select);
            BaseMemcache::i()->set('order_new_min', $minOrderId, 5*60);
        }
        return $order['id'] >= $minOrderId;
    }

    private function clearNewOrdersCache($orderId = 0)
    {
        if (!$orderId) {
            return BaseMemcache::i()->delete(self::NEW_ORDERS_MCKEY);
        }

        $data = BaseMemcache::i()->get(self::NEW_ORDERS_MCKEY);
        if (!empty($data[$orderId])) {
            return $this->clearNewOrdersCache();
        }

        return false;
    }

    private function clearUserOrdersCache($userId, $state = 0)
    {
        $res = BaseMemcache::i()->delete(self::USER_ORDERS_MCKEY . '0_' . $userId);

        if ($state) {
            return BaseMemcache::i()->delete(self::USER_ORDERS_MCKEY . $state . '_' . $userId);
        }


        BaseMemcache::i()->delete(self::USER_ORDERS_MCKEY . self::STATE_NEW . '_' . $userId);
        BaseMemcache::i()->delete(self::USER_ORDERS_MCKEY . self::STATE_EXECUTED . '_' . $userId);

        return $res;
    }

    private function clearExecuterOrdersCache($userId)
    {
        return BaseMemcache::i()->delete(self::EXECUTER_ORDERS_MCKEY . $userId);
    }
}
