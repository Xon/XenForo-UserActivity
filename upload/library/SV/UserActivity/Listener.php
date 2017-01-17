<?php

class SV_UserActivity_Listener
{
    public static function load_class($class, array &$extend)
    {
        $extend[] = 'SV_UserActivity_'.$class;
    }
}