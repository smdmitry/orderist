<?php

class BaseAuth extends \Phalcon\DI\Injectable
{
    private $_salt = 'Ibcj;cdfA~\cj3mU`k]>$]O4[qksMJl;*7';

    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance;}
    protected function __construct() {}

    public function authUser($user)
    {
        $year = 31536000;

        $authHash = $this->getAuthHash($user['id'], $user['email'], $user['password']);
        BaseService::i()->setCookie('uid', $user['id'], $year);
        BaseService::i()->setCookie('hw', $authHash, $year);

        BaseService::i()->setCSRFToken();

        return true;
    }

    public function getAuthUser()
    {
        $uid = BaseService::i()->getCookie('uid');
        $hw = BaseService::i()->getCookie('hw');

        if (!$uid || !$hw) {
            return false;
        }

        $user = UserDao::i()->getById($uid);
        if (!$user) {
            return false;
        }

        if ($hw == $this->getAuthHash($user['id'], $user['email'], $user['password'])) {
            return $user;
        }

        return false;
    }

    public function logout()
    {
        BaseService::i()->deleteCookie('hw');
    }

    private function getAuthHash($id, $email, $password)
    {
        return md5($this->_salt . '_' . $id . $email . $password);
    }
}