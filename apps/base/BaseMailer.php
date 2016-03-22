<?php

class BaseMailer extends \Phalcon\DI\Injectable
{
    const TYPE_SIGNUP = 1;
    const TYPE_RECOVER_PASSWORD = 2;

    protected $_mail;

    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance;}
    protected function __construct()
    {
        require_once '../apps/lib/PHPMailer/PHPMailerAutoload.php';

        $mail = new PHPMailer();

        $config = $this->config['mail'];

        $mail->CharSet = 'utf-8';
        $mail->isSMTP();
        $mail->Host = $config['smtp'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['login'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = 'tls';

        $mail->From = $config['email'];
        $mail->FromName = 'Заказист';

        $mail->WordWrap = 50;
        $mail->isHTML(true);

        $this->_mail = $mail;
    }

    protected function getTypes()
    {
        return array(
            self::TYPE_SIGNUP => [
                'subject' => 'Регистрация',
                'tpl' => 'signup',
            ],
            self::TYPE_RECOVER_PASSWORD => [
                'subject' => 'Восстановление пароля',
                'tpl' => 'recover_password',
            ],
        );
    }

    protected function getType($typeId)
    {
        $types = $this->getTypes();
        return isset($types[$typeId]) ? $types[$typeId] : false;
    }

    public function sendByType($user, $typeId, $data = [])
    {
        $config = $this->getType($typeId);

        $view = new Phalcon\Mvc\View\Simple();
        $view->setViewsDir('../apps/views/');

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
        $mail = $this->_mail;

        BackgroundWorker::i()->addJob(function() use ($mail) {
            $mail->send();
        });

        return true;
    }
}