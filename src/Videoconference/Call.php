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
	 * @param string $_room optional room id in order initiate a call within an
	 * existing session
	 * @param boolean $_npn no picked up notification,prevents caller from getting
	 * notification about callee response state. It make sense to be switched on
	 * when inviting to an existing session
	 *
	 * @throws
	 */
	public static function ajax_video_call($data, $_room = null, $_npn = false)
	{
		$response = Api\Json\Response::get();
		$caller = [
			'name' => $GLOBALS['egw_info']['user']['account_fullname'],
			'email' => $GLOBALS['egw_info']['user']['account_email'],
			'avatar' => (string)(new Api\Contacts\Photo('account:' . $GLOBALS['egw_info']['user']['account_id'], true)),
			'account_id' => $GLOBALS['egw_info']['user']['account_id']
		];
		$room = $_room ? $_room : self::genUniqueRoomID();
		$CallerUrl = self::genMeetingUrl($room, $caller, ['audioonly' => $data[0]['audioonly']]);
		foreach ($data as $user) {
			// try to fill out missing information
			if ($user['id'] && (!$user['name'] || !$user['email']))
			{
				$user['name'] = Api\Accounts::id2name($user['id'], 'account_fullname');
				$user['email'] = Api\Accounts::id2name($user['id'], 'account_email');
			}
			$callee = [
				'name' => $user['name'],
				'email' => $user['email'],
				'avatar' => (string)(new Api\Contacts\Photo('account:' . $user['id'], true)),
				'account_id' => $user['id']
			];
			$CalleeUrl = self::genMeetingUrl($room, $callee, ['audioonly' => $user['audioonly']]);
			self::pushCall($CalleeUrl, $user['id'], $caller, $_npn);
		}
		$response->data(['caller' => $CallerUrl, 'callee' => $CalleeUrl]);
	}

	/**
	 * sends full working meeting Url to client
	 * @param $room string room id
	 * @param $context array user data
	 * @throws Api\Json\Exception
	 */
	public static function ajax_genMeetingUrl($room, $context)
	{
		$respose = Api\Json\Response::get();
		if (empty($context['avatar'])) $context['avatar'] = (string)(new Api\Contacts\Photo('account:' . $context['account_id'], true));
		$respose->data([self::genMeetingUrl($room, $context)]);
	}

	/**
	 *
	 * @param type $data
	 */
	public static function ajax_setMissedCallNotification($data)
	{
		if (!$data['npn'])
		{
			$p = new Api\Json\Push($data['caller']['account_id']);

			$p->call('app.status.didNotPickUp', [
				'id' => $GLOBALS['egw_info']['user']['account_id'],
				'name' => $GLOBALS['egw_info']['user']['account_fullname'],
				'avatar' => 'account:'.$GLOBALS['egw_info']['user']['account_id'],
				'room' => $data['room']
			]);
		}
		$n = new \notifications();
		$n->set_receivers([$GLOBALS['egw_info']['user']['account_id']]);
		$n->set_sender($data['caller']['account_id']);
		$n->set_subject(lang("Missed call from %1", $data['caller']['name']));
		$n->set_popupdata('status', ['caller'=>$data['caller']['account_id'], 'app' => 'status', 'onSeenAction' => 'app.status.refresh()']);
		$n->set_message(lang("You have a missed call from %1", $data['caller']['name']));
		$n->send();
	}

	/**
	 * Generates a full working meeting Url
	 * @param $room string room id
	 * @param $context array user data
	 * @param $extra array extra url options
	 *
	 * @return mixed
	 */
	public static function genMeetingUrl ($room, $context, $extra = [])
	{
		$backend = self::_getBackendInstance($room, [
			'user' => $context
		]);
		if (method_exists($backend, 'setStartAudioOnly')) $backend->setStartAudioOnly($extra['audioonly']);
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
	 * @param boolean $npn no picked up notification, prevents caller from getting
	 * notification about callee response state
	 *
	 * @throws Api\Json\Exception
	 */
	public static function pushCall ($call, $callee, $caller, $npn = false)
	{
		$p = new Api\Json\Push($callee);
		$p->call('app.status.receivedCall',[
			'call' => $call,
			'caller' => $caller,
			'room' => self::fetchRoomFromUrl($call),
			'npn' => $npn //no picked up notification
		]);
	}

	/**
	 * retrives room id from a given full url
	 * 
	 * @param type $url
	 * @return type
	 */
	public static function fetchRoomFromUrl ($url)
	{
		if ($url)
		{
			$parts = explode('?jwt=', $url);
			if (is_array($parts)) $parts = explode('/', $parts[0]);
		}
		return is_array($parts) ? array_pop($parts) : "";
	}
}