<?php

trait UserActivityInjector
{
    protected function getSvActivityInjector($display)
    {
        if (empty($this->activityInjector['controller']) ||
            empty($this->activityInjector['activeKey']))
        {
            return null;
        }

        $key = $this->activityInjector['activeKey'];
        $options = XenForo_Application::getOptions();
        if ($display)
        {
            /** @noinspection PhpUndefinedFieldInspection */
            if (empty($options->svUADisplayUsers[$key]))
            {
                return null;
            }
        }
        else
        {
            /** @noinspection PhpUndefinedFieldInspection */
            if (empty($options->svUAPopulateUsers[$key]))
            {
                return null;
            }
        }


        return $this->activityInjector;
    }

    protected function _preDispatch($action)
    {
        if ($activityInjector = $this->getSvActivityInjector(false))
        {
            /** @var  SV_UserActivity_Model$model */
            $model = $this->getModelFromCache('SV_UserActivity_Model');
            $model->registerHandler($activityInjector['controller'], $activityInjector);
        }

        return parent::_preDispatch($action);
    }

    protected function _postDispatch($response, $controllerName, $action)
    {
        if (($activityInjector = $this->getSvActivityInjector(true)) &&
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
