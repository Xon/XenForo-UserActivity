<?php

class SV_UserActivity_Model extends XenForo_Model
{
    const SAMPLE_INTERVAL = 30;

    public function GarbageCollectActivity()
    {
        $registry = $this->_getDataRegistryModel();
        $cache = $this->_getCache(true);
        if (!method_exists($registry, 'getCredis') || !($credis = $registry->getCredis($cache)))
        {
            // do not have a fallback
            return;
        }

        $options = XenForo_Application::getOptions();
        $onlineStatusTimeout = $options->onlineStatusTimeout * 60;
        // we need to manually expire records out of the per content hash set if they are kept alive with activity
        $gckey = Cm_Cache_Backend_Redis::PREFIX_KEY. $cache->getOption('cache_id_prefix') . "activityGC";
        $datakey = Cm_Cache_Backend_Redis::PREFIX_KEY. $cache->getOption('cache_id_prefix') . "activity.";

        $end = XenForo_Application::$time - $onlineStatusTimeout;
        $end = $end - ($end  % self::SAMPLE_INTERVAL);

        $loopGuard = 10000;
        while($loopGuard > 0)
        {
            $contentKeyPart = $credis->spop($gckey);
            if (empty($contentKeyPart))
            {
                break;
            }
            // the actual prune operation
            $fullkey = $datakey.$contentKeyPart;
            $credis->zremrangebyscore($fullkey, 0, $end);
            // don't matter about a race condition, as they will have aded it back into the set
            if ($credis->zcard($fullkey))
            {
                // add the key back for the next GC pass
                $result = $credis->sadd($gckey, $contentKeyPart);
                $credis->expire($gckey, $onlineStatusTimeout);
            }

            $loopGuard++;
        }
    }

    public function updateSessionActivity($contentType, $contentId, $ip, $robotKey, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $score = XenForo_Application::$time - ( XenForo_Application::$time  % self::SAMPLE_INTERVAL);
        $data = array
        (
            'user_id' => $viewingUser['user_id'],
            'username' => $viewingUser['username'],
            'visible' => $viewingUser['visible'] && $viewingUser['activity_visible'],
            'robot'  => empty($robotKey) ? 0 : 1,
        );

        $options = XenForo_Application::getOptions();
        if ($viewingUser['user_id'])
        {
            if ($options->RainDD_UA_ThreadViewType == 0)
            {
                $data['display_style_group_id'] = $viewingUser['display_style_group_id'];
            }
            else if ($options->RainDD_UA_ThreadViewType == 1)
            {
                $data['gender'] = $viewingUser['gender'];
                $data['avatar_date'] = $viewingUser['avatar_date'];
                $data['gravatar'] = $viewingUser['gravatar'];
            }
            else
            {
                // unknown display type
                return;
            }
        }
        else
        {
            $data['ip'] = $ip;
        }

        $registry = $this->_getDataRegistryModel();
        $cache = $this->_getCache(true);
        if (!method_exists($registry, 'getCredis') || !($credis = $registry->getCredis($cache)))
        {
            // do not have a fallback
            return;
        }

        // record keeping
        $key = Cm_Cache_Backend_Redis::PREFIX_KEY. $cache->getOption('cache_id_prefix') . "activity.{$contentType}.{$contentId}";
        $result = $credis->zadd($key, $score, json_encode($data));
        $credis->expire($key, $options->onlineStatusTimeout * 60);

        // we need to manually expire records out of the per content hash set if they are kept alive with activity
        $key = Cm_Cache_Backend_Redis::PREFIX_KEY. $cache->getOption('cache_id_prefix') . "activityGC";
        $result = $credis->sadd($key, "{$contentType}.{$contentId}");
        $credis->expire($key, $options->onlineStatusTimeout * 60);
    }

    public function getUsersViewing($contentType, $contentId, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $memberCount = 1;
        $guestCount = 0;
        $robotCount = 0;
        $records = array($viewingUser);

        $registry = $this->_getDataRegistryModel();
        $cache = $this->_getCache(true);
        if (!method_exists($registry, 'getCredis') || !($credis = $registry->getCredis($cache)))
        {
            // do not have a fallback
            return null;
        }
        else
        {
            $key =  Cm_Cache_Backend_Redis::PREFIX_KEY. $cache->getOption('cache_id_prefix') . "activity.{$contentType}.{$contentId}";

            $options = XenForo_Application::getOptions();
            $start = XenForo_Application::$time  - $options->onlineStatusTimeout * 60;
            $start = $start - ($start  % self::SAMPLE_INTERVAL);
            $end = XenForo_Application::$time + 1;
            $onlineRecords = $credis->zrevrangebyscore($key, $end, $start, array('withscores' => true));
        }

        if(is_array($onlineRecords))
        {
            $displayType = $options->RainDD_UA_ThreadViewType;
            $seen = array($viewingUser['user_id'] => true);
            $bypassUserPrivacy = $this->_getUserModel()->canBypassUserPrivacy($null, $viewingUser);

            foreach($onlineRecords as $rec => $score)
            {
                $rec = json_decode($rec, true);
                if ($rec['user_id'])
                {
                    if (empty($seen[$rec['user_id']]))
                    {
                        $seen[$rec['user_id']] = true;
                        $memberCount += 1;
                        if(!empty($rec['visible']) || $bypassUserPrivacy)
                        {
                            $score = $score - ($score % self::SAMPLE_INTERVAL);
                            $rec['effective_last_activity'] = $score;
                            $records[] = $rec;
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

        return array
        (
            'members' => $memberCount,
            'guests'  => $guestCount,
            'robots'  => $robotCount,
            'records' => $records,
        );
    }

    protected function _getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }
}