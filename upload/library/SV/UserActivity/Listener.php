<?php

class SV_UserActivity_Listener
{
    const AddonNameSpace = 'SV_UserActivity_';

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }
}