<?php
/**
 * Ui for Status app
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @package Status
 * @copyright (c) 2019 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Status;

use EGroupware\Api;

/**
 * Description of Ui
 *
 * @author hadi
 */
class Ui {

	/**
	 * Public functions
	 * @var array
	 */
	public $public_functions = [
		'index' => true
	];

	/*
	 *
	 */
	function index($content=null)
	{
		$tpl = new Api\Etemplate('status.index');
		
		$tpl->exec('status.EGroupware\\Status\\Ui.index', array(),array());
	}


	/**
	 * Get current online users
	 *
	 * @return array return an array of users info
	 */
	static function getOnlineUsers ()
	{
		$accesslog = new \admin_accesslog();
		$rows = $readonlys = $users = array ();
		$total = $accesslog->get_rows(array('session_list' => true), $rows, $readonlys);
		if ($total > 0)
		{
			unset($rows['no_lo'], $rows['no_total']);
			foreach ($rows as $row)
			{
				if ($row['account_id'] == $GLOBALS['egw_info']['user']['account_id']) continue;
				$users [] = array(
					'account_id' => $row['account_id'],
					'username' => Api\Accounts::username($row['account_id']),
					'email' => Api\Accounts::id2name($row['account_id'], 'account_email'),
				);
			}
		}
		return $users;
	}
}
