<?php
/**
 * Call for videoconference
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @package Status
 * @copyright (c) 2020 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Status\Videoconference;

use EGroupware\Api;

class Call
{
	/**
	 * Backend modules class name
	 */
	const BACKENDS = ['Jitsi'];

	/**
	 * @param string $id
	 * @throws
	 */
	public static function ajax_video_call ($data)
	{
		$response = Api\Json\Response::get();
		$caller = [
			'name' => $GLOBALS['egw_info']['user']['account_fullname'],
			'email' => $GLOBALS['egw_info']['user']['account_email'],
			'account_id' => $GLOBALS['egw_info']['user']['account_id']
		];
		$room = self::genUniqueRoomID();
		$CallerUrl = self::genMeetingUrl($room, $caller);
		foreach ($data as $user)
		{
			$callee = [
				'name' => $user['name'],
				'email' => $user['email'],
				'avatar' => 'account:'.$user['id'],
				'account_id' => $user['id']
			];
			$CalleeUrl = self::genMeetingUrl($room, $callee);
			self::pushCall($CalleeUrl, $user['id'], $caller);
		}
		$response->data($CallerUrl);
	}

	/**
	 * Generates a full working meeting Url
	 * @param $room room id
	 * @param $context user data
	 * @return mixed
	 */
	public static function genMeetingUrl ($room, $context)
	{
		$backend = self::_getBackendInstance($room, [
			'user' => $context
		]);
		return $backend->getMeetingUrl();
	}

	/**
	 * Generates a unique room ID
	 * @return string
	 * @throws \Exception
	 */
	public static function genUniqueRoomID()
	{
		return str_replace(array('-', '.', ':', '/') , '',  $_SERVER['HTTP_HOST']).Api\Auth::randomstring(20);
	}

	/**
	 *
	 * @param $room
	 * @param $context
	 * @return bool
	 */
	private static function _getBackendInstance($room, $context)
	{
		$config = Api\Config::read('status');
		$backend = 	$config['videoconference']['backend'] ? $config['videoconference']['backend'][0] : 'Jitsi';
		if (!in_array($backend, self::BACKENDS) || $config['videoconference']['disable'] === true) return false;
		$instance = '\\EGroupware\\Status\\Videoconference\\Backends\\'.$backend;

		return new $instance($room, $context);
	}

	/**
	 * @param $call string call url
	 * @param $callee string account id of callee
	 * @param $caller array info about caller
	 *
	 * @throws Api\Json\Exception
	 */
	public static function pushCall ($call, $callee, $caller)
	{
		$p = new Api\Json\Push($callee);
		$p->call('app.status.receivedCall',[
			'call' => $call,
			'caller' => $caller
		]);
	}
}