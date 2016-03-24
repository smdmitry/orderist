<?php

class UserDao extends BaseDao
{
    const TABLE_USER = 'users';
    const TABLE_PAYMENTS = 'payments';
    const TABLE_PAYMENTS_SHARDS = 4;

    const MC_KEY = 'user:';
    const MC_TIME = BaseService::TIME_DAY;

    const NEW_PAYMENTS_MCKEY = 'payments:';
    const NEW_PAYMENTS_MCTIME = BaseService::TIME_DAY;
    const NEW_PAYMENTS_LIMIT = UserController::PAYMENTS_PER_PAGE;

    private function getPaymentsTable($userId)
    {
        $shardId = $userId % self::TABLE_PAYMENTS_SHARDS;
        return self::TABLE_PAYMENTS . '_' . $shardId;
    }

    public function getById($id, $skipcache = 0)
    {
        if ($skipcache >= 2) {
            return reset($this->getUsersFromDb([$id]));
        } else if ($skipcache == 1) {
            BaseMemcache::i()->flushStaticCache(self::MC_KEY.$id);
        }

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
        $email = mb_strtolower($email);
        $select = $this->db->select()->from(self::TABLE_USER)->where('email_hash = ?', crc32($email))->where('email = ?', $email);
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
        $email = mb_strtolower($email);

        $res = $this->db->insert(self::TABLE_USER, [
            'name' => $name,
            'email_hash' => crc32($email),
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

    public function updateMoney($userId, $amount, $hold = 0, $orderId = 0)
    {
        $userId = (int)$userId;
        $amount = (int)$amount;
        $hold = (int)$hold;
        $orderId = (int)$orderId;

        if ($amount != 0) {
            $res = $this->db->insert($this->getPaymentsTable($userId), [
                'user_id' => $userId,
                'amount' => $amount,
                'order_id' => $orderId,
                'inserted' => time(),
            ]);
            $paymentId = $res ? $this->db->lastInsertId() : 0;

            if (!$paymentId) {
                return false;
            }

            $this->clearPaymentsCache($userId);
        } else {
            $paymentId = true;
        }

        $update = [
            'cash' => $this->db->expr('cash + ?', $amount),
            'hold' => $this->db->expr('IF(hold + ? <= 0, 0, hold + ?)', [$hold, $hold]),
            'updated' => time(),
        ];
        if ($paymentId !== true && $paymentId) {
            $update['payment_id'] = $paymentId;
        }

        $res = $this->_update($userId, $update);

        BaseWS::i()->send($userId, ['type' => 'cash']);

        return $res ? $paymentId : false;
    }

    public function _update($userId, $update)
    {
        $res = $this->db->update(self::TABLE_USER, $update, $this->db->qq("id = ?", $userId));
        if ($res) {
            $user = $this->getById($userId, 2);
            if ($user) {
                BaseMemcache::i()->set(self::MC_KEY.$userId, $user, self::MC_TIME);
            } else {
                $this->clearCache($userId);
            }
        }
        return $res;
    }

    public function getUserPayments($userId, $limit, $lastPaymentId = 0)
    {
        if ($lastPaymentId == 0 && $limit <= self::NEW_PAYMENTS_LIMIT) {
            $data = BaseMemcache::i()->get(self::NEW_PAYMENTS_MCKEY . $userId);
            if ($data === false) {
                $data = $this->getUserPaymentsFromDb($userId, self::NEW_PAYMENTS_LIMIT);
                BaseMemcache::i()->set(self::NEW_PAYMENTS_MCKEY . $userId, $data, self::NEW_PAYMENTS_MCTIME);
            }
            return array_slice($data, 0, $limit, true);
        }

        return $this->getUserPaymentsFromDb($userId, $limit, $lastPaymentId);
    }

    private function clearPaymentsCache($userId)
    {
        return BaseMemcache::i()->delete(self::NEW_PAYMENTS_MCKEY . $userId);
    }

    private function getUserPaymentsFromDb($userId, $limit, $lastPaymentId = 0)
    {
        $userId = (int)$userId;
        $limit = (int)$limit;
        $lastPaymentId = (int)$lastPaymentId;

        $select = $this->db->select()->from($this->getPaymentsTable($userId))->order('id DESC')->limit($limit);
        if ($userId) {
            $select->where('user_id = ?', $userId);
        }

        if ($lastPaymentId) {
            $select->where('id < ?', $lastPaymentId);
        }

        return $this->db->fetchAssoc($select);
    }

    public function getUserUpdateByPayments($userId)
    {
        $table = $this->getPaymentsTable($userId);
        $select = $this->db->qq("SELECT SUM(amount) as cash, MAX(id) as payment_id FROM {$table} WHERE user_id = ?;", $userId);
        return $this->db->fetchRow($select);
    }

    public function getUserOrderPayment($userId, $orderId)
    {
        $table = $this->getPaymentsTable($userId);
        $select = $this->db->select()->from($table)->where('user_id = ?', $userId)->where('order_id = ?', $orderId);
        return $this->db->fetchRow($select);
    }
}