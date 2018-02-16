<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserCountActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_FindNew extends XFCP_SV_UserActivity_XenForo_ControllerPublic_FindNew
{

    protected function threadFetcher(/** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        array $config)

    {
        if (empty($response->subView->params['threads']))
        {
            return null;
        }

        return array_keys($response->subView->params['threads']);
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'find-new',
            'type'      => 'thread',
            'actions'   => ['posts'],
            'fetcher'   => 'threadFetcher'
        ],
    ];
    use UserCountActivityInjector
    {
        _injectUserCountIntoResponse as protected _injectUserCountIntoResponseBase;
    }
}
