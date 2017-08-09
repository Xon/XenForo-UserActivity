<?php

include_once('SV/UserActivity/ActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_Thread extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Thread
{
    protected $activityInjector = [
        'controller' => 'XenForo_ControllerPublic_Thread',
        'type' => 'thread',
        'id' => 'thread_id',
        'actions' => ['index'],
    ];
    use ActivityInjector;
}