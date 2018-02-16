<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserActivityInjector.php');
/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserCountActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_Conversation extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Conversation
{
    protected function conversationFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        $action,
        array $config)

    {
        if (empty($response->params['conversations']))
        {
            return null;
        }

        return array_keys($response->params['conversations']);
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'conversation',
            'type'      => 'conversation',
            'actions'   => ['index', 'starred', 'yours', 'starred'],
            'fetcher'   => 'conversationFetcher'
        ],
    ];
    use UserCountActivityInjector;

    protected $activityInjector = [
        'controller' => 'XenForo_ControllerPublic_Conversation',
        'type'       => 'conversation',
        'id'         => 'conversation_id',
        'actions'    => ['view'],
        'activeKey'  => 'conversation',
    ];
    use UserActivityInjector;
}
