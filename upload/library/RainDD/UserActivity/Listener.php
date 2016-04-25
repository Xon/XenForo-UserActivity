<?php

class RainDD_UserActivity_Listener
{

	public static function extendThreadController($class, array &$extend)
	{
	
		$options = XenForo_Application::get('options');

		if ($options->RainDD_UA_ThreadViewPos > 0)
		{
			$extend[] = 'RainDD_UserActivity_Controller_ThreadViewers';
		}

		if ($options->RainDD_UA_ThreadReadPos > 0)
		{
			$extend[] = 'RainDD_UserActivity_Controller_ThreadReaders';
		}
	
	}
	
}