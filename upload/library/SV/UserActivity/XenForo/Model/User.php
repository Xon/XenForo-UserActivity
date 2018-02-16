<?php

class SV_UserActivity_XenForo_Model_User extends XFCP_SV_UserActivity_XenForo_Model_User
{
    public function updateSessionActivity($userId, $ip, $controllerName, $action, $viewState, array $inputParams, $viewDate = null, $robotKey = '')
    {
        $userActivityModel = $this->_getSVUserActivityModel();
        $handler = $userActivityModel->getHandler($controllerName);
        if (!empty($handler) && $userActivityModel->isLogging() && $viewState == 'valid')
        {
            $requiredKey = $handler['id'];
            if (!empty($inputParams[$requiredKey]))
            {
                //$activeKey = null
                /** @noinspection PhpUndefinedFieldInspection */
                if (XenForo_Application::getOptions()->SV_UA_TrackRobots || empty($robotKey))
                {
                    $visitor = XenForo_Visitor::getInstance();
                    if ($userId == $visitor['user_id'])
                    {
                        /** @var  SV_UserActivity_Model $userActivityModel */
                        $userActivityModel = $this->getModelFromCache('SV_UserActivity_Model');
                        $userActivityModel->trackViewerUsage($handler['type'], $inputParams[$requiredKey], $handler['activeKey'], $ip, $robotKey);
                    }
                }
            }
        }

        return parent::updateSessionActivity($userId, $ip, $controllerName, $action, $viewState, $inputParams, $viewDate, $robotKey);
    }

    /**
     * @return XenForo_Model|SV_UserActivity_Model
     */
    protected function _getSVUserActivityModel()
    {
        return $this->getModelFromCache('SV_UserActivity_Model');
    }
}
