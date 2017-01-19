<?php

class SV_UserActivity_CronEntry_ActivityGarbageCollect
{
    public static function run()
    {
        XenForo_Application::defer('SV_UserActivity_Deferred_ActivityGarbageCollect', array());
    }
}