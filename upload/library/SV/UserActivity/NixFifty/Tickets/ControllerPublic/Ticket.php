<?php

include_once('SV/UserActivity/ActivityInjector.php');
class SV_UserActivity_NixFifty_Tickets_ControllerPublic_Ticket extends XFCP_SV_UserActivity_NixFifty_Tickets_ControllerPublic_Ticket
{
    protected $activityInjector = [
        'controller' => 'NixFifty_Tickets_ControllerPublic_Ticket',
        'type'       => 'ticket',
        'id'         => 'ticket_id',
        'actions'    => ['view'],
    ];
    use ActivityInjector;
}
