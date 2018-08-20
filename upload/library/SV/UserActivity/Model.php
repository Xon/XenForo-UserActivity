<?php

class SV_UserActivity_Model extends XenForo_Model
{
    protected static $handlers      = [];
    protected static $logging       = true;
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
     * @param array  $config
     */
    public function registerHandler($controllerName, $config)
    {
        self::$handlers[$controllerName] = $config;
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
     * @param array                                    $fetchData
     * @throws Exception
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

            try
            {
                $response->params['UA_UsersViewingCount'] = $this->getUsersViewingCount($fetchData);
            }
            catch(Exception $e)
            {
                // prevent an error causing the page to fail to load
                XenForo_Error::logException($e, false);
            }
            if (isset($response->params['UA_UsersViewingCount']))
            {
                if (!empty($response->subView))
                {
                    $response->subView->params['UA_UsersViewingCount'] = $response->params['UA_UsersViewingCount'];
                }
                SV_UserActivity_Listener::$viewCounts = $response->params['UA_UsersViewingCount'];
            }
        }
    }

    /**
     * @param string                                   $controllerName
     * @param XenForo_ControllerResponse_Abstract|null $response
     * @throws Exception
     */
    public function insertUserActivityIntoViewResponse($controllerName, &$response)
    {
        if ($response instanceof XenForo_ControllerResponse_View &&
            !isset($response->params['UA_UsersViewing']))
        {
            $handler = $this->getHandler($controllerName);
            if (empty($handler) ||
                empty($handler['type']) ||
                empty($handler['id']))
            {
                return;
            }
            $contentType = $handler['type'];
            $contentIdField = $handler['id'];
            if (empty($response->params[$contentType][$contentIdField]))
            {
                return;
            }

            $visitor = XenForo_Visitor::getInstance();
            if (!$visitor->hasPermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers'))
            {
                return;
            }

            try
            {
                $response->params['UA_UsersViewing'] = $this->getUsersViewing($contentType, $response->params[$contentType][$contentIdField], $visitor->toArray());
            }
            catch(Exception $e)
            {
                // prevent an error causing the page to fail to load
                XenForo_Error::logException($e, false);
            }

            if (!empty($response->params['UA_UsersViewing']))
            {
                $response->params['UA_ContentType'] = new XenForo_Phrase($contentType);
                if (!empty($response->subView))
                {
                    $response->subView->params['UA_UsersViewing'] = $response->params['UA_UsersViewing'];
                    $response->subView->params['UA_ContentType'] = $response->params['UA_ContentType'];
                }
            }
        }
    }

    /**
     * @param array        $data
     * @param integer|null $targetRunTime
     * @return array|bool
     */
    protected function _garbageCollectActivityFallback(/** @noinspection PhpUnusedParameterInspection */
        array $data, $targetRunTime = null)
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
     * @param array $updateSet
     * @param int   $time
     * @return void
     * @throws Zend_Db_Statement_Mysqli_Exception
     */
    protected function _updateSessionActivityFallback($updateSet, $time)
    {
        $db = $this->_getDb();

        $sqlParts = [];
        $sqlArgs = [];
        foreach ($updateSet as $record)
        {
            // $record has the format; [content_type, content_id, `blob`]
            $sqlArgs = \array_merge($sqlArgs, $record);
            $sqlArgs[] = $time;
            $sqlParts[] = '(?,?,?,?)';
        }
        $sql = implode(',', $sqlParts);

        try
        {
            $db->query(
                "
            INSERT INTO xf_sv_user_activity 
            (content_type, content_id, `blob`, `timestamp`) 
            VALUES 
              {$sql}
             ON DUPLICATE KEY UPDATE `timestamp` = values(`timestamp`)",
                $sqlArgs
            );
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (Zend_Db_Statement_Mysqli_Exception $e)
        {
            // something went wrong, recount the alerts and return
            if (stripos($e->getMessage(), "Deadlock found when trying to get lock; try restarting transaction") !== false)
            {
                if (XenForo_Db::inTransaction($db))
                {
                    // why the hell are we inside a transaction?
                    throw $e;
                }
                // do them one at a time
                $sql = '(?,?,?,?)';
                foreach($updateSet as $record)
                {
                    $sqlArgs = $record;
                    $sqlArgs[] = $time;
                    $db->query(
                        "
                        INSERT INTO xf_sv_user_activity 
                        (content_type, content_id, `blob`, `timestamp`) 
                        VALUES 
                          {$sql}
                         ON DUPLICATE KEY UPDATE `timestamp` = values(`timestamp`)",
                        $sqlArgs
                    );
                }
            }
            else
            {
                throw $e;
            }
        }
    }

    /**
     * @param string $threadViewType
     * @param string $ip
     * @param string $robotKey
     * @param array  $viewingUser
     * @return array
     */
    protected function buildSessionActivityBlob($threadViewType, $ip, $robotKey, $viewingUser)
    {
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

        if ($viewingUser['user_id'])
        {
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

        /** @noinspection PhpUndefinedFieldInspection */
        if (XenForo_Application::getOptions()->svUAIncBannedState)
        {
            $data['is_banned'] = $viewingUser['is_banned'];
        }

        return $data;
    }

    protected static $cacheKeys = [
        'user_id',
        'username',
        'visible',
        'robot',
        'display_style_group_id',
        'gender',
        'avatar_date',
        'gravatar',
        'ip',
        'is_banned', // not always populated but
    ];

    /**
     * @param string $blob
     * @return array|null
     */
    protected function decodeSessionBlob($blob)
    {
        if (!$blob)
        {
            return null;
        }
        $data = explode("\n", $blob);
        $keyCount = count(self::$cacheKeys);
        $valueCount = count($data);
        if ($keyCount > $valueCount)
        {
            for ($i = $valueCount; $i < $keyCount; $i++)
            {
                $data[] = '';
            }
        }
        else if ($keyCount < $valueCount)
        {
            for ($i = $valueCount; $i < $keyCount; $i++)
            {
                array_pop($data);
            }
            if (!$data)
            {
                return null;
            }
        }
        $blob = @array_combine(self::$cacheKeys, $data);

        return $blob;
    }

    /**
     * Builds a insert set, sorting is forced if using MySQL
     *
     * @param array $trackBuffer
     * @param array $updateBlob
     * @param bool  $sort
     * @return array
     */
    protected function buildSessionActivityUpdateSet(array $trackBuffer, array $updateBlob, $sort = false)
    {
        $sort = $sort || !$this->getCredis();
        // encode the data
        $raw = implode("\n", $updateBlob);
        $outputSet = [];
        if ($sort)
        {
            // if using MySQL, ensure rows are sorted to reduce deadlock risks
            ksort($trackBuffer, SORT_STRING);
        }
        foreach ($trackBuffer as $contentType => $contentIds)
        {
            if ($sort)
            {
                ksort($contentIds, SORT_NUMERIC);
            }
            foreach ($contentIds as $contentId => $val)
            {
                $outputSet[] = [$contentType, $contentId, $raw];
            }
        }

        return $outputSet;
    }

    /**
     * @param array $updateSet
     * @throws Zend_Cache_Exception
     * @throws Zend_Db_Statement_Mysqli_Exception
     */
    protected function updateSessionActivity($updateSet)
    {
        $score = XenForo_Application::$time - (XenForo_Application::$time % $this->getSampleInterval());

        $credis = $this->getCredis();
        if (!$credis)
        {
            $this->_updateSessionActivityFallback($updateSet, $score);

            return;
        }
        $registry = $this->_getDataRegistryModel();
        $cache = $this->_getCache(true);
        /** @var Credis_Client $credis */
        $useLua = method_exists($registry, 'useLua') && $registry->useLua($cache);
        $options = XenForo_Application::getOptions();
        /** @noinspection PhpUndefinedFieldInspection */
        $onlineStatusTimeout = max(60, intval($options->onlineStatusTimeout) * 60);

        // not ideal, but fairly cheap
        // cluster support requires that each `key` potentially be on a separate host
        foreach ($updateSet as &$record)
        {
            // $record has the format; [content_type, content_id, `blob`]
            list($contentType, $contentId, $raw) = $record;
            // record keeping
            $key = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . "activity.{$contentType}.{$contentId}";

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
        }
    }

    /**
     * @param string  $contentType
     * @param integer $contentId
     * @param integer $start
     * @param integer $end
     * @return array
     */
    protected function _getUsersViewingFallback(/** @noinspection PhpUnusedParameterInspection */
        $contentType, $contentId, $start, $end)
    {
        $db = $this->_getDb();
        $raw = $db->fetchAll(
            'SELECT * FROM xf_sv_user_activity WHERE content_type = ? AND content_id = ? AND `timestamp` >= ? ORDER BY `timestamp` DESC',
            [$contentType, $contentId, $start]
        );

        $records = [];
        foreach ($raw as $row)
        {
            $records[$row['blob']] = $row['timestamp'];
        }

        return $records;
    }

    /**
     * @param string     $contentType
     * @param int        $contentId
     * @param array|null $viewingUser
     * @return array
     * @throws Zend_Cache_Exception
     */
    protected function getUsersViewing($contentType, $contentId, array $viewingUser = null)
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
                $rec = $this->decodeSessionBlob($rec);
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
    protected function _getUsersViewingCountFallback(/** @noinspection PhpUnusedParameterInspection */
        $fetchData, $start, $end)
    {
        $db = $this->_getDb();

        $args = [$start];
        $sql = [];
        foreach ($fetchData as $contentType => $list)
        {
            $list = array_filter(array_map('intval', array_unique($list)));
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

    /**
     * @param array $fetchData
     * @return array
     * @throws Zend_Cache_Exception
     */
    protected function getUsersViewingCount($fetchData)
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
     * Needs to be static as multiple instances of the Model can be created
     * @var array
     */
    protected static $trackBuffer = [];

    /**
     * @param string $contentType
     * @param int    $contentId
     * @param string $activeKey
     */
    public function bufferTrackViewerUsage($contentType, $contentId, $activeKey)
    {
        if (!$contentType ||
            !$contentId ||
            !$activeKey ||
            !$this->isLogging())
        {
            return;
        }
        $options = XenForo_Application::getOptions();
        /** @noinspection PhpUndefinedFieldInspection */
        if (empty($options->svUAPopulateUsers[$activeKey]))
        {
            return;
        }
        self::$trackBuffer[$contentType][$contentId] = true;
    }

    /**
     * @param string|null $ip
     * @param string|null $robotKey
     * @param array|null  $user
     * @throws Exception
     */
    public function flushTrackViewerUsageBuffer($ip = null, $robotKey = null, array $user = null)
    {
        if (!$this->isLogging() && !self::$trackBuffer)
        {
            return;
        }
        $options = XenForo_Application::getOptions();

        if ($robotKey === null && XenForo_Application::isRegistered('session'))
        {
            $session = XenForo_Application::getSession();
            $robotKey = $session->isRegistered('robotId') ? $session->get('robotId') : '';
        }

        /** @noinspection PhpUndefinedFieldInspection */
        if (empty($robotKey) || $options->SV_UA_TrackRobots)
        {
            $this->standardizeViewingUserReference($user);

            /** @noinspection PhpUndefinedFieldInspection */
            $threadViewType = $options->RainDD_UA_ThreadViewType;
            $blob = $this->buildSessionActivityBlob($threadViewType, $ip, $robotKey, $user);
            if (!$blob)
            {
                return;
            }

            $updateSet = $this->buildSessionActivityUpdateSet(self::$trackBuffer, $blob);
            self::$trackBuffer = [];
            if ($updateSet)
            {
                try
                {
                    $this->updateSessionActivity($updateSet);
                }
                catch(Exception $e)
                {
                    // prevent an error causing the page to fail to load
                    XenForo_Error::logException($e, false);
                }
            }
        }
    }

    /**
     * @param array $permissions
     * @param array $nodes
     * @return array|null
     */
    public function getFilteredNodeIds(array $permissions, array $nodes)
    {
        if (empty($nodes))
        {
            return null;
        }

        $nodeIds = [];
        foreach ($nodes as $nodeId => $node)
        {
            if (empty($permissions[$nodeId]))
            {
                continue;
            }
            $nodePermissions = $permissions[$nodeId];
            if (!empty($nodePermissions['viewOthers']) &&
                !empty($nodePermissions['viewContent']))
            {
                $nodeIds[] = $nodeId;
            }
        }

        return $nodeIds;
    }

    /**
     * @param array  $params
     * @param string $key
     * @return array|null
     */
    public function getFilteredThreadIds(array $params, $key)
    {
        if (empty($params[$key]))
        {
            return null;
        }

        if (empty($params['nodeList']['nodePermissions']))
        {
            $permissions = XenForo_Visitor::getInstance()->getAllNodePermissions();
        }
        else
        {
            $permissions = $params['nodeList']['nodePermissions'];
        }
        $threads = $params[$key];
        $nodeIds = array_unique(XenForo_Application::arrayColumn($threads, 'node_id'));
        foreach ($nodeIds as $nodeId)
        {
            if (!isset($permissions[$nodeId]))
            {
                /** @var XenForo_Model_Node $nodeModel */
                $nodeModel = $this->getModelFromCache('XenForo_Model_Node');
                $permissions = $nodeModel->getNodePermissionsForPermissionCombination();
                break;
            }
        }

        $threadIds = [];
        foreach ($threads as $thread)
        {
            $nodeId = $thread['node_id'];
            if (empty($permissions[$nodeId]))
            {
                continue;
            }
            $nodePermissions = $permissions[$nodeId];
            if (!empty($nodePermissions['viewContent']))
            {
                $threadIds[] = $thread['thread_id'];
            }
        }

        return $threadIds;
    }

    /**
     * @return XenForo_Model|XenForo_Model_User|SV_UserActivity_XenForo_Model_User
     */
    protected function _getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }
}
