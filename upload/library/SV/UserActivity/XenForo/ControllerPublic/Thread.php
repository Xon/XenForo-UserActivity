<?php

class SV_UserActivity_XenForo_ControllerPublic_Thread extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Thread
{
    const CONTROLLER_NAME = 'XenForo_ControllerPublic_Thread';

    protected function _preDispatch($action)
    {
        $this->getModelFromCache('SV_UserActivity_Model')->registerHandler(self::CONTROLLER_NAME, 'thread', 'thread_id');
        return parent::_preDispatch($action);
    }

    public function actionIndex()
    {
        $response = parent::actionIndex();
        $this->getModelFromCache('SV_UserActivity_Model')->insertUserActivityIntoViewResponse(self::CONTROLLER_NAME, $response);
        return $response;
    }
}