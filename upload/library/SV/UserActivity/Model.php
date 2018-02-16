<?php

class SV_UserActivity_Model extends XenForo_Model
{
    protected static $handlers = [];
    protected static $logging  = true;
    protected static $forceFallback = false;

    public function getSampleInterval()
    {
        return 30;
    }

    public function supresssLogging()
    {
        self::$logging = false;
    }

    public function isLogging()
    {
        return self::$logging;
    }

    /**
     * @param string $controllerName
     * @param string $contentType
     * @param string $contentIdField
     */
    public function registerHandler($controllerName, $contentType, $contentIdField)
    {
        self::$handlers[$controllerName] = [$contentType, $contentIdField];
    }

    /**
     * @param string $controllerName
     * @return bool|array
     */
    public function getHandler($controllerName)
    {
        if (empty(self::$handlers[$controllerName]))
        {
            return false;
        }

        return self::$handlers[$controllerName];
    }

    /**
     * @param XenForo_ControllerResponse_Abstract|null $response
     * @param array                                   $fetchData
     */
    public function insertBulkUserActivityIntoViewResponse(&$response, array $fetchData)
    {
        if ($response instanceof XenForo_ControllerResponse_View)
        {
            $visitor = XenForo_Visitor::getInstance();
            if (!$visitor->hasPermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers'))
            {
                return;
            }

            $response->params['UA_UsersViewingCount'] = $this->getUsersViewingCount($fetchData);
            if (!empty($response->subView))
            {
                $response->subView->params['UA_UsersViewingCount'] = $response->params['UA_UsersViewingCount'];
            }
            SV_UserActivity_Listener::$viewCounts = $response->params['UA_UsersViewingCount'];
        }
    }

    /**
     * @param string                                   $controllerName
     * @param XenForo_ControllerResponse_Abstract|null $response
     */
    public function insertUserActivityIntoViewResponse($controllerName, &$response)
    {
        if ($response instanceof XenForo_ControllerResponse_View &&
            !isset($response->params['UA_ViewerPermission']))
        {
            $handler = $this->getHandler($controllerName);
            if (empty($handler))
            {
                return;
            }
            $contentType = $handler[0];
            $contentIdField = $handler[1];
            if (empty($response->params[$contentType][$contentIdField]))
            {
                return;
            }

            $visitor = XenForo_Visitor::getInstance();
            if (!$visitor->hasPermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers'))
            {
                return;
            }

            $response->params['UA_UsersViewing'] = $this->getUsersViewing($contentType, $response->params[$contentType][$contentIdField], $visitor->toArray());
            $response->params['UA_ViewerPermission'] = !empty($response->params['UA_UsersViewing']);
            if ($response->params['UA_ViewerPermission'])
            {
                $response->params['UA_ContentType'] = new XenForo_Phrase($contentType);
                if (!empty($response->subView))
                {
                    $response->subView->params['UA_UsersViewing'] = $response->params['UA_UsersViewing'];
                    $response->subView->params['UA_ViewerPermission'] = $response->params['UA_ViewerPermission'];
                }
            }
        }
    }

    /**
     * @param array        $data
     * @param integer|null $targetRunTime
     * @return array|bool
     */
    protected function _garbageCollectActivityFallback(/** @noinspection PhpUnusedParameterInspection */array $data, $targetRunTime = null)
    {
        $options = XenForo_Application::getOptions();
        /** @noinspection PhpUndefinedFieldInspection */
        $onlineStatusTimeout = $options->onlineStatusTimeout * 60;
        $end = XenForo_Application::$time - $onlineStatusTimeout;
        $end = $end - ($end % $this->getSampleInterval());

        $db = $this->_getDb();
        $db->query('DELETE FROM `xf_sv_user_activity` WHERE `timestamp` < ?', $end);

        return false;
    }

    /**
     * @return Credis_Client|null
     */
    protected function getCredis()
    {
        if (self::$forceFallback)
        {
            return null;
        }

        $registry = $this->_getDataRegistryModel();
        $cache = $this->_getCache(true);
        if (!method_exists($registry, 'getCredis') || !($credis = $registry->getCredis($cache)))
        {
            return null;
        }
        return $credis;
    }

    /**
     * @param array        $data
     * @param integer|null $targetRunTime
     * @return array|bool
     * @throws Zend_Cache_Exception
     */
    public function GarbageCollectActivity(array $data, $targetRunTime = null)
    {
        $credis = $this->getCredis();
        if (!$credis)
        {
            return $this->_garbageCollectActivityFallback($data, $targetRunTime);
        }
        $cache = $this->_getCache(true);

        /** @var Credis_Client $credis */
        $options = XenForo_Application::getOptions();
        /** @noinspection PhpUndefinedFieldInspection */
        $onlineStatusTimeout = $options->onlineStatusTimeout * 60;
        // we need to manually expire records out of the per content hash set if they are kept alive with activity
        $datakey = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . "activity.";

        $end = XenForo_Application::$time - $onlineStatusTimeout;
        $end = $end - ($end % $this->getSampleInterval());

        // indicate to the redis instance would like to process X items at a time.
        $count = 100;
        // prevent looping forever
        $loopGuard = 10000;
        // find indexes matching the pattern
        $cursor = empty($data['cursor']) ? null : $data['cursor'];
        $s = microtime(true);
        do
        {
            $keys = $credis->scan($cursor, $datakey . "*", $count);
            $loopGuard--;
            if ($keys === false)
            {
                break;
            }
            $data['cursor'] = $cursor;

            // the actual prune operation
            foreach ($keys as $key)
            {
                $credis->zremrangebyscore($key, 0, $end);
            }

            $runTime = microtime(true) - $s;
            if ($targetRunTime && $runTime > $targetRunTime)
            {
                break;
            }
            $loopGuard--;
        }
        while ($loopGuard > 0 && !empty($cursor));

        if (empty($cursor))
        {
            return false;
        }

        return $data;
    }

    const LUA_IFZADDEXPIRE_SH1 = 'dc1d76eefaca2f4ccf848a6ed7e80def200ac7b7';

    /**
     * @param string  $contentType
     * @param integer $contentId
     * @param integer $time
     * @param array   $data
     * @param string  $raw
     * @return array
     */
    protected function _updateSessionActivityFallback($contentType, $contentId, $time, array $data, $raw)
    {
        $db = $this->_getDb();
        $db->query(
            '
            INSERT INTO xf_sv_user_activity 
            (content_type, content_id, `timestamp`, `blob`) 
            VALUES 
            (?,?,?,?)
             ON DUPLICATE KEY UPDATE `timestamp` = values(`timestamp`)',
            [$contentType, $contentId, $time, $raw]
        );

        return $data;
    }

    /**
     * @param string     $contentType
     * @param integer    $contentId
     * @param string     $ip
     * @param string     $robotKey
     * @param array|null $viewingUser
     * @return array|null
     * @throws Zend_Cache_Exception
     */
    public function updateSessionActivity($contentType, $contentId, $ip, $robotKey, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $score = XenForo_Application::$time - (XenForo_Application::$time % $this->getSampleInterval());
        $data = [
            'user_id'                => $viewingUser['user_id'],
            'username'               => $viewingUser['username'],
            'visible'                => $viewingUser['visible'] && $viewingUser['activity_visible'] ? 1 : null,
            'robot'                  => empty($robotKey) ? null : 1,
            'display_style_group_id' => null,
            'gender'                 => null,
            'avatar_date'            => null,
            'gravatar'               => null,
            'ip'                     => null,
        ];

        $options = XenForo_Application::getOptions();
        if ($viewingUser['user_id'])
        {
            /** @noinspection PhpUndefinedFieldInspection */
            $threadViewType = $options->RainDD_UA_ThreadViewType;
            if ($threadViewType == 0)
            {
                $data['display_style_group_id'] = $viewingUser['display_style_group_id'];
            }
            else if ($threadViewType == 1)
            {
                $data['gender'] = $viewingUser['gender'];
                $data['avatar_date'] = $viewingUser['avatar_date'];
                $data['gravatar'] = $viewingUser['gravatar'];
            }
            else
            {
                // unknown display type
                return null;
            }
        }
        else
        {
            $data['ip'] = $ip;
        }

        // encode the data
        $raw = implode("\n", $data);

        $credis = $this->getCredis();
        if (!$credis)
        {
            // do not have a fallback
            return $this->_updateSessionActivityFallback($contentType, $contentId, $score, $data, $raw);
        }
        $registry = $this->_getDataRegistryModel();
        $cache = $this->_getCache(true);
        /** @var Credis_Client $credis */
        $useLua = method_exists($registry, 'useLua') && $registry->useLua($cache);

        // record keeping
        $key = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . "activity.{$contentType}.{$contentId}";
        /** @noinspection PhpUndefinedFieldInspection */
        $onlineStatusTimeout = max(60, intval($options->onlineStatusTimeout) * 60);

        if ($useLua)
        {
            $ret = $credis->evalSha(self::LUA_IFZADDEXPIRE_SH1, [$key], [$score, $raw, $onlineStatusTimeout]);
            if ($ret === null)
            {
                $script =
                    "local c = tonumber(redis.call('zscore', KEYS[1], ARGV[2])) " .
                    "local n = tonumber(ARGV[1]) " .
                    "local retVal = 0 " .
                    "if c == nil or n > c then " .
                    "retVal = redis.call('ZADD', KEYS[1], n, ARGV[2]) " .
                    "end " .
                    "redis.call('EXPIRE', KEYS[1], ARGV[3]) " .
                    "return retVal ";
                $credis->eval($script, [$key], [$score, $raw, $onlineStatusTimeout]);
            }
        }
        else
        {
            $credis->pipeline()->multi();
            // O(log(N)) for each item added, where N is the number of elements in the sorted set.
            $credis->zadd($key, $score, $raw);
            $credis->expire($key, $onlineStatusTimeout);
            $credis->exec();
        }

        return $data;
    }

    const CacheKeys = [
        'user_id',
        'username',
        'visible',
        'robot',
        'display_style_group_id',
        'gender',
        'avatar_date',
        'gravatar',
        'ip',
    ];

    /**
     * @param string  $contentType
     * @param integer $contentId
     * @param integer $start
     * @param integer $end
     * @return array
     */
    protected function _getUsersViewingFallback(/** @noinspection PhpUnusedParameterInspection */$contentType, $contentId, $start, $end)
    {
        $db = $this->_getDb();
        $raw = $db->fetchAll(
            'SELECT * FROM xf_sv_user_activity WHERE content_type = ? AND content_id = ? AND `timestamp` >= ? ORDER BY `timestamp` desc',
            [$contentType, $contentId, $start]
        );

        $records = [];
        foreach ($raw as $row)
        {
            $records[$row['blob']] = $row['timestamp'];
        }

        return $records;
    }

    public function getUsersViewing($contentType, $contentId, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $isGuest = empty($viewingUser['user_id']);
        $memberCount = $isGuest ? 0 : 1;
        $guestCount = 0;
        $robotCount = 0;
        $records = $isGuest ? [] : [$viewingUser];

        $options = XenForo_Application::getOptions();
        /** @noinspection PhpUndefinedFieldInspection */
        $start = XenForo_Application::$time - $options->onlineStatusTimeout * 60;
        $start = $start - ($start % $this->getSampleInterval());
        $end = XenForo_Application::$time + 1;

        $credis = $this->getCredis();
        /** @noinspection PhpUndefinedFieldInspection */
        $pruneChance = $options->UA_pruneChance;
        if (!$credis)
        {
            // do not have a fallback
            $onlineRecords = $this->_getUsersViewingFallback($contentType, $contentId, $start, $end);
            // check if the activity counter needs pruning
            if ($pruneChance > 0 && mt_rand() < $pruneChance)
            {
                $this->_garbageCollectActivityFallback([]);
            }
        }
        else
        {
            $registry = $this->_getDataRegistryModel();
            $cache = $this->_getCache(true);
            /** @var Credis_Client $credis */
            $key = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . "activity.{$contentType}.{$contentId}";
            $onlineRecords = $credis->zrevrangebyscore($key, $end, $start, ['withscores' => true]);
            // check if the activity counter needs pruning
            if ($pruneChance > 0 && mt_rand() < $pruneChance)
            {
                $credis = $registry->getCredis($cache, false);
                /** @noinspection PhpUndefinedFieldInspection */
                if ($credis->zcard($key) >= count($onlineRecords) * $options->UA_fillFactor)
                {
                    // O(log(N)+M) with N being the number of elements in the sorted set and M the number of elements removed by the operation.
                    $credis->zremrangebyscore($key, 0, $start - 1);
                }
            }
        }

        /** @noinspection PhpUndefinedFieldInspection */
        $cutoff = $options->SV_UA_Cutoff;
        $memberVisibleCount = $isGuest ? 0 : 1;
        $recordsUnseen = 0;

        if (is_array($onlineRecords))
        {
            $seen = [$viewingUser['user_id'] => true];
            $bypassUserPrivacy = $this->_getUserModel()->canBypassUserPrivacy($null, $viewingUser);
            $sampleInterval = $this->getSampleInterval();

            foreach ($onlineRecords as $rec => $score)
            {
                $data = explode("\n", $rec);
                $rec = @array_combine(self::CacheKeys, $data);
                if (empty($rec))
                {
                    continue;
                }
                if ($rec['user_id'])
                {
                    if (empty($seen[$rec['user_id']]))
                    {
                        $seen[$rec['user_id']] = true;
                        $memberCount += 1;
                        if (!empty($rec['visible']) || $bypassUserPrivacy)
                        {
                            $memberVisibleCount += 1;
                            if ($cutoff > 0 && $memberVisibleCount > $cutoff)
                            {
                                $recordsUnseen += 1;
                                continue;
                            }
                            $score = $score - ($score % $sampleInterval);
                            $rec['effective_last_activity'] = $score;
                            $records[] = $rec;
                        }
                        else
                        {
                            $recordsUnseen += 1;
                        }
                    }
                }
                else if (empty($rec['robot']))
                {
                    $guestCount += 1;
                }
                else
                {
                    $robotCount += 1;
                }
            }
        }

        return [
            'members'       => $memberCount,
            'guests'        => $guestCount,
            'robots'        => $robotCount,
            'records'       => $records,
            'recordsUnseen' => $recordsUnseen,
        ];
    }

    /**
     * @param array $fetchData
     * @param int   $start
     * @param int   $end
     * @return array
     */
    protected function _getUsersViewingCountFallback(/** @noinspection PhpUnusedParameterInspection */ $fetchData, $start, $end)
    {
        $db = $this->_getDb();

        $args = [$start];
        $sql = [];
        foreach($fetchData as $contentType => $list)
        {
            $list = array_filter(array_map('intval',array_unique($list)));
            if ($list)
            {
                $sql[] = "\n(content_type = " . $db->quote($contentType) . " AND content_id in (" . $db->quote($list) . "))";
            }
        }

        if (!$sql)
        {
            return [];
        }

        $sql = join(' OR ', $sql);

        $raw = $db->fetchAll(
            "SELECT content_type, content_id, count(*) as count
                  FROM xf_sv_user_activity 
                  WHERE `timestamp` >= ?  AND ({$sql})
                  group by content_type, content_id",
            $args
        );

        $records = [];
        foreach ($raw as $row)
        {
            $records[$row['content_type']][$row['content_id']] = $row['count'];
        }

        return $records;
    }

    public function getUsersViewingCount($fetchData)
    {
        $options = XenForo_Application::getOptions();
        /** @noinspection PhpUndefinedFieldInspection */
        $start = XenForo_Application::$time - $options->onlineStatusTimeout * 60;
        $start = $start - ($start % $this->getSampleInterval());
        $end = XenForo_Application::$time + 1;

        $credis = $this->getCredis();
        /** @noinspection PhpUndefinedFieldInspection */
        $pruneChance = $options->UA_pruneChance;
        if (!$credis)
        {
            // do not have a fallback
            $onlineRecords = $this->_getUsersViewingCountFallback($fetchData, $start, $end);
            // check if the activity counter needs pruning
            if ($pruneChance > 0 && mt_rand() < $pruneChance)
            {
                $this->_garbageCollectActivityFallback([]);
            }
        }
        else
        {
            $cache = $this->_getCache(true);
            /** @var Credis_Client $credis */

            $registry = $this->_getDataRegistryModel();
            $useLua = method_exists($registry, 'useLua') && $registry->useLua($cache);

            $onlineRecords = [];
            $args = [];
            foreach ($fetchData as $contentType => $list)
            {
                $list = array_filter(array_map('intval', array_unique($list)));
                foreach ($list as $contentId)
                {
                    $args[] = [$contentType, $contentId];
                }
            }

            if (false) //$useLua
            {
                /*
                $ret = $credis->evalSha(self::LUA_IFZADDEXPIRE_SH1, [$key], [$score, $raw, $onlineStatusTimeout]);
                if ($ret === null)
                {
                    $script = "";
                    $credis->eval($script, [$key], [$score, $raw, $onlineStatusTimeout]);
                }
                */
            }
            else
            {
                $credis->pipeline()->multi();
                foreach ($args as $row)
                {
                    $key = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . "activity.{$row[0]}.{$row[1]}";
                    $credis->zcount($key, $start, $end);
                }
                $ret = $credis->exec();
                foreach ($args as $i => $row)
                {
                    $val = intval($ret[$i]);
                    if ($val)
                    {
                        $onlineRecords[$row[0]][$row[1]] = $val;
                    }
                }
            }
        }

        return $onlineRecords;
    }

    /**
     * @return XenForo_Model|XenForo_Model_User|SV_UserActivity_XenForo_Model_User
     */
    protected function _getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }
}
