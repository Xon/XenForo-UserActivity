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
            XenForo_Application::getOptions()->svUATrackForum &&
            isset($response->params['thread']['node_id']) &&
            XenForo_Application::isRegistered('session'))
        {
            $nodeId = $response->params['thread']['node_id'];

            /** @var  SV_UserActivity_Model $userActivityModel */
            $userActivityModel = $this->getModelFromCache('SV_UserActivity_Model');
            if ($nodeId && $userActivityModel->isLogging())
            {
                $session = XenForo_Application::getSession();
                $robotKey = $session->isRegistered('robotId') ? $session->get('robotId') : '';
                $ip = $this->_request->getClientIp(false);

                if (SV_UserActivity_XenForo_Model_User::$SV_UA_TrackRobots === null)
                {
                    SV_UserActivity_XenForo_Model_User::$SV_UA_TrackRobots = XenForo_Application::getOptions()->SV_UA_TrackRobots;
                }
                if (SV_UserActivity_XenForo_Model_User::$SV_UA_TrackRobots || empty($robotKey))
                {
                    $visitor = XenForo_Visitor::getInstance();
                    $user = $visitor->toArray();
                    $userActivityModel->updateSessionActivity('node', $nodeId, $ip, $robotKey, $user);
                }
            }
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
