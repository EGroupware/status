<?php
/**
 * Tests for the meeting url of the Jitsi backend
 *
 * The room id of a calendar event can be set by whoever created the event, eg. via CalDAV.
 * The url is used in mails, notifications and as javascript argument,
 * so the room must not add anything but a path segment to it.
 *
 * @link http://www.egroupware.org
 * @package status
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Status;

use EGroupware\Api\LoggedInTest;
use EGroupware\Status\Videoconference\Backends\Jitsi;
use EGroupware\Status\Videoconference\Call;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

class JitsiMeetingUrlTest extends LoggedInTest
{
	/**
	 * Path of the meeting url
	 *
	 * @param string $room
	 * @return string
	 */
	protected function path(string $room) : string
	{
		$url = (new Jitsi($room, ['user' => ['name' => 'Test']]))->getMeetingUrl();

		return preg_replace('/[?#].*$/s', '', $url);
	}

	/**
	 * A room id we generate ourselves is used unchanged
	 */
	public function testGeneratedRoomIsUnchanged() : void
	{
		$room = Call::genUniqueRoomID();

		$this->assertStringEndsWith('/'.$room, $this->path($room));
	}

	/**
	 * Characters with a meaning in urls, html or javascript are encoded
	 */
	public function testRoomIsEncoded() : void
	{
		$room = 'room");window.injected=1;//<b>\'?x=1#y';
		$path = $this->path($room);

		$this->assertStringEndsWith('/'.rawurlencode($room), $path);
		$this->assertDoesNotMatchRegularExpression('/["\'<>();\s]/', $path);
	}
}
