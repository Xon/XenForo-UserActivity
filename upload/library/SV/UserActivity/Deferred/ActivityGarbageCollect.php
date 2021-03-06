<?php

class SV_UserActivity_Deferred_ActivityGarbageCollect extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        /** @var SV_UserActivity_Model $model */
        $model = XenForo_Model::create("SV_UserActivity_Model");

        return $model->GarbageCollectActivity($data, $targetRunTime);
    }
}
