<?php

include_once('SV/UserActivity/ActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_Report extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Report
{
    protected $activityInjector = [
        'controller' => 'XenForo_ControllerPublic_Report',
        'type'       => 'report',
        'id'         => 'report_id',
        'actions'    => ['view'],
    ];
    use ActivityInjector;
}
