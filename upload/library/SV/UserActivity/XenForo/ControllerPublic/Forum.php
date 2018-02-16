<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserActivityInjector.php');
/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserCountActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_Forum extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Forum
{
    public function actionForum()
    {
        $response = parent::actionForum();
        // alias forum => node, limitations of activity tracking
        if ($response instanceof XenForo_ControllerResponse_View &&
            isset($response->params['forum']) && !isset($response->params['node']))
        {
            $response->params['node'] = $response->params['forum'];
        }
        return $response;
    }

    protected function forumFetcher(/** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        array $config)
    {
        if (empty($response->params['forum']['node_id']))
        {
            return null;
        }

        return [$response->params['forum']['node_id']];
    }

    protected function subForumFetcher(/** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        array $config)

    {
        if (empty($response->params['nodeList']))
        {
            return null;
        }

        return array_keys($response->params['nodeList']['nodeParents']);
    }

    protected function threadFetcher(/** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        array $config)

    {
        if (empty($response->params['threads']))
        {
            return null;
        }

        return array_keys($response->params['threads']);
    }

    protected function stickyThreadFetcher(/** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        array $config)

    {
        if (empty($response->params['stickyThreads']))
        {
            return null;
        }

        return array_keys($response->params['stickyThreads']);
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'forum',
            'type'      => 'node',
            'actions'   => ['list', 'forum'],
            'fetcher'   => 'forumFetcher',
        ],
        [
            'activeKey' => 'sub-forum',
            'type'      => 'node',
            'actions'   => ['list', 'forum'],
            'fetcher'   => 'subForumFetcher',
        ],
        [
            'activeKey' => 'thread',
            'type'      => 'thread',
            'actions'   => ['list', 'forum'],
            'fetcher'   => 'threadFetcher'
        ],
        [
            'activeKey' => 'sticky-thread',
            'type'      => 'thread',
            'actions'   => ['list', 'forum'],
            'fetcher'   => 'stickyThreadFetcher'
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
