<?php

trait UserActivityInjector
{
    protected function getSvActivityInjector()
    {
        if (empty($this->activityInjector['controller']) ||
            empty($this->activityInjector['activeKey']))
        {
            return null;
        }

        $key = $this->activityInjector['activeKey'];
        $options = XenForo_Application::getOptions();
        /** @noinspection PhpUndefinedFieldInspection */
        if (empty($options->svUADisplayUsers[$key]))
        {
            return null;
        }

        return $this->activityInjector;
    }

    protected function _preDispatch($action)
    {
        if ($activityInjector = $this->getSvActivityInjector())
        {
            /** @var  SV_UserActivity_Model$model */
            $model = $this->getModelFromCache('SV_UserActivity_Model');
            $model->registerHandler($activityInjector['controller'], $activityInjector['type'], $activityInjector['id']);
        }

        return parent::_preDispatch($action);
    }

    public function _postDispatch($response, $controllerName, $action)
    {
        if (($activityInjector = $this->getSvActivityInjector()) &&
            !empty($activityInjector['actions']))
        {
            $actionL = strtolower($action);
            if (in_array($actionL, $activityInjector['actions'], true))
            {
                /** @var  SV_UserActivity_Model$model */
                $model = $this->getModelFromCache('SV_UserActivity_Model');
                $model->insertUserActivityIntoViewResponse($activityInjector['controller'], $response);
            }
        }

        return parent::_postDispatch($response, $controllerName, $action);
    }
}
