<?php
/**
 * Hooks for Status app
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @package Status
 * @copyright (c) 2019 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Status;

use EGroupware\Api;

class Hooks {
	/**
	 * App name
	 * var string
	 */
	const APPNAME = 'status';


	public static function get_rows ()
	{
		$accesslog = new \admin_accesslog();
		$contact_obj = new Api\Contacts();
		$rows = $result = $onlineusers = $readonlys = $users = array ();

		\admin_ui::get_users(array(), $users);
		$total = $accesslog->get_rows(array('session_list' => true), $rows, $readonlys);
		if ($total > 0)
		{
			unset($rows['no_lo'], $rows['no_total']);
			foreach ($rows as $row)
			{
				if ($row['account_id'] == $GLOBALS['egw_info']['user']['account_id']) continue;
				$onlineusers [] = $row['account_id'];
			}
		}
		foreach ($users as $user)
		{
			$contact = $contact_obj->read('account:'.$user['account_id'], true);
			$result [] = array (
				'id' => $contact['id'],
				'stat1' => in_array($user['account_id'], $onlineusers)
			);
		}
		return $result;
	}
}
