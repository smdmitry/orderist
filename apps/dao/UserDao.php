<?php

class UserDao extends BaseDao
{
    const TABLE_USER = 'order_users';
    const TABLE_PAYMENTS = 'order_payments';

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

        $select = "SELECT * FROM ". self::TABLE_USER ." WHERE id IN (". $this->implodeBind($ids) .");";
        return $this->db->fetchAll($select, Phalcon\Db::FETCH_ASSOC);
    }

    public function getByEmailPassword($email, $password)
    {
        $user = $this->getByEmail($email);

        if (!$user) {
            return false;
        }

        return password_verify($password, $user['password']) ? $user : false;
    }

    public function getByEmail($email)
    {
        $select = "SELECT * FROM ". self::TABLE_USER ." WHERE email = ?;";
        return $this->db->fetchOne($select, Phalcon\Db::FETCH_ASSOC, [$email]);
    }

    public function getField($USER, $field)
    {
        if ($field == 'cash') {
            return number_format(($USER['cash'] - $USER['hold']) / 100, 2, '.', ' ');
        } else if ($field == 'hold') {
            return number_format($USER['hold'] / 100, 2, '.', ' ');
        }
        return $USER[$field];
    }

    private function _addUser($name, $email, $phash)
    {
        $res = $this->db->insertAsDict(self::TABLE_USER, [
            'name' => $name,
            'email' => $email,
            'password' => $phash,
            'inserted' => time(),
        ]);
        return $res ? $this->db->lastInsertId() : false;
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
        $res = $this->db->updateAsDict(self::TABLE_USER, $bind, "id = {$userId}");
        return $res;
    }

    public function addMoney($userId, $amount, $hold = 0, $orderId = 0)
    {
        $res = $this->db->insertAsDict(self::TABLE_PAYMENTS, [
            'user_id' => $userId,
            'amount' => $amount,
            'order_id' => $orderId,
            'inserted' => time(),
        ]);
        $paymentId = $res ? $this->db->lastInsertId() : 0;

        if (!$paymentId) {
            return false;
        }

        $table = self::TABLE_USER;
        $query = "UPDATE {$table} SET cash = cash + {$amount}, hold = hold + {$hold} WHERE id = $userId;";
        $res = $this->db->query($query);

        return $res ? $paymentId : false;
    }

    public function lock($userId)
    {
        return BaseMemcache::i()->add('userCashLock:' . $userId, 1, 10);
    }

    public function unlock($userId)
    {
        return BaseMemcache::i()->delete('userCashLock:' . $userId);
    }
}