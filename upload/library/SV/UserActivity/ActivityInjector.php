<?php

trait ActivityInjector
{
    protected function _preDispatch($action)
    {
        if (!empty($this->activityInjector['controller']))
        {
            $this->getModelFromCache('SV_UserActivity_Model')->registerHandler($this->activityInjector['controller'], $this->activityInjector['type'], $this->activityInjector['id']);
        }
        return parent::_preDispatch($action);
    }

    public function _postDispatch($response, $controllerName, $action)
    {
        if (!empty($this->activityInjector['controller']) && !empty($this->activityInjector['actions']))
        {
            $actionL = strtolower($action);
            if (in_array($actionL, $this->activityInjector['actions'], true))
            {
                $this->getModelFromCache('SV_UserActivity_Model')->insertUserActivityIntoViewResponse($this->activityInjector['controller'], $response);
            }
        }
        return parent::_postDispatch($response, $controllerName, $action);
    }
}