<?php

class SV_UserActivity_XenForo_Model_User extends XFCP_SV_UserActivity_XenForo_Model_User
{
    static $SV_UA_TrackRobots = null;
    static $tracked_Controllers = array
    (
        'XenForo_ControllerPublic_Thread' => array('thread', 'thread_id'),
        //'XenForo_ControllerPublic_Conversation' => array('conversation', 'conversation_id'),
    );

    public function updateSessionActivity($userId, $ip, $controllerName, $action, $viewState, array $inputParams, $viewDate = null, $robotKey = '')
    {
        if (!empty(self::$tracked_Controllers[$controllerName]))
        {
            $requiredKey = self::$tracked_Controllers[$controllerName][1];
            if (!empty($inputParams[$requiredKey]))
            {
                if (self::$SV_UA_TrackRobots === null)
                {
                    self::$SV_UA_TrackRobots = XenForo_Application::getOptions()->SV_UA_TrackRobots;
                }
                if (!self::$SV_UA_TrackRobots || $robotKey)
                {
                    $user = XenForo_Visitor::getInstance()->toArray();
                    if($userId == $user['user_id'])
                    {
                        $contentType = self::$tracked_Controllers[$controllerName][0];
                        $this->_getSVUserActivityModel()->updateSessionActivity($contentType, $inputParams[$requiredKey], $ip, $robotKey, $user);
                    }
                }
            }
        }
        return parent::updateSessionActivity($userId, $ip, $controllerName, $action, $viewState, $inputParams, $viewDate, $robotKey);
    }

    protected function _getSVUserActivityModel()
    {
        return $this->getModelFromCache('SV_UserActivity_Model');
    }
}