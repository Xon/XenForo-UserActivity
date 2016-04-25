<?php

class RainDD_UserActivity_Controller_ThreadViewers extends XFCP_RainDD_UserActivity_Controller_ThreadViewers
{

	public function actionIndex()
	{
		$response = parent::actionIndex();
		
		if ($response instanceof XenForo_ControllerResponse_View)
		{
			$visitor = XenForo_Visitor::getInstance();
			$hasPermission =  $visitor->hasPermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadViewers');
			$bypassUserPrivacy = $this->getModelFromCache('XenForo_Model_User')->canBypassUserPrivacy();
			if ($response instanceof XenForo_ControllerResponse_View && $hasPermission)
			{
				$threadId = $response->params['thread']['thread_id'];
				$viewing = $this->getModelFromCache('RainDD_UserActivity_Model_ThreadViewers');
				$sessionModel = $this->getModelFromCache('XenForo_Model_Session');
				
				if ($bypassUserPrivacy)
				{
					$response->params += array('RainDD_UA_ThreadUsersViewing' => $viewing->getUsersViewingThread($threadId, $bypassUserPrivacy),
											'RainDD_UA_ThreadViewerPermission' => $hasPermission, 'RainDD_UA_bypassUserPrivacy' => true,
				);
				} else {
					$response->params += array('RainDD_UA_ThreadUsersViewing' => $viewing->getUsersViewingThread($threadId, $bypassUserPrivacy),
											'RainDD_UA_ThreadViewerPermission' => $hasPermission, 'RainDD_UA_bypassUserPrivacy' => false,
											'RainDD_UA_Totals' => $viewing->getViewingThreadTotals($threadId, $bypassUserPrivacy)
				);
				}
			}

			return $response;         
		}
		else
		{
			return $response;
		}		
			
	}
}