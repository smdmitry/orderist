<?php

use Phalcon\Tag;

class BaseTag extends Tag
{
    public static function path($file)
    {
        return '//' . \Phalcon\DI::getDefault()->getConfig()['static'] . '/v10' . $file;
    }
}