<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserActivityInjector.php');
class SV_UserActivity_NixFifty_Tickets_ControllerPublic_Ticket extends XFCP_SV_UserActivity_NixFifty_Tickets_ControllerPublic_Ticket
{
    protected $activityInjector = [
        'controller' => 'NixFifty_Tickets_ControllerPublic_Ticket',
        'type'       => 'ticket',
        'id'         => 'ticket_id',
        'actions'    => ['view'],
        'activeKey'  => 'nf_ticket',
    ];
    use UserActivityInjector;
}
