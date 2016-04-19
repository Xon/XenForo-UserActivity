<?php

class SV_UserActivity_CronEntry_ActivityGarbageCollect
{
    public static function run()
    {
        XenForo_Model::create("SV_UserActivity_Model")->GarbageCollectActivity();
    }
}