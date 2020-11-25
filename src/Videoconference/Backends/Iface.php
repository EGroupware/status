<?php
/**
 * video conference backend interface
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @package Status
 * @copyright (c) 2020 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
namespace EGroupware\Status\Videoconference\Backends;

interface Iface
{
	/**
	 * Constructor
	 * @param string $room room-id
	 * @param array $context values for keys 'name', 'email', 'avatar', 'account_id'
	 * @param int|null $start start timestamp, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int|null $end expriation timestamp, default now plus gracetime of self::EXP_GRACETIME=1h
	 */
	function __construct($room='', array $context, $start=null, $end=null);

	/**
	 * Generate meeting url
	 * @param array|null $context
	 * @return mixed
	 */
	function getMeetingURL($context=null);

	/**
	 * Check if meeting token is valid
	 * @return mixed
	 */
	function isMeetingValid();

	/**
	 * Resolve room id from full url
	 * @param string $url
	 * @return mixed
	 */
	static function fetchRoomFromUrl(string $url);

}