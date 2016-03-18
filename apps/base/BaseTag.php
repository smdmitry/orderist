<?php

use Phalcon\Tag;

class BaseTag extends Tag
{
    public static function path($file)
    {
        return '//orderist.smd.im' . $file . '?v=2';
    }
}