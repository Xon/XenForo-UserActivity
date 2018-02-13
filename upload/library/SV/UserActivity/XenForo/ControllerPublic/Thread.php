<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/ActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_Thread extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Thread
{
    public function __construct(Zend_Controller_Request_Http $request, Zend_Controller_Response_Http $response, XenForo_RouteMatch $routeMatch)
    {
        parent::__construct($request, $response, $routeMatch);

    }

    public function actionIndex()
    {
        $response = parent::actionIndex();

        /** @noinspection PhpUndefinedFieldInspection */
        if (XenForo_Application::getOptions()->svUATrackForum)
        {
            /** @var  SV_UserActivity_Model$model */
            //$model = $this->getModelFromCache('SV_UserActivity_Model');
            //$model->registerHandler('XenForo_ControllerPublic_Thread', 'node', 'node_id');
        }

        return $response;
    }

    protected $activityInjector = [
        'controller' => 'XenForo_ControllerPublic_Thread',
        'type'       => 'thread',
        'id'         => 'thread_id',
        'actions'    => ['index'],
        'countsOnly' => false,
    ];
    use ActivityInjector;
}
