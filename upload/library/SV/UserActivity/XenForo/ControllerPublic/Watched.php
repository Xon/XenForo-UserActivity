<?php

/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserActivityInjector.php');
/** @noinspection PhpIncludeInspection */
include_once('SV/UserActivity/UserCountActivityInjector.php');
class SV_UserActivity_XenForo_ControllerPublic_Watched extends XFCP_SV_UserActivity_XenForo_ControllerPublic_Watched
{
    protected function forumFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        $action,
        array $config)
    {
        if (empty($response->params['forumsWatched']))
        {
            return null;
        }

        return array_keys($response->params['forumsWatched']);
    }


    protected function threadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerResponse_View $response,
        $action,
        array $config)

    {
        if (empty($response->params['threads']))
        {
            return null;
        }

        return array_keys($response->params['threads']);
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'watched-forums',
            'type'      => 'node',
            'actions'   => ['forums'],
            'fetcher'   => 'forumFetcher',
        ],
        [
            'activeKey' => 'watched-threads',
            'type'      => 'thread',
            'actions'   => ['threads', 'threadsall'],
            'fetcher'   => 'threadFetcher'
        ],
    ];
    use UserCountActivityInjector;
}
