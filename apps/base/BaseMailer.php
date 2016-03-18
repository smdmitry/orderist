<?php

class BaseMailer extends \Phalcon\DI\Injectable
{
    const TYPE_SIGNUP = 1;
    const TYPE_RECOVER_PASSWORD = 2;

    protected $_mail;

    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance;}
    protected function __construct()
    {
        return false; // Todo: do

        require_once '../app/library/PHPMailer/PHPMailerAutoload.php';

        $mail = new PHPMailer();

        $config = $this->config;

        $mail->CharSet = 'utf-8';
        $mail->isSMTP();
        $mail->Host = $config->mail->smtp;
        $mail->SMTPAuth = true;
        $mail->Username = $config->mail->login;
        $mail->Password = $config->mail->password;
        $mail->SMTPSecure = 'tls';

        $mail->From = $config->mail->email;
        $mail->FromName = 'Заказист';

        $mail->WordWrap = 50;
        $mail->isHTML(true);

        $this->_mail = $mail;
    }

    protected function getTypes()
    {
        return array(
            self::TYPE_SIGNUP => array(
                'subject' => 'Регистрация',
                'tpl' => 'signup',
            ),
            self::TYPE_RECOVER_PASSWORD => array(
                'subject' => 'Восстановление пароля',
                'tpl' => 'recover_password',
            ),
        );
    }

    protected function getType($typeId)
    {
        $types = $this->getTypes();
        return isset($types[$typeId]) ? $types[$typeId] : false;
    }

    public function sendByType($user, $typeId, $data = array())
    {
        return false; // Todo: do

        $config = $this->getType($typeId);

        $view = new Phalcon\Mvc\View\Simple();
        $view->setViewsDir('../app/views/');

        if (!empty($user['email'])) {
            $this->_mail->addAddress($user['email']);
            $view->user = $user;
        } else if (is_string($user)) {
            $this->_mail->addAddress($user);
        } else {
            return false;
        }

        $view->setVars($data);

        $html = $view->render('mail/' . $config['tpl']);

        $this->_mail->Subject = $config['subject'];
        $this->_mail->msgHTML($html);

        //$res = $this->_mail->send();
        //return $res;

        $mail = $this->_mail;
        BackgroundWorker::i()->addJob(function() use ($mail) {
            $mail->send();
        });

        return true;
    }
}