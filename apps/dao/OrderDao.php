<?php

class OrderDao extends BaseDao
{
    const TABLE_ORDERS = 'order_orders';

    const STATE_NEW = 1;
    const STATE_EXECUTED = 2;

    const COMMISSION = 0.18;

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
        }

        return $orders;
    }

    public function getOrders($state, $limit = 10, $lastOrderId = 0)
    {
        return $this->_getOrders($state, 0, $limit, $lastOrderId);
    }

    public function getUserOrders($userId, $state, $limit = 10, $lastOrderId = 0)
    {
        return $this->_getOrders($state, $userId, $limit, $lastOrderId);
    }

    protected function _getOrders($state, $userId = 0, $limit = 10, $lastOrderId = 0)
    {
        $limit = (int)$limit;
        $state = (int)$state;

        $select = $this->db->select()->from(self::TABLE_ORDERS)->order('id DESC')->limit($limit);
        if ($state) {
            $select->where('state = ?', $state);
        }
        if ($userId) {
            $select->where('user_id = ?', $userId);
        }
        if ($lastOrderId) {
            $select->where('id < ?', $lastOrderId);
        }

        return $this->db->fetchAssoc($select);
    }

    public function addOrder($userId, $title, $description, $price)
    {
        $res = $this->db->insert(self::TABLE_ORDERS, [
            'user_id' => $userId,
            'state' => self::STATE_NEW,
            'price' => $price,
            'title' => $title,
            'description' => $description,
            'inserted' => time(),
        ]);
        return $res ? $this->db->lastInsertId() : false;
    }

    public function execute($orderId, $userId)
    {
        $orderId = (int)$orderId;
        $state = self::STATE_NEW;

        $res = $this->db->update(self::TABLE_ORDERS, [
            'state' => self::STATE_EXECUTED,
            'executer_id' => $userId,
        ], $this->db->qq("id = ? AND state = ?", [$orderId, $state]));

        return $res;
    }
}
