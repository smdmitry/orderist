<?php

class UserDao extends BaseDao
{
    const TABLE_USER = 'order_users';
    const TABLE_PAYMENTS = 'order_payments';

    const MC_KEY = 'user:';
    const MC_TIME = 86400; // 24 часа

    public function getById($id)
    {
        $ids = $this->getByIds([$id]);
        return reset($ids);
    }

    public function getByIds($userIds)
    {
        return $this->getDataByIdsWithMemcache($userIds, [$this, 'getUsersFromDb'], self::MC_KEY, self::MC_TIME, []);
    }

    public function getUsersFromDb($userIds)
    {
        foreach ($userIds as &$userId) {
            $userId = (int)$userId;
        } unset($userId);

        $select = $this->db->select()->from(self::TABLE_USER)->where('id IN (?)', [$userIds]);
        return $this->db->fetchAssoc($select);
    }

    private function clearCache($userId)
    {
        return BaseMemcache::i()->delete(self::MC_KEY.$userId);
    }

    public function getByEmail($email)
    {
        $select = $this->db->select()->from(self::TABLE_USER)->where('email = ?', $email);
        return $this->db->fetchRow($select);
    }

    public function getField($USER, $field)
    {
        if ($field == 'cash') {
            return BaseService::i()->formatMoney($USER['cash'] - $USER['hold']);
        } else if ($field == 'hold') {
            return BaseService::i()->formatMoney($USER['hold']);
        }
        return $USER[$field];
    }

    private function _addUser($name, $email, $phash)
    {
        $res = $this->db->insert(self::TABLE_USER, [
            'name' => $name,
            'email' => $email,
            'password' => $phash,
            'inserted' => time(),
            'updated' => time(),
        ]);

        if ($res) {
            $id = $this->db->lastInsertId();
            $this->clearCache($id);
            return $id;
        }

        return false;
    }

    public function addUser($name, $email, $password)
    {
        $password = $password ? $password : substr(md5(uniqid()), 0, 12);
        $password = password_hash($password, PASSWORD_BCRYPT);

        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors[] = 'Слишком короткое имя.';
        } else if (mb_strlen($name) > 80) {
            $errors[] = 'Слишком длинное имя.';
        }

        if (mb_strlen($password) < 5) {
            $errors[] = 'Слишком короткий пароль.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Вы ввели недопустимый Email.';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $user = $this->getByEmail($email);
        if (!empty($user)) {
            $errors[] = 'Пользователь с таким Email уже зарегисрирован.';
            return ['errors' => $errors];
        }

        return $this->_addUser($name, $email, $password);
    }

    protected function _update($userId, $bind)
    {
        $bind['updated'] = time();
        $res = $this->db->update(self::TABLE_USER, $bind, $this->db->qq("id = ?", $userId));

        if ($res) {
            $this->clearCache($userId);
        }

        return $res;
    }

    public function updateMoney($userId, $amount, $hold = 0, $orderId = 0)
    {
        $userId = (int)$userId;
        $amount = (int)$amount;
        $hold = (int)$hold;
        $orderId = (int)$orderId;

        if ($amount != 0) {
            $res = $this->db->insert(self::TABLE_PAYMENTS, [
                'user_id' => $userId,
                'amount' => $amount,
                'order_id' => $orderId,
                'inserted' => time(),
            ]);
            $paymentId = $res ? $this->db->lastInsertId() : 0;

            if (!$paymentId) {
                return false;
            }
        } else {
            $paymentId = true;
        }

        $update = [
            'cash' => $this->db->expr('cash + ?', $amount),
            'hold' => $this->db->expr('IF(hold + ? <= 0, 0, hold + ?)', [$hold, $hold]),
        ];
        if ($paymentId !== true && $paymentId) {
            $update['payment_id'] = $paymentId;
        }

        $res = $this->_update($userId, $update);

        if ($res) {
            BaseWS::i()->send($userId, ['type' => 'cash']);
        }

        return $res ? $paymentId : false;
    }

    public function getUserPayments($userId, $limit, $lastPaymentId = 0)
    {
        $userId = (int)$userId;
        $limit = (int)$limit;
        $lastPaymentId = (int)$lastPaymentId;

        $select = $this->db->select()->from(self::TABLE_PAYMENTS)->order('id DESC')->limit($limit);
        if ($userId) {
            $select->where('user_id = ?', $userId);
        }

        if ($lastPaymentId) {
            $select->where('id < ?', $lastPaymentId);
        }

        return $this->db->fetchAssoc($select);
    }
}