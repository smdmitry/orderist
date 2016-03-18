<?php

class OrderDao extends BaseDao
{
    const STATE_NEW = 1;
    const STATE_EXECUTED = 2;

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

        $params = [];

        $select = "SELECT * FROM order_orders WHERE 1=1";

        if ($state) {
            $select .= " AND state = ?";
            $params[] = $state;
        }

        if ($userId) {
            $select .= " AND user_id = ?";
            $params[] = $userId;
        }

        if ($lastOrderId) {
            $select .= " AND id < ?";
            $params[] = $lastOrderId;
        }

        $select .= " ORDER BY id DESC LIMIT {$limit};";

        return $this->db->fetchAll($select, Phalcon\Db::FETCH_ASSOC, $params);
    }
}
