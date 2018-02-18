<?php

class SV_UserActivity_XenForo_Model_User extends XFCP_SV_UserActivity_XenForo_Model_User
{
    public function updateSessionActivity($userId, $ip, $controllerName, $action, $viewState, array $inputParams, $viewDate = null, $robotKey = '')
    {
        $userActivityModel = $this->_getSVUserActivityModel();
        $visitor = XenForo_Visitor::getInstance();
        if ($userActivityModel->isLogging() && $viewState == 'valid' && $userId === $visitor['user_id'])
        {
            $handler = $userActivityModel->getHandler($controllerName);
            if (!empty($handler) &&
                !empty($handler['type']) &&
                !empty($handler['id']))
            {
                $requiredKey = $handler['id'];
                if (!empty($inputParams[$requiredKey]))
                {

                    $userActivityModel->bufferTrackViewerUsage($handler['type'], $inputParams[$requiredKey], $handler['activeKey']);
                }
            }

            $userActivityModel->flushTrackViewerUsageBuffer($ip, $robotKey, $visitor->toArray());
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
