<?php

class SV_UserActivity_XenForo_ControllerPublic_Post extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Post
{
    const CONTROLLER_NAME = 'XenForo_ControllerPublic_Post';

    protected function _preDispatch($action)
    {
        $this->getModelFromCache('SV_UserActivity_Model')->registerHandler(self::CONTROLLER_NAME, 'thread', 'thread_id');
        return parent::_preDispatch($action);
    }

    public function canUpdateSessionActivity($controllerName, $action, &$newState)
    {
        $actionL = strtolower($action);
        switch($actionL)
        {
            case 'like':
            case 'rate':
            case 'threadmark':
                return true;
        }

        return parent::canUpdateSessionActivity($controllerName, $action, $newState);
    }

    public function updateSessionActivity($controllerResponse, $controllerName, $action)
    {
        if ($controllerResponse instanceof XenForo_ControllerResponse_View && $this->_request->getParam('thread_id') === null)
        {
            if (isset($controllerResponse->params['post']['thread_id']))
            {
                $this->_request->setParam('thread_id', $controllerResponse->params['post']['thread_id']);
            }
            else if (isset($controllerResponse->params['thread']['thread_id']))
            {
                $this->_request->setParam('thread_id', $controllerResponse->params['thread']['thread_id']);
            }
        }
        parent::updateSessionActivity($controllerResponse, $controllerName, $action);
    }
}