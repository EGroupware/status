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


	/**
	 * Status items
	 *
	 * @return array returns an array of status items as sorted based on fav preferences
	 *
	 * @todo favorites and sorting result
	 */
	public static function statusItems ()
	{
		$status = [];
		$hooks = Api\Hooks::implemented('status-getStatus');
		foreach($hooks as $app)
		{
			$s = Api\Hooks::process(['location'=>'status-getStatus', 'app'=>$app], $app);
			$status = array_merge_recursive ($status, $s[$app]);
		}


		//TODO: consider favorites and sorting orders
		foreach ($status as &$s)
		{
			if (is_array($s['id'])) $s['id'] = $s['id'][0];
			$result [] = $s;
		}
		return $result;
	}

	/**
	 * Get status
	 * @param array $data info regarding the running hook
	 *
	 * @return array returns an array of users with their status
	 *
	 * Status array structure:
	 * [
	 *		[id] => [
	 *			'id' => account_lid,
	 *			'account_id' => account_id,
	 *			'icon' => Icon to show as avatar for the item,
	 *			'hint' => Text to show as tooltip for the item,
	 *			'stat' => [
	 *				[status id] => [
	 *					'notifications' => An integer number representing number of notifications,
	 *										this is an aggregation value which might gets added up
	 *										with other stat id related to the item.
	 *					'active' => int value to show item activeness
	 *				]
	 *			]
	 *		]
	 * ]
	 *
	 * An item example:
	 * [
	 *		'hn' => [
	 *			'id' => 'hn',
	 *			'account_id' => 7,
	 *			'icon' => Api\Egw::link('/api/avatar.php', [
	 *				'contact_id' => 7,
	 * 				'etag' => 11
	 * 			]),
	 *			'hint' => 'Hadi Nategh (hn@egroupware.org)',
	 *			'stat' => [
	 *				'status' => [
	 *					'notifications' => 5,
	 *					'active' => 1
	 *				]
	 *			]
	 *		]
	 * ]
	 *
	 */
	public static function getStatus ($data)
	{
		if ($data['app'] != self::APPNAME) return [];

		$stat = $rows = $readonlys = $users = $onlines = [];
		$accesslog = new \admin_accesslog();
		$contact_obj = new Api\Contacts();
		$total = $accesslog->get_rows(array('session_list' => true), $rows, $readonlys);
		if ($total > 0)
		{
			unset($rows['no_lo'], $rows['no_total']);
			foreach ($rows as $row)
			{
				if ($row['account_id'] == $GLOBALS['egw_info']['user']['account_id']) continue;
				$id = Api\Accounts::id2name($row['account_id'], 'account_lid');
				$onlines [$id] = (int)(time() - $row['li']);
			}
		}
		\admin_ui::get_users([
			'filter' => 'accounts',
			'order' => 'account_lastlogin',
			'sort' => 'DESC',
			'active' => true
		], $users);

		foreach ($users as $user)
		{
			if ($user['account_id'] == $GLOBALS['egw_info']['user']['account_id']) continue;

			$contact = $contact_obj->read('account:'.$user['account_id'], true);
			$id = self::getUserName($user['account_lid']);
			if ($id)
			{
				$stat [$id] = [
					'id' => $id,
					'account_id' => $user['account_id'],
					'icon' => Api\Egw::link('/api/avatar.php', [
						'contact_id' => $contact['id'],
						'etag' => $contact['etag']
					]),
					'hint' => $contact['n_given']. ' ' . $contact['n_family'],
					'stat' => [
						'status' => [
							'active' => $onlines[$id],
							'bg' => 'api/templates/default/images/logo64x64.png'
						]
					]
				];
			}
		}
		uasort ($stat, function ($a ,$b){
			if ($a['stat']['egw']['active'] == $b['stat']['egw']['active'])
			{
				return 0;
			}
			return ($a['stat']['egw']['active'] < $b['stat']['egw']['active']) ? 1 : -1;
		});
		return $stat;
	}

	/**
	 * get actions
	 *
	 * @return array return an array of actions
	 */
	public static function get_actions ()
	{
		return [
			'fav' => [
				'caption' => 'Add to favorites',
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.status.handle_actions'
			],
			'unfavorite' => [
				'caption' => 'Remove from favorites',
				'allowOnMultiple' => false,
				'enabled' => false,
				'onExecute' => 'javaScript:app.status.handle_actions'
			]
		];
	}

	/**
	 * Get all implemented stat keys
	 * @return type
	 */
	public static function getStatKeys ()
	{
		return Api\Hooks::implemented('status-getStatus');
	}

	/**
	 * populates $settings for the Api\Preferences
	 *
	 * @return array
	 */
	static function settings() {

		$sel_options = self::getStatKeys();
		/* Settings array for this app */
		$settings = array(
			'status0' => array(
				'type'   => 'select',
				'label'  => 'Status indicator no. 1 (top left corner)',
				'name'   => 'status0',
				'values' => $sel_options,
				'help'   => '',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'status',
			),
			'status1' => array(
				'type'   => 'select',
				'label'  => 'Status indicator no. 2 (bottom left corner)',
				'name'   => 'status1',
				'values' => $sel_options,
				'help'   => '',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '',
			),
			'status2' => array(
				'type'   => 'select',
				'label'  => 'Status indicator no. 3 (top right corner)',
				'name'   => 'status2',
				'values' => $sel_options,
				'help'   => '',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '',
			),
			'status3' => array(
				'type'   => 'select',
				'label'  => 'Status indicator no. 4 (bottom right corner)',
				'name'   => 'status3',
				'values' => $sel_options,
				'help'   => '',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '',
			)
		);
		return $settings;
	}

	/**
	 * Get username from account_lid
	 *
	 * @param type $_user = null if user given then use user as account lid
	 * @return string return username
	 */
	public static function getUserName($_user = null)
	{
		return $_user ? $_user : $GLOBALS['egw_info']['user']['account_lid'];
	}
}
