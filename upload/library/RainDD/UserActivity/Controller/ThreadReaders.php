<?php

class RainDD_UserActivity_Controller_ThreadReaders extends XFCP_RainDD_UserActivity_Controller_ThreadReaders
{

	public function actionIndex()
	{
		$response = parent::actionIndex();
		
		if ($response instanceof XenForo_ControllerResponse_View)
		{
			$visitor = XenForo_Visitor::getInstance();
			$bypassUserPrivacy = $this->getModelFromCache('XenForo_Model_User')->canBypassUserPrivacy();
			$hasPermission =  $visitor->hasPermission('RainDD_UA_PermissionsMain', 'RainDD_UA_ThreadReaders');
			if ($response instanceof XenForo_ControllerResponse_View && $hasPermission)
			{
				$threadId = $response->params['thread']['thread_id'];
				$readUsersModel = $this->getModelFromCache('RainDD_UserActivity_Model_ThreadReaders');
				$readUsers = $readUsersModel->getReadUsers($threadId, $bypassUserPrivacy);
				$count = count($readUsers);		
				$response->params += array('RainDD_UA_ThreadUsersReading' => $readUsers, 
											'RainDD_UA_ThreadReaderPermission' => $hasPermission,
											'RainDD_UA_ThreadReaderCount' => $count);		
			}		
		return $response;         
		}
		else
		{
			return $response;
		}

	}

}