<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_Thread extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Thread
{
     public function actionIndex()
    {
        $response = parent::actionIndex();

        /** @noinspection PhpUndefinedFieldInspection */
        if ($response instanceof XenForo_ControllerResponse_View &&
            isset($response->params['thread']['node_id']))
        {
            $ip = $this->_request->getClientIp(false);
            /** @var  SV_UserActivity_Model $userActivityModel */
            $userActivityModel = $this->getModelFromCache('SV_UserActivity_Model');
            $userActivityModel->trackViewerUsage('node', $response->params['thread']['node_id'], 'forum', $ip);
        }

        return $response;
    }

    protected $activityInjector = [
        'controller' => 'XenForo_ControllerPublic_Thread',
        'type'       => 'thread',
        'id'         => 'thread_id',
        'actions'    => ['index'],
        'activeKey'  => 'thread',
    ];
    use UserActivityInjector;
}
