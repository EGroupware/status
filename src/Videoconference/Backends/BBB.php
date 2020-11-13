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

use BigBlueButton\Parameters\EndMeetingParameters;
use EGroupware\Api\Auth;
use EGroupware\Api\Config;
use BigBlueButton\BigBlueButton;
use BigBlueButton\Parameters\CreateMeetingParameters;
use BigBlueButton\Parameters\JoinMeetingParameters;
use EGroupware\Api\DateTime;
use EGroupware\Api\Exception;
use EGroupware\Status\Videoconference\Exception\NoResourceAvailable;


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
	 * Constructor
	 *
	 * @param string $_room room-id
	 * @param array $_context values for keys 'name', 'email', 'avatar', 'account_id'
	 * @param int $_start start timestamp, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int $_end expiration timestamp, default now plus gracetime of self::EXP_GRACETIME=1h
	 */
	public function __construct($_room, $_context, $_start=null, $_end=null)
	{
		// don't go further if no room given
		if (!$_room) return;
		// try to resolve meetingID if the whole url is given as room
		$room = parse_url($_room)['query'] ? self::fetchRoomFromUrl($_room) : $_room;
		$config = Config::read('status');
		$this->config = $config['videoconference']['bbb'];
		putenv('BBB_SECRET='.$this->config['bbb_api_secret']);
		putenv('BBB_SERVER_BASE_URL='.$this->config['bbb_domain']);
		$start = $_start??time();
		$end = $_end??$start+($this->config['bbb_call_duration']);

		$this->bbb = new BigBlueButton();
		$this->meetingParams = new CreateMeetingParameters($room, $_context['user']['name']);
		$this->meetingParams->setAttendeePassword(md5($room.$this->config['bbb_api_secret']));
		$this->meetingParams->setDuration($this->config['bbb_call_fixed_duration']?$end - $start: 0);
		if (($meeting = $this->bbb->getMeetingInfo($this->meetingParams)) && $meeting->success())
		{
			return $meeting->getMeeting();
		}
		else
		{
			$response = $this->bbb->createMeeting($this->meetingParams);
			if ($response->getReturnCode() == 'FAILED') {
				return 'Can\'t create room!';
			}
			$this->moderatorPW = $response->getModeratorPassword();
		}
	}

	public function getMeetingUrl ($_context=null)
	{
		$meetingParams = new JoinMeetingParameters($this->meetingParams->getMeetingId(), $this->meetingParams->getMeetingName(), $_context['position'] == 'callee'? $this->meetingParams->getAttendeePassword() : $this->moderatorPW);
		$meetingParams->setCustomParameter('isModerator', ($_context['position'] == 'caller' && $this->moderatorPW));
		if (!empty($_context['cal_id'])) $meetingParams->setCustomParameter('cal_id', $_context['cal_id']);
		$meetingParams->setRedirect(true);
		return $this->bbb->getJoinMeetingURL($meetingParams);
	}

	/**
	 * Check resources
	 *
	 * @param $_room
	 * @param $_start
	 * @param $_end
	 * @param $_participants
	 * @param $_is_invite_to
	 * @throws NoResourceAvailable
	 */
	public function checkResources($_room, $_start, $_end, $_participants, $_is_invite_to)
	{
		$config = Config::read('status');
		$resources = new \resources_bo($GLOBALS['egw_info']['user']['account_id']);
		$message = lang('There is no free seats left to make/join this call!');
		$cal_res_index = "r".$config['bbb_res_id'];
		$start = $_start??time();
		$end = $_end??$start+($config['videoconference']['bbb']['bbb_call_duration']);
		$room = parse_url($_room)['query'] ? self::fetchRoomFromUrl($_room) : $_room;
		$num_participants = $_is_invite_to?count($_participants)-1:count($_participants);
		$_participants[$cal_res_index] =  "A".$num_participants;
		$resource = $resources->checkUseable($config['bbb_res_id'], $start, $end);

		$event = [
			'title' => $room,
			'##videoconference' => $room,
			'start' => $start,
			'end' => $_end??$start+($config['videoconference']['bbb']['bbb_call_duration']),
			'participants' => $_participants,
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'participant_types' => ['r', $config['bbb_res_id']]
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
	 * free up resources bound to the call event
	 * @param $cal_id
	 * @throw Exception
	 */
	private static function freeUpResource($cal_id)
	{
		$cal = new \calendar_boupdate();
		$config = Config::read('status');
		$event = $cal->read($cal_id);
		unset($event['participants']['r'.$config['bbb_res_id']]);
		$event['videoconference'] = false;
		$res = $cal->update($event, true, false, false);
		if (!is_numeric($res))
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
	 * @param bool $force
	 */
	public function deleteRoom(array $params, $force=true)
	{
		$meetingInfo = $this->bbb->getMeetingInfo($this->meetingParams);
		if (!$params || $params['password'] != $meetingInfo->getMeeting()->getModeratorPassword()) return;
		$endMeetingParams = new EndMeetingParameters($params['meetingID'], $meetingInfo->getMeeting()->getModeratorPassword());
		try {
			$this->bbb->endMeeting($endMeetingParams);
			self::freeUpResource($params['cal_id']);
		}
		catch (\Exception $e)
		{
			error_log(__METHOD__."()".$e->getMessage());
		}
	}

	/**
	 * Fetch room from url
	 *
	 * @param $url
	 * return string returns room id
	 */
	public static function fetchRoomFromUrl($url)
	{
		parse_str(parse_url($url)['query'], $params);
		return $params['meetingID'];
	}
}