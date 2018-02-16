<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserActivityInjector.php');
class SV_UserActivity_NixFifty_Calendar_ControllerPublic_Calendar extends XFCP_SV_UserActivity_NixFifty_Calendar_ControllerPublic_Calendar
{
    protected $activityInjector = [
        'controller' => 'NixFifty_Calendar_ControllerPublic_Calendar',
        'type'       => 'event',
        'id'         => 'event_id',
        'actions'    => ['view'],
        'activeKey'  => 'nf_calendar',
    ];
    use UserActivityInjector;
}
