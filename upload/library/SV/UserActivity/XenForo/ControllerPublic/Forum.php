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
            isset($response->params['forum']))
        {
            if (!isset($response->params['node']))
            {
                $response->params['node'] = $response->params['forum'];
            }
            $options = XenForo_Application::getOptions();
            if (empty($options->svUAPopulateUsers['forum']))
            {
                return $response;
            }

            $nodeTrackLimit = intval($options->svUAThreadNodeTrackLimit);
            $nodeTrackLimit = $nodeTrackLimit < 0 ? PHP_INT_MAX : $nodeTrackLimit;
            /** @var  SV_UserActivity_Model $userActivityModel */
            $userActivityModel = $this->getModelFromCache('SV_UserActivity_Model');

            $node = $response->params['forum'];
            if ($nodeTrackLimit > 0)
            {
                /** @var XenForo_Model_Node $nodeModel */
                $nodeModel = $this->getModelFromCache('XenForo_Model_Node');
                $nodes = $nodeModel->getNodeAncestors($node);
                if (!$nodes)
                {
                    return $response;
                }
                $nodes = array_reverse($nodes);

                $count = 1;
                foreach ($nodes AS $node)
                {
                    if ($node['node_type_id'] === 'Forum')
                    {
                        $userActivityModel->bufferTrackViewerUsage('node', $node['node_id'], 'forum');
                        $count++;
                        if ($count > $nodeTrackLimit)
                        {
                            break;
                        }
                    }
                }
            }
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
        if (empty($response->params['nodeList']['nodePermissions']) ||
            empty($response->params['nodeList']['nodeParents']))
        {
            return null;
        }
        $nodes = $response->params['nodeList']['nodeParents'];
        $permissions = $response->params['nodeList']['nodePermissions'];

        return $this->_getSVUserActivityModel()->getFilteredNodeIds($permissions, $nodes);
    }

    protected function threadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        $action,
        array $config)

    {
        return $this->_getSVUserActivityModel()->getFilteredThreadIds($response->params, 'threads');
    }

    protected function stickyThreadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        $action,
        array $config)

    {
        return $this->_getSVUserActivityModel()->getFilteredThreadIds($response->params, 'stickyThreads');
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

    /**
     * @return XenForo_Model|SV_UserActivity_Model
     */
    protected function _getSVUserActivityModel()
    {
        return $this->getModelFromCache('SV_UserActivity_Model');
    }
}
