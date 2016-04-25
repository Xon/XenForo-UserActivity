<?php

class RainDD_UserActivity_Install
{
    public static function installer($installedAddon)
    {
        if (XenForo_Application::$versionId < 1020031)
        {
            // note: this can't be phrased
            throw new XenForo_Exception('This add-on requires XenForo 1.2.0 Beta 1 or higher.', true);
        }
    }
}