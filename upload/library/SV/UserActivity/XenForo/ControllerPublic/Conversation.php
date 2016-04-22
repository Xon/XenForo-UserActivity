<?php

class SV_UserActivity_XenForo_ControllerPublic_Conversation extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Conversation
{
    const CONTROLLER_NAME = 'XenForo_ControllerPublic_Conversation';

    protected function _preDispatch($action)
    {
        $this->getModelFromCache('SV_UserActivity_Model')->registerHandler(self::CONTROLLER_NAME, 'conversation', 'conversation_id');
        return parent::_preDispatch($action);
    }

    public function actionView()
    {
        $response = parent::actionView();
        $this->getModelFromCache('SV_UserActivity_Model')->insertUserActivityIntoViewResponse(self::CONTROLLER_NAME, $response);
        return $response;
    }
}