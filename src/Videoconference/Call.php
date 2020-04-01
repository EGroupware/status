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
	public static function ajax_video_call ($id)
	{
		$response = Api\Json\Response::get();
		$caller = [
			'name' => $GLOBALS['egw_info']['user']['account_fullname'],
			'email' => $GLOBALS['egw_info']['user']['account_email'],
			'avatar' => $_SERVER['HTTP_ORIGIN'].Api\Egw::link('/api/avatar.php', array('account_id' => $GLOBALS['egw_info']['user']['account_id'])),
			'acc_id' => $GLOBALS['egw_info']['user']['account_id']
		];
		$backend = self::getBackendInstance(self::genRoomHash($GLOBALS['egw_info']['user']['account_lid'].$id), [
			'user' => $caller
		]);
		self::pushCall($backend->getMeetingUrl(),$id, $caller);
		$response->data($backend->getMeetingUrl());
	}

	/**s
	 *
	 * @param $room
	 * @param $context
	 * @return bool
	 */
	private function getBackendInstance($room, $context)
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
	public function pushCall ($call, $callee, $caller)
	{
		$p = new Api\Json\Push($callee);
		$p->call('app.status.receivedCall',[
			'call' => $call,
			'caller' => $caller
		]);
	}

	/**
	 * Generate md5 hash from given roomid
	 * @param $roomid
	 * @return string
	 */
	public static function genRoomHash($roomid)
	{
		return md5($roomid);
	}
}