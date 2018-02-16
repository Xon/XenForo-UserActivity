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

    protected function forumFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        $action,
        array $config)
    {
        if (empty($response->params['forum']['node_id']))
        {
            return null;
        }

        return [$response->params['forum']['node_id']];
    }

    protected function forumListFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        $action,
        array $config)

    {
        if (empty($response->params['nodeList']))
        {
            return null;
        }

        $nodeIds = [];
        $permissions = $response->params['nodeList']['nodePermissions'];
        foreach($response->params['nodeList']['nodeParents'] as $nodeId => $node)
        {
            $nodePermissions = $permissions[$nodeId];
            if (!empty($nodePermissions['viewOthers']) &&
                !empty($nodePermissions['viewContent']))
            {
                $nodeIds[] = $nodeId;
            }
        }


        return $nodeIds;
    }

    protected function threadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        $action,
        array $config)

    {
        return $this->getFilteredThreadIds($response, 'threads');
    }

    protected function stickyThreadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        $action,
        array $config)

    {
        return $this->getFilteredThreadIds($response, 'stickyThreads');
    }

    protected function getFilteredThreadIds(XenForo_ControllerResponse_View $response, $key)
    {
        if (empty($response->params[$key]) ||
            empty($response->params['nodeList']))
        {
            return null;
        }

        $permissions = $response->params['nodeList']['nodePermissions'];
        $threadIds = [];
        foreach($response->params[$key] as $thread)
        {
            $nodeId = $thread['node_id'];
            $nodePermissions = $permissions[$nodeId];
            if (!empty($nodePermissions['viewContent']))
            {
                $nodeIds[] = $nodeId;
            }
        }
        return $threadIds;
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'index-forum',
            'type'      => 'node',
            'actions'   => ['index'],
            'fetcher'   => 'forumListFetcher',
        ],
        [
            'activeKey' => 'forum',
            'type'      => 'node',
            'actions'   => ['forum'],
            'fetcher'   => 'forumFetcher',
        ],
        [
            'activeKey' => 'sub-forum',
            'type'      => 'node',
            'actions'   => ['forum'],
            'fetcher'   => 'forumListFetcher',
        ],
        [
            'activeKey' => 'thread',
            'type'      => 'thread',
            'actions'   => ['forum'],
            'fetcher'   => 'threadFetcher'
        ],
        [
            'activeKey' => 'sticky-thread',
            'type'      => 'thread',
            'actions'   => ['forum'],
            'fetcher'   => 'stickyThreadFetcher'
        ],
    ];
    use UserCountActivityInjector;

    protected $activityInjector = [
        'controller' => 'XenForo_ControllerPublic_Forum',
        'type'       => 'node',
        'id'         => 'node_id',
        'actions'    => ['forum'],
        'activeKey'  => 'forum',
    ];
    use UserActivityInjector;
}
