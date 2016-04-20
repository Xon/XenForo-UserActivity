<?php

class SV_UserActivity_XenForo_ControllerPublic_Thread extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Thread
{
    protected function _preDispatch($action)
    {
        $this->_getSVUserActivityModel()->registerHandler('XenForo_ControllerPublic_Thread', 'thread', 'thread_id');
        return parent::_preDispatch($action);
    }

    public function actionIndex()
    {
        $response = parent::actionIndex();
        if ($response instanceof XenForo_ControllerResponse_View &&
            !empty($response->params['thread']['thread_id']))
        {
            $visitor = XenForo_Visitor::getInstance();
            if ($visitor->hasPermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers'))
            {
                $response->params['RainDD_UA_ThreadUsersViewing'] = $this->_getSVUserActivityModel()->getUsersViewing('thread', $response->params['thread']['thread_id'], $visitor->toArray());
                $response->params['RainDD_UA_ThreadViewerPermission'] = !empty($response->params['RainDD_UA_ThreadUsersViewing']);
            }
        }
        return $response;
    }

    protected function _getSVUserActivityModel()
    {
        return $this->getModelFromCache('SV_UserActivity_Model');
    }
}