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
	 * debug mode
	 */
	const DEBUG = false;

	/**
	 * messages
	 */
	const MSG_MEETING_IN_THE_PAST = 'This meeting is no longer valid because it is in the past!';
	const MSG_ROOM_IS_NOT_READY = 'Room is not yet ready!';

	/**
	 * Call function
	 * @param array $_data
	 * @param string|null $_room optional room id in order initiate a call within an
	 * existing session
	 * @param bool $_npn no picked up notification,prevents caller from getting
	 * notification about callee response state. It make sense to be switched on
	 * when inviting to an existing session
	 * @param bool $_is_invite_to
	 * @throws
	 */
	public static function ajax_video_call($_data=[], $_room = null, $_npn = false, $_is_invite_to = false)
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
		foreach ($_data as $p)
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
		$CallerUrl = self::genMeetingUrl($room, $caller, ['audioonly' => $_data[0]['audioonly'], 'participants'=> $participants]);
		$CalleeUrl = '';
		foreach ($_data as $user) {
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
	 * Sends full working meeting Url to client
	 * @param string $room room id
	 * @param array $context user data
	 * @param null $start
	 * @param null $end
	 * @param array $extra extra parameteres
	 * @return void;
	 * @throws Api\Json\Exception|Api\Exception
	 */
	public static function ajax_genMeetingUrl(string $room, array $context=[], $start=null, $end=null, array $extra=[])
	{
		$respose = Api\Json\Response::get();
		$now = \calendar_boupdate::date2ts((new Api\DateTime('now'))->setServer());
		$start = \calendar_boupdate::date2ts((new Api\DateTime($start))->setServer());
		$end = \calendar_boupdate::date2ts((new Api\DateTime($end))->setServer());
		if ($now > $end)
		{
			$respose->data(['err'=>self::MSG_MEETING_IN_THE_PAST]);
			return;
		}
		if (empty($context['avatar'])) $context['avatar'] = (string)(new Api\Contacts\Photo('account:' . $context['account_id'], true));
		$context['position'] = self::isModerator($room, $context['account_id'].":".$context['cal_id']) ? 'caller' : 'callee';
		$respose->data(['url' => self::genMeetingUrl($room, $context, $extra, $start, $end)]);
	}

	/**
	 * Sets missed call notification
	 *
	 * @param array $data
	 * @throws Api\Json\Exception
	 */
	public static function ajax_setMissedCallNotification(array $data=[])
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

		try {
			$n->send();
		}
		catch(\Exception $e)
		{
			if (self::DEBUG) error_log(__METHOD__."()".$e->getMessage());
		}
	}

	/**
	 * Check available resources
	 *
	 * @param string $_room
	 * @param int|null $_start
	 * @param int|null $_end
	 * @param array $_participants
	 * @param bool $_is_invite_to
	 * @throws NoResourceAvailable
	 * @return bool|int return cal_id
	 */
	private static function checkResources(string $_room, $_start=null, $_end=null, $_participants=[], $_is_invite_to=false)
	{
		$backend = self::_getBackendInstance(0, []);
		if (method_exists($backend, 'checkResources'))
		{
			try {
				return $backend->checkResources($_room, $_start, $_end, $_participants, $_is_invite_to);
			}
			catch (NoResourceAvailable $e)
			{
				throw $e;
			}

		}
		return false;
	}

	/**
	 * Check if the user is a moderator of room
	 *
	 * @param string $room
	 * @param string|int $id
	 * @return bool return true if is moderator
	 */
	private static function isModerator(string $room, $id)
	{
		$backend = self::_getBackendInstance(0, []);
		if (method_exists($backend, 'isModerator'))
		{
			return $backend->isModerator($room, $id);
		}
		return false;
	}

	/**
	 * Generates a full working meeting Url
	 * @param string $room room id
	 * @param array $context user data
	 * @param array $extra extra url options
	 * @param int|DateTime|null $start start time, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int|DateTime|null $end expriation time, default now plus gracetime of self::EXP_GRACETIME=1h
	 *
	 * @return mixed
	 */
	public static function genMeetingUrl (string $room, array $context=[], $extra = [], $start=null, $end=null)
	{
		$backend = self::_getBackendInstance($room, [
			'user' => $context,
			'extra' => $extra
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
		return str_replace('/' , '',
			preg_replace('/:\d+$/', '', Api\Header\Http::host()).'-'.
			Api\Auth::randomstring(20));
	}

	/**
	 * Factory
	 *
	 * @param string $room room-id
	 * @param array $context values for keys 'name', 'email', 'avatar', 'account_id'
	 * @param int|DateTime|null $start start timestamp, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int|DateTime|null $end expriation timestamp, default now plus gracetime of self::EXP_GRACETIME=1h
	 *
	 * @return bool|Backends\Jitsi|Backends\Iface
	 */
	private static function _getBackendInstance(string $room, array $context=[], $start=null, $end=null)
	{
		$config = Api\Config::read('status');
		$backend = 	$config['videoconference']['backend'] ? $config['videoconference']['backend'][0] : 'Jitsi';
		if (!in_array($backend, self::BACKENDS) || $config['videoconference']['disable'] === true) return false;
		$instance = '\\EGroupware\\Status\\Videoconference\\Backends\\'.$backend;

		return new $instance($room, $context, $start, $end);
	}

	/**
	 * @param string $call call url
	 * @param string $callee account id of callee
	 * @param array $caller info about caller
	 * @param boolean $npn no picked up notification, prevents caller from getting
	 * notification about callee response state
	 *
	 * @throws Api\Json\Exception
	 */
	public static function pushCall (string $call, string $callee, array $caller, $npn = false)
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
	 * @param string $url
	 * @return string
	 */
	public static function fetchRoomFromUrl (string $url)
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
		$response = Api\Json\Response::get();
		$params = [];
		if (method_exists($backend, 'deleteRoom'))
		{
			if($url)
			{
				parse_str(parse_url($url)['query'], $params);
			}
			$backend->deleteRoom($params);
		}
		$response->data([]);
	}
}
