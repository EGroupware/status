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

		$this->bbb = new BigBlueButton();
		$this->meetingParams = new CreateMeetingParameters($room, $_context['user']['name']);
		$this->meetingParams->setAttendeePassword(md5($room.$this->config['bbb_api_secret']));

		if (($meeting = $this->bbb->getMeetingInfo($this->meetingParams)) && $meeting->success())
		{
			$response = $meeting->getMeeting();
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
		$meetingParams->setRedirect(true);
		return $this->bbb->getJoinMeetingURL($meetingParams);
	}

	private static function checkResources($res_id, $_start, $_end)
	{
		$resources = new \resources_bo($GLOBALS['egw_info']['user']['account_id']);
		return $resources->checkUseable($res_id, $_start, $_end);
	}

	private static function checkEvent($_roomid, $_res_id, $_start, $_end)
	{
		$cal = new \calendar_boupdate();
		$events = $cal->search([
			'start' => $_start,
			'end' => $_end,
			'users' => ['r'.$_res_id],
			'cfs' => ['#videoconference'],
			'ignore_acl' => true,
			'enum_groups' => false,
		]);
		if ($events)
		{
			foreach ($events as $event)
			{
				if ($event['##videoconference'] == $_roomid) return ['cal' => $cal, 'event' => $event];
			}
		}
		return ['cal' => $cal];
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
		if (!$params || $params['password'] != $meetingInfo->getMeeting()->getModeratorPassword()
			|| !$force && $meetingInfo->getMeeting()->getModeratorCount() > 1) return;
		$endMeetingParams = new EndMeetingParameters($params['meetingID'], $meetingInfo->getMeeting()->getModeratorPassword());
		$this->bbb->endMeeting($endMeetingParams);
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