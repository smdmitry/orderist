<?php

class OrderDao extends BaseDao
{
    const TABLE_ORDERS = 'orders';

    const STATE_NEW = 1;
    const STATE_EXECUTED = 2;
    const STATE_DELETED = 3;
    const FAKE_STATE_IS_EXECUTED = 3;

    const COMMISSION = 0.10;

    const NEW_ORDERS_MCKEY = 'orders_new';
    const NEW_ORDERS_MCTIME = 60;
    const NEW_ORDERS_LIMIT = IndexController::ORDERS_PER_PAGE;

    const EXECUTED_ORDERS_MCKEY = 'executed_orders:';
    const USER_ORDERS_MCKEY = 'user_orders:';
    const USER_ORDERS_MCTIME = 3600;
    const USER_ORDERS_LIMIT = IndexController::ORDERS_PER_PAGE;

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

        $userIds = array_column($orders, 'user_id');
        $userIds = array_unique($userIds);
        $users = UserDao::i()->getByIds($userIds);

        foreach ($orders as $k => $record) {
            $orders[$k]['user'] = $users[$record['user_id']];
            $orders[$k]['description'] = str_replace(["\r", "\n"], ['', '<br>'], $orders[$k]['description']);
        }

        return $orders;
    }

    public function getNewOrders($limit = 10, $lastOrderId = 0)
    {
        $state = self::STATE_NEW;
        if ($lastOrderId == 0 && $limit <= self::NEW_ORDERS_LIMIT) {
            $data = BaseMemcache::i()->get(self::NEW_ORDERS_MCKEY);
            if ($data === false) {
                $data = $this->_getOrdersSortId($state, 0, self::NEW_ORDERS_LIMIT, $lastOrderId);
                BaseMemcache::i()->set(self::NEW_ORDERS_MCKEY, $data, self::NEW_ORDERS_MCTIME);
            }
            return array_slice($data, 0, $limit, true);
        }

        return $this->_getOrdersSortId($state, 0, $limit, $lastOrderId);
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

    public function getUserOrders($userId, $state, $limit = 10, $offset = 0, $lastOrderId = 0, $firstTime = 0)
    {
        if (!$lastOrderId && !$firstTime && $limit <= self::USER_ORDERS_LIMIT) {
            $data = BaseMemcache::i()->get(self::USER_ORDERS_MCKEY . $state . '_' . $userId);
            if ($data === false) {
                $data = $this->_getOrdersSortId($state, $userId, self::USER_ORDERS_LIMIT);
                BaseMemcache::i()->set(self::USER_ORDERS_MCKEY . $state . '_' . $userId, $data, self::USER_ORDERS_MCTIME);
            }
            return array_slice($data, 0, $limit, true);
        }

        return $this->_getOrdersSortId($state, $userId, $limit, $offset, $lastOrderId);
    }

    public function getExecuterOrders($userId, $limit = 10, $offset = 0, $lastOrderId = 0, $firstTime = 0)
    {
        if (!$lastOrderId && !$firstTime && $limit <= self::USER_ORDERS_LIMIT) {
            $data = BaseMemcache::i()->get(self::EXECUTED_ORDERS_MCKEY . $userId);
            if ($data === false) {
                $data = $this->_getExecuterOrders($userId, self::USER_ORDERS_LIMIT);
                BaseMemcache::i()->set(self::EXECUTED_ORDERS_MCKEY . $userId, $data, self::USER_ORDERS_MCTIME);
            }
            return array_slice($data, 0, $limit, true);
        }

        return $this->_getExecuterOrders($userId, $limit, $offset, $lastOrderId, $firstTime);
    }
    private function _getExecuterOrders($userId, $limit = 10, $offset = 0, $lastOrderId = 0, $firstTime = 0)
    {
        $limit = (int)$limit;

        $select = $this->db->select()->from(self::TABLE_ORDERS)->order('executed DESC')->limit($limit, $offset);
        $select->where('executer_id = ?', (int)$userId);

        if ($firstTime) {
            $select->where('executed <= ?', $firstTime);
        }

        return $this->db->fetchAssoc($select);
    }

    private function clearUserOrdersCache($userId, $state)
    {
        BaseMemcache::i()->delete(self::USER_ORDERS_MCKEY . '0_' . $userId);
        return BaseMemcache::i()->delete(self::USER_ORDERS_MCKEY . $state . '_' . $userId);
    }

    private function clearExecutedOrdersCache($userId)
    {
        return BaseMemcache::i()->delete(self::EXECUTED_ORDERS_MCKEY . $userId);
    }

    protected function _getOrdersSortId($state, $userId = 0, $limit = 10, $lastOrderId = 0)
    {
        $limit = (int)$limit;
        $state = (int)$state;

        $select = $this->db->select()->from(self::TABLE_ORDERS)->order('id DESC')->limit($limit);
        if ($state) {
            $select->where('state = ?', $state);
        }
        if ($userId) {
            $select->where('user_id = ?', (int)$userId);
        }
        if ($lastOrderId) {
            $select->where('id < ?', $lastOrderId);
        }

        return $this->db->fetchAssoc($select);
    }

    protected function _getOrdersSortExecuted($state, $userId = 0, $limit = 10, $offset = 0, $lastOrderId = 0)
    {
        $limit = (int)$limit;
        $state = (int)$state;

        $select = $this->db->select()->from(self::TABLE_ORDERS)->order('id DESC')->limit($limit);
        if ($state) {
            $select->where('state = ?', $state);
        }
        if ($userId) {
            $select->where('user_id = ?', (int)$userId);
        }
        if ($lastOrderId) {
            $select->where('id < ?', $lastOrderId);
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
        ], $this->db->qq("id = ? AND state = ?", [$orderId, $state]));

        if ($res) {
            $this->clearNewOrdersCache($orderId);
            $this->clearUserOrdersCache($order['user_id'], $state);
            $this->clearExecutedOrdersCache($userId);
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
}
