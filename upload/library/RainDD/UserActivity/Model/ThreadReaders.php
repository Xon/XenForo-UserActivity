<?php

class RainDD_UserActivity_Model_ThreadReaders extends XenForo_Model
{

    public function getReadUsers($threadId, $bypassUserPrivacy)
    {	
		$options = XenForo_Application::get('options');
        $db = $this->_getDb();
		
		if ($options->currentVersionId > 1040000)
			{
				$select = "SELECT user.username as username, user.user_id, user.display_style_group_id, user.avatar_date, user.avatar_width, user.avatar_height, user.gravatar, user.is_banned, user.activity_visible";
			}
			else
			{
				$select = "SELECT user.username as username, user.user_id, user.display_style_group_id, user.avatar_date, user.avatar_width, user.avatar_height, user.gravatar, user.is_banned";
			}
		
		$from = "FROM xf_thread_read AS threadread
            INNER JOIN xf_user AS user ON (user.user_id = threadread.user_id)
        ";
		
		$where = "WHERE threadread.thread_id = ?";
		
		if ($options->RainDD_UA_ThreadReadBanned == 2 || $options->RainDD_UA_ThreadReadBanned == 1 && !$bypassUserPrivacy)
		{
			$where = $where . " AND user.is_banned = 0";
		}
		
		if ($options->RainDD_UA_ThreadReadActivityPrivacy == 2 || $options->RainDD_UA_ThreadReadActivityPrivacy == 1 && !$bypassUserPrivacy)
		{
			if ($options->currentVersionId > 1040000)
			{
				$where = $where . " AND user.activity_visible = 1";
			}
			else
			{
				$where = $where . " AND user.visible = 1";
			}
		}
		
		
			
		$query =  $select . ' ' . $from . ' ' . $where;
		
        $users = $db->fetchAll($query, $threadId);
        return $users;
    }
}
