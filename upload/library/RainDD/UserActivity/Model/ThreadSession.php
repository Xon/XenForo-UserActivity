<?php

class RainDD_UserActivity_Model_ThreadSession extends XenForo_Model_Session
{
	
	public function prepareSessionActivityConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		if ($conditions['tapatalk'])
		{
			$sqlConditions[] = "session_activity.controller_name = 'XenForo_ControllerPublic_Thread'
									or
								session_activity.controller_name = 'Tapatalk_ControllerPublic_Tapatalk'";
								
		}
		else
		{
			$sqlConditions[] = "session_activity.controller_name = 'XenForo_ControllerPublic_Thread'";
		}
		
		if (isset($conditions['version']))
		{
			if ($conditions['version'] > 1040000)
			{
				if (!$conditions['activityvisible'])
				{
					$sqlConditions[] = "user.activity_visible = 1";
				}
			}
		}
								
		if (isset($conditions['threadid']))
		{						
			$sqlConditions[] = "
				session_activity.params like '%thread_id=" . intval($conditions['threadid']) . "&%'
					or
				session_activity.params like '%thread_id=" . intval($conditions['threadid']) . "'
			";
		}
		
		
		if (!empty($conditions['cutOff']) && is_array($conditions['cutOff']))
		{
			list($operator, $cutOff) = $conditions['cutOff'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "session_activity.view_date $operator " . $db->quote($cutOff);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
}