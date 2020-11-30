<?php

/**
 * BigBlueButton backend
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @package Status
 * @copyright (c) 2020 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Status\Videoconference\Backends;

use BigBlueButton\Core\Meeting;
use BigBlueButton\Parameters\EndMeetingParameters;
use EGroupware\Api\Config;
use BigBlueButton\BigBlueButton;
use BigBlueButton\Parameters\CreateMeetingParameters;
use BigBlueButton\Parameters\JoinMeetingParameters;
use EGroupware\Api\Exception;
use EGroupware\Status\Hooks;
use EGroupware\Status\Videoconference\Exception\NoResourceAvailable;
use EGroupware\OpenID\Token;
use EGroupware\Api;
use EGroupware\Api\DateTime;

class BBB Implements Iface
{
	/**
	 * BigBlueButton Api object
	 * @var
	 */
	private $bbb;

	/**
	 * @var
	 */
	private $meetingParams;

	/**
	 * @var mixed
	 */
	private $config;

	/**
	 * @var string
	 */
	private $moderatorPW;

	/**
	 * @var bool
	 */
	private $isUserModerator;

	/**
	 * @var mixed
	 */
	private $roomNotReady;

	/**
	 * Constructor
	 *
	 * @param string $_room room-id
	 * @param array $_context values for keys 'name', 'email', 'avatar', 'account_id'
	 * @param int|null $_start start timestamp, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int|null $_end expiration timestamp, default now plus gracetime of self::EXP_GRACETIME=1h
	 *
	 * @throws Exception
	 * @return void|Meeting
	 */
	public function __construct($_room='', array $_context=[], $_start=null, $_end=null)
	{
		// don't go further if no room given
		if (!$_room) return;
		// try to resolve meetingID if the whole url is given as room
		$room = parse_url($_room)['query'] ? self::fetchRoomFromUrl($_room) : $_room;
		$config = Config::read('status');
		$this->config = $config['videoconference']['bbb'];
		putenv('BBB_SECRET='.$this->config['bbb_api_secret']);
		putenv('BBB_SERVER_BASE_URL='.$this->config['bbb_domain']);
		$now = \calendar_boupdate::date2ts(new DateTime('now'));
		$start = $_start??$now;
		$end = $_end??$start+($this->config['bbb_call_duration']);
		$duration = $_end ? ($end - $start) / 60 : $end - $start;
		$this->roomNotReady = null;
		$this->isUserModerator = self::isModerator($room, $_context['user']['account_id'].':'.$_context['user']['cal_id']);
		$this->bbb = new BigBlueButton();
		$this->meetingParams = new CreateMeetingParameters($room, $_context['user']['name']);
		$this->meetingParams->setAttendeePassword(md5($room.$this->config['bbb_api_secret']));
		$this->meetingParams->setDuration($this->config['bbb_call_fixed_duration']?$duration: 0);
		if (($meeting = $this->bbb->getMeetingInfo($this->meetingParams)) && $meeting->success() && $start <= $now)
		{
			if ($this->isUserModerator)
			{
				$this->moderatorPW = $meeting->getMeeting()->getModeratorPassword();
			}
			return $meeting->getMeeting();
		}
		elseif($this->isUserModerator && $start <= $now)
		{
			$token = new Token();
			$jwt = $token->accessToken('BBB', ['videoconference'], 'PT1H',
				false, null, ['context'=> array_merge([
					'room' => $room,
					'account_lid' => Api\Accounts::id2name($_context['user']['account_id'])
				], $_context['user'])]);

			$this->meetingParams->setEndCallbackUrl(Api\Framework::getUrl($GLOBALS['egw_info']['server']['webserver_url'].'/status/src/Videoconference/endCallback.php?jwt='.$jwt));
			$response = $this->bbb->createMeeting($this->meetingParams);
			if ($response->getReturnCode() == 'FAILED') {
				throw new Exception($response->getMessage());
			}
			$this->moderatorPW = $response->getModeratorPassword();
		}
		// users invited as an external invited users via calendar (email addresses)
		elseif (self::isAnExternalUser($_context['user']['account_id']))
		{
			return; //simply return which wpould let getMeetingUrl do the rest to create the link
		}
		else
		{
			$this->roomNotReady = [
				'error' => lang('Room is not yet ready!'),
				'meetingID' => $room,
				'cal_id' => $_context['user']['cal_id'],
				'start' => \calendar_boupdate::date2ts($start),
				'end' => $end
			];
		}
	}

	/**
	 * @param array|null $_context
	 * @return string
	 */
	public function getMeetingUrl ($_context=null)
	{
		if (is_array($this->roomNotReady))
		{
			return Api\Framework::getUrl(Api\Framework::link('/index.php',
				array_merge([
					'menuaction' => 'status.EGroupware\\Status\\Ui.room',
				], $this->roomNotReady)));
		}
		$meetingParams = new JoinMeetingParameters($this->meetingParams->getMeetingId(), $this->meetingParams->getMeetingName(), $this->isUserModerator?
			$this->moderatorPW:$this->meetingParams->getAttendeePassword());
		$meetingParams->setCustomParameter('isModerator', $this->isUserModerator);
		if (!empty($_context['cal_id'])) $meetingParams->setCustomParameter('cal_id', $_context['cal_id']);
		$meetingParams->setRedirect(true);
		return $this->bbb->getJoinMeetingURL($meetingParams);
	}

	/**
	 * Check resources
	 *
	 * @param string $_room
	 * @param int|null $_start
	 * @param int|null $_end
	 * @param array $_participants
	 * @param bool $_is_invite_to
	 * @throws NoResourceAvailable
	 * @return int cal_id
	 */
	public function checkResources($_room='', $_start=null, $_end=null, $_participants=[], $_is_invite_to=false)
	{
		$res_id = Hooks::getVideoconferenceResourceId();
		$config = Config::read('status');
		$resources = new \resources_bo($GLOBALS['egw_info']['user']['account_id']);
		$message = lang('There is no free seats left to make/join this call!');
		$cal_res_index = "r".$res_id;
		$start = $_start??time();
		$end = $_end??$start+((int)$config['videoconference']['bbb']['bbb_call_duration']*60);
		$room = parse_url($_room)['query'] ? self::fetchRoomFromUrl($_room) : $_room;
		$num_participants = $_is_invite_to?count($_participants)-1:count($_participants);
		$_participants[$cal_res_index] =  "A".$num_participants;
		$resource = $resources->checkUseable($res_id, $start, $end);

		$event = [
			'title' => $room,
			'##videoconference' => $room,
			'start' => $start,
			'end' => $end,
			'participants' => $_participants,
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'participant_types' => ['r', $res_id]
		];

		if ($resource['useable'] < $num_participants)
		{
			throw new NoResourceAvailable($message);
		}

		$cal = new \calendar_boupdate();

		$res = $cal->update($event, false, false, true);
		if (is_array($res))
		{
			foreach ($res as $r) {
				if ($r['title'] == $event['title'] && $event['start']>=$r['start'] && $event['start'] <= $r['end'])
				{
					$n = (intval(substr($r['participants'][$cal_res_index] ,1))
						+ intval(substr($event['participants'][$cal_res_index],1)));
					$event['id'] = $r['id'];
					$event['participants'] = $event['participants'] + $r['participants'];
					$event['participants'][$cal_res_index] = "A".$n;
				}
			}
			$res = $cal->update($event, true, false, true);
		}
		if (is_array($res)) throw new NoResourceAvailable($message);
		return $res;
	}

	/**
	 * Check if the user is an external user
	 * @param string $_id account_id
	 * @return bool
	 */
	public function isAnExternalUser($_id='')
	{
		return filter_var($_id, FILTER_VALIDATE_EMAIL)?true:false;
	}

	/**
	 * Check if the user is moderator of room
	 * @param string $_room not used
	 * @param string $_id account_id:cal_id
	 * @return bool
	 */
	public function isModerator($_room='', $_id='')
	{
		unset($_room); //neccesarry by func signature

		$id = explode(':', $_id);
		if (!empty($id[1]))
		{
			$cal = new \calendar_boupdate();

			$event = $cal->read($id[1]);

			foreach ($event['participants'] as $user => $participant)
			{
				if ($user == $id[0] && preg_match('/CHAIR/', $participant)) return true;
			}
		}
		return false;
	}

	/**
	 * free up resources bound to the call event
	 * @param string $cal_id
	 * @param string $room
	 * @throws Exception
	 */
	private static function freeUpResource($cal_id='', $room='')
	{
		$cal = new \calendar_boupdate();
		$res_id = Hooks::getVideoconferenceResourceId();
		$event = $cal->read($cal_id);
		unset($event['participants']['r'.$res_id]);
		$event['videoconference'] = false;
		$res = ($room == $event['title']) ? $cal->delete($cal_id, 0, false, true)
			: $cal->update($event, true, false, false);
		if (!is_numeric($res) && !$res === true)
		{
			throw new Exception('freeing up resource from cal_id='.$cal_id.' failed!');
		}
	}

	function isMeetingValid()
	{
		// TODO: Implement isMeetingValid() method.
		return true;
	}

	/**
	 * End meeting forcibly
	 * @param array $params
	 */
	public function deleteRoom(array $params)
	{
		$meetingInfo = $this->bbb->getMeetingInfo($this->meetingParams);
		if (!$params || $params['password'] != $meetingInfo->getMeeting()->getModeratorPassword()) return;
		$endMeetingParams = new EndMeetingParameters($params['meetingID'], $meetingInfo->getMeeting()->getModeratorPassword());
		try {
			$this->bbb->endMeeting($endMeetingParams);
			self::freeUpResource($params['cal_id'], $params['meetingID']);
		}
		catch (\Exception $e)
		{
			error_log(__METHOD__."()".$e->getMessage());
		}
	}

	/**
	 * Fetch room from url
	 *
	 * @param string $url
	 * @return string returns room id
	 */
	public static function fetchRoomFromUrl($url='')
	{
		parse_str(parse_url($url)['query'], $params);
		return $params['meetingID'];
	}
}