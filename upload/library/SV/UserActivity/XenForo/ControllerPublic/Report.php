<?php

class SV_UserActivity_XenForo_ControllerPublic_Report extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Report
{
    const CONTROLLER_NAME = 'XenForo_ControllerPublic_Report';

    protected function _preDispatch($action)
    {
        $this->getModelFromCache('SV_UserActivity_Model')->registerHandler(self::CONTROLLER_NAME, 'report', 'report_id');
        return parent::_preDispatch($action);
    }

    public function actionView()
    {
        $response = parent::actionView();
        $this->getModelFromCache('SV_UserActivity_Model')->insertUserActivityIntoViewResponse(self::CONTROLLER_NAME, $response);
        return $response;
    }
}