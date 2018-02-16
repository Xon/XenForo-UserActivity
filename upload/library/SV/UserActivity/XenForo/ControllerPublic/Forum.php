<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserActivityInjector.php');
/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserCountActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_Forum extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Forum
{
    protected function forumFetcher(XenForo_ControllerResponse_View $response)
    {
        if (empty($response->params['forum']['node_id']))
        {
            return null;
        }
        return [$response->params['forum']['node_id']];
    }

    protected function subForumFetcher(XenForo_ControllerResponse_View $response)
    {
        if (empty($response->params['nodeList']))
        {
            return null;
        }
        return array_keys($response->params['nodeList']['nodeParents']);
    }

    protected function threadFetcher(XenForo_ControllerResponse_View $response)
    {
        if (empty($response->params['threads']))
        {
            return null;
        }
        return array_keys($response->params['threads']);
    }

    protected function stickyThreadFetcher(XenForo_ControllerResponse_View $response)
    {
        if (empty($response->params['stickyThreads']))
        {
            return null;
        }
        return array_keys($response->params['stickyThreads']);
    }

    protected $countActivityInjector = [
        'forum'  => [
            'type'    => 'node',
            'actions' => ['list', 'forum'],
            'fetcher' => 'forumFetcher',
        ],
        'sub-forum' => [
            'type'    => 'node',
            'actions' => ['list', 'forum'],
            'fetcher' => 'subForumFetcher',
        ],
        'thread' => [
            'type'    => 'thread',
            'actions' => ['list', 'forum'],
            'fetcher' => 'threadFetcher'
        ],
        'sticky-thread' => [
            'type'    => 'thread',
            'actions' => ['list', 'forum'],
            'fetcher' => 'stickyThreadFetcher'
        ],
    ];
    use UserCountActivityInjector;

    protected $activityInjector = [
        'controller' => 'XenForo_ControllerPublic_Forum',
        'type'       => 'node',
        'id'         => 'node_id',
        'actions'    => ['index', 'forum'],
        'activeKey'  => 'forum',
    ];
    use UserActivityInjector;
}
