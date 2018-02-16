<?php

class SV_UserActivity_Listener
{
    public static function load_class($class, array &$extend)
    {
        $extend[] = 'SV_UserActivity_' . $class;
    }

    public static $viewCounts = null;

    public static function templateCreate(&$templateName, array &$params, XenForo_Template_Abstract $template)
    {
        if (self::$viewCounts && !$template->getParam('UA_UsersViewingCount'))
        {
            $template->setParam('UA_UsersViewingCount', self::$viewCounts);
        }
    }
}
