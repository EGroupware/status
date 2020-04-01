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
	function __construct($room, $context);

	function getMeetingURL();

	function isMeetingValid();

}