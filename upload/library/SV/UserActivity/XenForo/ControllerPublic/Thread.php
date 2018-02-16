<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserActivityInjector.php');
/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserCountActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_Thread extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Thread
{
     public function actionIndex()
    {
        $response = parent::actionIndex();

        $options = XenForo_Application::getOptions();
        /** @noinspection PhpUndefinedFieldInspection */
        if ($response instanceof XenForo_ControllerResponse_View &&
            isset($response->params['thread']['node_id']) &&
            !empty($options->svUAPopulateUsers['forum']))
        {
            $nodeTrackLimit = intval($options->svUAThreadNodeTrackLimit);
            /** @var  SV_UserActivity_Model $userActivityModel */
            $userActivityModel = $this->getModelFromCache('SV_UserActivity_Model');
            $ip = $this->_request->getClientIp(false);
            if ($nodeTrackLimit === 1 || count($response->params['nodeBreadCrumbs']) == 1)
            {
                $userActivityModel->trackViewerUsage('node', $response->params['thread']['node_id'], 'forum', $ip);
            }
            else if ($nodeTrackLimit !== 0)
            {
                $node = $response->params['forum'];
                /** @var XenForo_Model_Node $nodeModel */
                $nodeModel = $this->getModelFromCache('XenForo_Model_Node');
                $nodes = $nodeModel->getNodeAncestors($node);
                $nodes[$node['node_id']] = $node;

                $nodes = array_reverse($nodes);

                $count = $nodeTrackLimit < 0 ? PHP_INT_MAX : $nodeTrackLimit;
                foreach ($nodes AS $node)
                {
                    if ($node['node_type_id'] === 'Forum' )
                    {
                        $userActivityModel->trackViewerUsage('node', $node['node_id'], 'forum', $ip);
                        $count--;
                        if ($count <= 0)
                        {
                            break;
                        }
                    }
                }
            }
        }

        return $response;
    }


    protected function similarThreadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        $action,
        array $config)

    {
        if (empty($response->params['svSimilarThreads']))
        {
            return null;
        }
        $threadIds = [];
        foreach($response->params['svSimilarThreads'] as $content)
        {
            if ($content['content_type'] === 'thread')
            {
                $threadIds[] = $content['content_id'];
            }
        }

        return $threadIds;
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'similar-threads',
            'type'      => 'thread',
            'actions'   => ['index'],
            'fetcher'   => 'similarThreadFetcher'
        ],
    ];
    use UserCountActivityInjector;

    protected $activityInjector = [
        'controller' => 'XenForo_ControllerPublic_Thread',
        'type'       => 'thread',
        'id'         => 'thread_id',
        'actions'    => ['index'],
        'activeKey'  => 'thread',
    ];
    use UserActivityInjector;
}
