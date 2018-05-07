<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserActivityInjector.php');
class SV_UserActivity_NFLJ_AMS_ControllerPublic_Article extends XFCP_SV_UserActivity_NFLJ_AMS_ControllerPublic_Article
{
    protected $activityInjector = [
        'controller' => 'SV_UserActivity_NFLJ_AMS_ControllerPublic_Article',
        'type'       => 'article',
        'id'         => 'article_id',
        'actions'    => ['view'],
        'activeKey'  => 'nflj_ams',
    ];
    use UserActivityInjector;
}