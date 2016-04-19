<?php

class SV_UserActivity_Model extends XenForo_Model
{
    public function updateSessionActivity($contentType, $contentId, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

    }

    public function getUsersViewing($contentType, $contentId, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);


        return array
        (
            'members' => 1,
            'guests'  => 0,
            'robots'  => 0,
            'records' => array($viewingUser)
        );
    }
}