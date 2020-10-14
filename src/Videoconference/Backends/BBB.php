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

use EGroupware\Api\Config;
use BigBlueButton\BigBlueButton;
use BigBlueButton\Parameters\CreateMeetingParameters;

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
	 * Constructor
	 *
	 * @param string $_room room-id
	 * @param array $_context values for keys 'name', 'email', 'avatar', 'account_id'
	 * @param int $_start start timestamp, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int $_end expiration timestamp, default now plus gracetime of self::EXP_GRACETIME=1h
	 */
	public function __construct($_room, $_context, $_start=null, $_end=null)
	{
		$config = Config::read('status');
		$this->config = $config['videoconference']['bbb'];
		//todo: set BBB_SECRET and BBB_API_URL
		$this->bbb = new BigBlueButton();
		$this->meetingParams = new CreateMeetingParameters($_room, $_context['user']['name']);
		$response = $this->bbb->createMeeting($this->meetingParams);
		if ($response->getReturnCode() == 'FAILED') {
			return 'Can\'t create room!';
		} else {
			//TODO
		}
	}

	public function getMeetingUrl ()
	{
		$meetingParams = new JoinMeetingParameters($this->meetingParams->getMeetingId(), $this->meetingParams->getMeetingName());
		return $this->bbb->getJoinMeetingURL($meetingParams);
	}


	function isMeetingValid()
	{
		// TODO: Implement isMeetingValid() method.
		return true;
	}
}