<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/ActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_Forum extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Forum
{
    public function __construct(Zend_Controller_Request_Http $request, Zend_Controller_Response_Http $response, XenForo_RouteMatch $routeMatch)
    {
        parent::__construct($request, $response, $routeMatch);
        /** @noinspection PhpUndefinedFieldInspection */
        if (!XenForo_Application::getOptions()->svUATrackForum)
        {
            $this->activityInjector = [];
        }
    }

    public function actionIndex()
    {
        return $this->injectResponse(parent::actionIndex());
    }

    public function actionForum()
    {
        return $this->injectResponse(parent::actionForum());
    }

    /**
     * @param XenForo_ControllerResponse_Abstract $response
     * @return XenForo_ControllerResponse_Abstract
     */
    public function injectResponse($response)
    {
        if ($response instanceof  XenForo_ControllerResponse_View &&
            empty($response->params['touchedUA']))
        {
            $response->params['touchedUA'] = true;
            $fetchData = [];
            $options = XenForo_Application::getOptions();

            /** @noinspection PhpUndefinedFieldInspection */
            if ($options->svUATrackForum)
            {
                $fetchData['node'] = [];
                if (!empty($response->params['nodeList']))
                {
                    $fetchData['node'] = array_keys($response->params['nodeList']['nodeParents']);
                }
                if (isset($response->params['forum']['node_id']))
                {
                    $fetchData['node'][] = $response->params['forum']['node_id'];
                }
            }

            /** @noinspection PhpUndefinedFieldInspection */
            if ($options->svUADisplayThreads)
            {
                $fetchData['thread'] = [];
                if (isset($response->params['threads']))
                {
                    $fetchData['thread'] = array_keys($response->params['threads']);
                }
                if (isset($response->params['stickyThreads']))
                {
                    $fetchData['thread'] = array_merge($fetchData['thread'], array_keys($response->params['stickyThreads']));
                }
            }
            if ($fetchData)
            {
                /** @var  SV_UserActivity_Model $model */
                $model = $this->getModelFromCache('SV_UserActivity_Model');
                $model->insertBulkUserActivityIntoViewResponse($response, $fetchData);
                if (!empty($response->params['UA_UsersViewingCount']))
                {
                    SV_UserActivity_Listener::$forumViewCounts = $response->params['UA_UsersViewingCount'];
                }
            }
        }

        return $response;
    }


    protected $activityInjector = [
        'controller' => 'XenForo_ControllerPublic_Forum',
        'type'       => 'node',
        'id'         => 'node_id',
        'actions'    => [], // deliberate, as we do our own thing to inject content
        'countsOnly' => true,
    ];
    use ActivityInjector;
}
