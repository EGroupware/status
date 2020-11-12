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
use EGroupware\Status\Videoconference\Exception\NoResourceAvailable;

class Call
{
	/**
	 * Backend modules class name
	 */
	const BACKENDS = ['Jitsi', 'BBB'];

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
	public static function ajax_video_call($data, $_room = null, $_npn = false, $_is_invite_to = false)
	{
		$response = Api\Json\Response::get();
		$caller = [
			'name' => $GLOBALS['egw_info']['user']['account_fullname'],
			'email' => $GLOBALS['egw_info']['user']['account_email'],
			'avatar' => (string)(new Api\Contacts\Photo('account:' . $GLOBALS['egw_info']['user']['account_id'], true)),
			'account_id' => $GLOBALS['egw_info']['user']['account_id'],
			'position' => 'caller'
		];
		// set the owner of the call event
		$participants = [$caller['account_id']=>'ACHAIR'];
		foreach ($data as $p)
		{
			// set all participants
			$participants[$p['id']] = 'A';
		}
		$room = $_room?? self::genUniqueRoomID();
		try {
			$cal_id = self::checkResources($room, null, null, $participants, $_is_invite_to);
			if (is_numeric($cal_id)) $caller['cal_id'] = $cal_id;
		}
		catch(NoResourceAvailable $e)
		{
			$response->data(['msg' => ['message' => $e->getMessage(), 'type'=>'error']]);
			return;
		}
		$CallerUrl = self::genMeetingUrl($room, $caller, ['audioonly' => $data[0]['audioonly'], 'participants'=> $participants]);
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
				'account_id' => $user['id'],
				'position' => 'callee'
			];
			$CalleeUrl = self::genMeetingUrl($room, $callee, ['audioonly' => $user['audioonly'], 'participants' => $participants]);
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
		$n->set_message(Api\DateTime::to().": ".lang("You have a missed call from %1", $data['caller']['name']));
		$n->send();
	}

	/**
	 * Check available resources
	 *
	 * @param $_room
	 * @param $_start
	 * @param $_end
	 * @param $_participants
	 * @param $_is_invite_to
	 * @throws NoResourceAvailable
	 * @return return cal_id
	 */
	private static function checkResources($_room, $_start, $_end, $_participants, $_is_invite_to)
	{
		$backend = self::_getBackendInstance(0, []);
		if (method_exists($backend, 'checkResources'))
		{
			return $backend->checkResources($_room, $_start, $_end, $_participants, $_is_invite_to);
		}
	}

	/**
	 * Generates a full working meeting Url
	 * @param $room string room id
	 * @param $context array user data
	 * @param $extra array extra url options
	 * @param int|DateTime $start start time, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int|DateTime $end expriation time, default now plus gracetime of self::EXP_GRACETIME=1h
	 *
	 * @return mixed
	 */
	public static function genMeetingUrl ($room, $context, $extra = [], $start=null, $end=null)
	{
		$backend = self::_getBackendInstance($room, [
			'user' => $context
		], $start instanceof \DateTime ? $start->getTimestamp() : $start,
			$end instanceof \DateTime ? $end->getTimestamp() : $end);

		if (method_exists($backend, 'setStartAudioOnly')) $backend->setStartAudioOnly($extra['audioonly']);

		return $backend->getMeetingUrl($context);
	}

	/**
	 * Generates a unique room ID
	 * @return string
	 * @throws \Exception
	 */
	public static function genUniqueRoomID()
	{
		return str_replace('/' , '',  Api\Header\Http::host().'-'.Api\Auth::randomstring(20));
	}

	/**
	 * Factory
	 *
	 * @param string $room room-id
	 * @param array $context values for keys 'name', 'email', 'avatar', 'account_id'
	 * @param int|DateTime $start start timestamp, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int|DateTime $end expriation timestamp, default now plus gracetime of self::EXP_GRACETIME=1h
	 *
	 * @return Backends\Jitsi|Backends\Iface
	 */
	private static function _getBackendInstance($room, $context, $start=null, $end=null)
	{
		$config = Api\Config::read('status');
		$backend = 	$config['videoconference']['backend'] ? $config['videoconference']['backend'][0] : 'Jitsi';
		if (!in_array($backend, self::BACKENDS) || $config['videoconference']['disable'] === true) return false;
		$instance = '\\EGroupware\\Status\\Videoconference\\Backends\\'.$backend;

		return new $instance($room, $context, $start, $end);
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
		$backend = self::_getBackendInstance(0,[]);
		return $backend::fetchRoomFromUrl($url);
	}

	/**
	 * Get a regex that we can use to recognize "our" calls
	 */
	public static function getMeetingRegex()
	{
		$regex = 'https://.*(\r?\n)*';
		$backend = self::_getBackendInstance(0,[]);

		if (method_exists($backend, 'getRegex'))
		{
			$regex = $backend->getRegex();
		}

		return $regex;
	}

	/**
	 * Ajax function to call a room to end
	 * @param $room
	 * @param $url
	 */
	static function ajax_deleteRoom($room, $url)
	{
		$backend = self::_getBackendInstance($room, []);
		if (method_exists($backend, 'deleteRoom'))
		{
			if($url)
			{
				parse_str(parse_url($url)['query'], $params);
			}
			$backend->deleteRoom($params, false);
		}
	}
}