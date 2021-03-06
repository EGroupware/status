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

use BigBlueButton\Parameters\DeleteRecordingsParameters;
use BigBlueButton\Parameters\EndMeetingParameters;
use BigBlueButton\Parameters\GetRecordingsParameters;
use EGroupware\Api\Config;
use BigBlueButton\BigBlueButton;
use BigBlueButton\Parameters\CreateMeetingParameters;
use BigBlueButton\Parameters\JoinMeetingParameters;
use EGroupware\Api\Exception;
use EGroupware\Status\Hooks;
use EGroupware\Status\Videoconference\Call;
use EGroupware\Status\Videoconference\Exception\NoResourceAvailable;
use EGroupware\OpenID\Token;
use EGroupware\Api;

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

	/*
	 * Default config value of extra invites
	 */
	private const EXTRA_INVITES_DEFAULT = 2;

	/**
	 * Constructor
	 *
	 * @param string $_room room-id
	 * @param array $_context values for keys 'name', 'email', 'avatar', 'account_id'
	 * @param int|null $_start start UTC timestamp, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int|null $_end expiration UTC timestamp, default now plus gracetime of self::EXP_GRACETIME=1h
	 *
	 * @return void
	 * @throws Exception
	 *
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
		putenv('BBB_SERVER_BASE_URL='.(substr($this->config['bbb_domain'], -4) === "/api" ?
				substr($this->config['bbb_domain'],0,-3):$this->config['bbb_domain']));
		$now = time();
		$start = $_start??$now;
		$end = $_end??$start+($this->config['bbb_call_duration']);
		$duration = $_end ? ($end - $start) / 60 : $end - $start;
		$this->isUserModerator = self::isModerator($room, $_context['user']['account_id'].':'.$_context['user']['cal_id']);
		$this->bbb = new BigBlueButton();

		if (Call::DEBUG)
		{
			error_log(__METHOD__.__LINE__."() room=".$_room." context=".array2string($_context)." start=".$start." end=".$end."isModerator=".$this->isUserModerator);
		}

		// Meeting params
		$this->meetingParams = new CreateMeetingParameters($room, $_context['user']['title']??lang('direct call from %1', $_context['user']['name']));
		$this->meetingParams->setAttendeePassword(md5($room.$this->config['bbb_api_secret']));
		$this->meetingParams->setDuration($this->config['bbb_call_fixed_duration']?$duration: 0);

		//Set recordings params
		$this->meetingParams->setRecord(!$this->config['disable_recordings']);
		$this->meetingParams->setAllowStartStopRecording(!$this->config['disable_recordings']);


		if (!empty($_context['extra']['participants'])) $this->meetingParams->setMaxParticipants(count($_context['extra']['participants'])+($this->config['bbb_call_extra_invites']??self::EXTRA_INVITES_DEFAULT));
		if ($start <= $now && $now <= $end && ($meeting = $this->bbb->getMeetingInfo($this->meetingParams)) && $meeting->success())
		{
			if ($this->isUserModerator)
			{
				$this->moderatorPW = $meeting->getMeeting()->getModeratorPassword();
			}
			return;
		}
		elseif(!$_context['user']['notify_only'] && $this->isUserModerator && ($start <= $now || $now + $this->config['bbb_call_preparation'] * 60 >= $start) && $now <= $end)
		{
			$token = new Token();
			$jwt = $token->accessToken('BBB', ['videoconference'], 'PT1H',
				false, null, ['context'=> array_merge([
					'room' => $room,
					'account_lid' => Api\Accounts::id2name($_context['user']['account_id'])
				], $_context['user'])]);
			// set end callback url
			$this->meetingParams->setEndCallbackUrl(Api\Framework::getUrl($GLOBALS['egw_info']['server']['webserver_url'].'/status/endCallback.php?jwt='.$jwt));
			// set recordings ready callback url
			$this->meetingParams->setRecordingReadyCallbackUrl(Api\Framework::getUrl($GLOBALS['egw_info']['server']['webserver_url'].'/status/recordingReadyCallback.php'));
			try {
				$response = $this->bbb->createMeeting($this->meetingParams);

				if (Call::DEBUG)
				{
					error_log(__METHOD__.__LINE__."Meeting created=".array2string($this->meetingParams)." user=".array2string($_context['user']));
				}
			}catch(\Exception $e)
			{
				throw new Exception(lang('Communicating with server %1 failed because of %2',$this->config['bbb_domain'], $e->getMessage()));
			}
			if ($response->getReturnCode() === 'FAILED') {
				throw new Exception($response->getMessage());
			}
			$this->moderatorPW = $response->getModeratorPassword();
		}
		// users invited as an external invited users via calendar (email addresses)
		elseif (self::isAnExternalUser($_context['user']['account_id']))
		{
			return; //simply return which would let getMeetingUrl do the rest to create the link
		}
		else
		{
			$this->roomNotReady = [
				'error' => ($start <= $now && $now <= $end) ? lang(Call::MSG_ROOM_NOT_CREATED) :
					($now > $end ? lang(Call::MSG_MEETING_IN_THE_PAST) : lang(Call::MSG_ROOM_IS_NOT_READY)),
				'meetingID' => $room,
				'cal_id' => $_context['user']['cal_id'],
				'start' => $start,
				'end' => $end
			];
			if ($this->isUserModerator) $this->roomNotReady['preparation'] =  $this->config['bbb_call_preparation'] * 60;

			if (Call::DEBUG)
			{
				error_log(__METHOD__.__LINE__."Room not ready=".array2string($this->roomNotReady)." user=".array2string($_context['user']));
			}
		}
	}

	/**
	 * @param array|null $context
	 * @return string
	 */
	public function getMeetingUrl ($context=null)
	{
		if (is_array($this->roomNotReady))
		{
			return Api\Framework::getUrl(Api\Framework::link('/index.php',
				array_merge([
					'menuaction' => 'status.EGroupware\\Status\\Ui.room',
				], $this->roomNotReady)));
		}
		$meetingParams = new JoinMeetingParameters($this->meetingParams->getMeetingId(), $context['name'], $this->isUserModerator?
			$this->moderatorPW:$this->meetingParams->getAttendeePassword());
		$meetingParams->setCustomParameter('isModerator', $this->isUserModerator);
		if (!empty($context['cal_id'])) $meetingParams->setCustomParameter('cal_id', $context['cal_id']);
		$meetingParams->setRedirect(true);
		return $this->bbb->getJoinMeetingURL($meetingParams);
	}

	/**
	 * Check resources
	 *
	 * @param string $_room
	 * @param array $_params
	 * 	- start
	 * 	- end
	 * 	- participants
	 * 	- invitation
	 * @return int cal_id returns cal_id if successful and throw exceptions otherwise
	 * @throws NoResourceAvailable|Exception
	 */
	public function checkResources($_room='', $_params = [])
	{
		$res_id = Hooks::getVideoconferenceResourceId();
		$config = Config::read('status');
		$cal = new \calendar_boupdate();
		$resources = new \resources_bo($GLOBALS['egw_info']['user']['account_id']);
		$message = lang('There is no free seats left to make/join this call!');
		$cal_res_index = "r".$res_id;
		$url_params = self::fetchParamsFromUrl($_room);
		$start = $_params['start'] ? new Api\DateTime($_params['start']) : new Api\DateTime('now');

		if ($_params['end'])
		{
			$end = new Api\DateTime($_params['end']);
		}
		else
		{
			$end = new Api\DateTime($start->format('ts'));
			$end->add(((int)$config['videoconference']['bbb']['bbb_call_duration']*60).' seconds');
		}

		$room = parse_url($_room)['query'] ? self::fetchRoomFromUrl($_room) : $_room;
		$num_participants = $_params['invitation']?count($_params['participants'])-1:count($_params['participants']);
		$_params['participants'][$cal_res_index] =  "A".$num_participants;

		// check resources on the given time period and throw exception when there's no resource left
		$resource = $resources->checkUseable($res_id, $start, $end, true);
		if ($resource['useable'] < $num_participants)
		{
			throw new NoResourceAvailable($message);
		}

		$cal_events = $url_params['cal_id'] ? $cal->read([$url_params['cal_id']], null, true) : [];
		if (is_array($cal_events[$url_params['cal_id']]))
		{
			$event = $cal_events[$url_params['cal_id']];
			$event['participants'] = $_params['participants'];
		}
		else // create a new event (it happens in direct calls)
		{
			$names = [];
			foreach ($_params['participants'] as $u => $p)
			{
				if (is_numeric($u) && $u !=  $GLOBALS['egw_info']['user']['account_id'])
				{
					$names[] = Api\Accounts::id2name($u, 'account_fullname');
				}
			}
			$event = [
				'title' => lang("video call: %1 to", $GLOBALS['egw_info']['user']['account_fullname'])." ".implode(',', $names),
				'##videoconference' => $room,
				'start' => $start,
				'end' => $end,
				'participants' => $_params['participants'],
				'owner' => $GLOBALS['egw_info']['user']['account_id'],
				'participant_types' => ['r', $res_id],
				'category' => $config['status_cat_videocall']
			];
			$cal_events = $cal->update($event, false, false, true, true, $msg, true);
		}

		if (is_array($cal_events))
		{
			foreach ($cal_events as $r) {
				// add newly added participant for the event
				if ($r['id'] == $event['id'] && $event['start']>=$r['start'] && $event['start'] <= $r['end'])
				{
					$n = (int)(substr($r['participants'][$cal_res_index] ,1))
						+ (int)(substr($event['participants'][$cal_res_index],1));
					$event['participants'] += $r['participants'];
					$event['participants'][$cal_res_index] = "A".$n;
				}
			}

			// update the event after participants update
			$cal_events = $cal->update($event, true, false, true);
		}
		// there's still a confilict array then throw exception
		if (is_array($cal_events)) throw new NoResourceAvailable($message);
		// returns cal_id if successful
		return $cal_events;
	}

	/**
	 * Check if the user is an external user
	 * @param string $_id account_id
	 * @return bool
	 */
	public static function isAnExternalUser($_id='')
	{
		return (bool)filter_var($_id, FILTER_VALIDATE_EMAIL);
	}

	/**
	 * Check if the user is moderator of room
	 * @param ?string $_room not used
	 * @param string $_id account_id:cal_id
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function isModerator(?string $_room=null, $_id='')
	{
		$id = explode(':', $_id);
		if (!empty($id[1]))
		{
			$cal = new \calendar_boupdate();

			$event = $cal->read($id[1]);

			foreach ($event['participants'] as $user => $participant)
			{
				if ($user == $id[0] && strpos($participant, 'CHAIR') !== FALSE) return true;
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
	public static function freeUpResource($cal_id='', $room='')
	{
		$cal = new \calendar_boupdate();
		$res_id = Hooks::getVideoconferenceResourceId();
		$event = $cal->read($cal_id);
		unset($event['participants']['r'.$res_id]);
		$res = ($event['##videoconference'] == $room) ? $cal->update($event, true, false, false) : false;

		if (!is_numeric($res) && !$res === true)
		{
			throw new Exception('freeing up resource from cal_id='.$cal_id.' failed!');
		}
	}

	public function isMeetingValid()
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
		if (!$params || $params['password'] !== $meetingInfo->getMeeting()->getModeratorPassword()) return;
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

	/**
	 * Fetsh all params from given url
	 * @param string $url
	 * @return array return parameteres
	 */
	public static function fetchParamsFromUrl($url='')
	{
		parse_str(parse_url($url)['query'], $params);
		return $params;
	}

	/**
	 * Get recordings
	 *
	 * @param array $params values used for getting specific recordings
	 * @param bool $fetchall
	 * @return array returns an array of records or empty array.
	 */
	public function getRecordings(array $params, bool $fetchall=false)
	{
		$recordingParams = new GetRecordingsParameters();
		$meetingId = $params['meetingID']?? $this->meetingParams->getMeetingId();

		// prevents from fetching all recordings when there's no meetingId given and fetchall is not being requested
		// delibertly.
		if (!$meetingId && !$fetchall) return ['error' => lang('No valid meetingId given.')];

		$recordingParams->setMeetingId($fetchall?'':$meetingId);
		$records = $this->bbb->getRecordings($recordingParams);
		$result = [];
		if ($records->success() && !empty($records->getRecords()))
		{
			try
			{
				foreach ($records->getRecords() as $r)
				{
					$result[] = [
						'recordid' => $r->getRecordId(),
						'room' => $r->getMeetingId(),
						'name' => $r->getName(),
						'isPublished' => $r->isPublished(),
						'state' => $r->getState(),
						'url' => $r->getPlaybackUrl(),
						'type' => $r->getPlaybackType(),
						'starttime' => new Api\DateTime($r->getStartTime()/1000),
						'endtime' => new Api\DateTime($r->getEndTime()/1000)
					];
				}
			} catch (Exception $e)
			{
				error_log(__METHOD__ . '()' . $e->getMessage());
			}
		}
		else
		{
			$result['error'] = $records->getMessage();
		}
		return $result;
	}

	/**
	 * Delete recording
	 * @param $_params
	 * @return array
	 */
	public function deleteRecordings($_params)
	{
		$result = [];
		$meetingId = $_params['meetingID']?? $this->meetingParams->getMeetingId();
		if (!$_params['recordid']) return ['error' => lang('No valid recordid found!')];
		if (!self::isModerator($meetingId, $GLOBALS['egw_info']['user']['account_id'].':'.$_params['cal_id']))
		{
			$result['error'] = lang('Access denied!');
			return $result;
		}
		$recordingParams = new DeleteRecordingsParameters($_params['recordid']);
		$result = $this->bbb->deleteRecordings($recordingParams);
		return $result->success() ? ['success' => $result->success()] : ['error' => $result->getMessage()];
	}
}
