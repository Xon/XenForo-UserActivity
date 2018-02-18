<?php

trait UserCountActivityInjector
{
    protected function _postDispatchType($response, $controllerName, $action)
    {
        /** @noinspection PhpUndefinedClassInspection */
        parent::_postDispatchType($response, $controllerName, $action);

        if ($response instanceof XenForo_ControllerResponse_View &&
            !empty($this->countActivityInjector))
        {
            $this->_injectUserCountIntoResponse($response, $action);
        }
    }

    /**
     * @param XenForo_ControllerResponse_View $response
     * @param string                          $action
     */
    protected function _injectUserCountIntoResponse($response, $action)
    {
        $fetchData = [];
        $options = XenForo_Application::getOptions();
        $actionL = strtolower($action);
        foreach($this->countActivityInjector as $config)
        {
            /** @noinspection PhpUndefinedFieldInspection */
            if (empty($options->svUADisplayCounts[$config['activeKey']]))
            {
                continue;
            }
            if (!in_array($actionL, $config['actions']))
            {
                continue;
            }
            $callback = $config['fetcher'];
            if (is_string($callback))
            {
                $callback = [$this, $callback];
            }
            if (!is_callable($callback))
            {
                continue;
            }

            $output = $callback($response, $actionL, $config);
            if (empty($output))
            {
                continue;
            }

            if (!is_array($output))
            {
                $output = [$output];
            }

            $type = $config['type'];
            if (!isset($fetchData[$type]))
            {
                $fetchData[$type] = [];
            }

            $fetchData[$type] = array_merge($fetchData[$type], $output);
        }

        if ($fetchData)
        {
            /** @var  SV_UserActivity_Model $model */
            $model = $this->getModelFromCache('SV_UserActivity_Model');
            $model->insertBulkUserActivityIntoViewResponse($response, $fetchData);
        }
    }
}
