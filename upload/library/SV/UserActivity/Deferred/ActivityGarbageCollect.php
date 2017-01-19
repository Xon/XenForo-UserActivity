<?php

class SV_UserActivity_Deferred_ActivityGarbageCollect extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        return XenForo_Model::create("SV_UserActivity_Model")->GarbageCollectActivity($data, $targetRunTime);
    }
}