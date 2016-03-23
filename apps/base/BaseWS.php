<?php

class BaseWS extends \Phalcon\DI\Injectable
{
    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance;}
    protected function __construct() {}

    public function send($userId, $data)
    {
        return BackgroundWorker::i()->addJob(function() use ($userId, $data) {
            $message = json_encode($data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://localhost:8080/emit/{$userId}/secret/" . urldecode($message));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $content = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        });
    }
}