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
use EGroupware\Api\Config;
use EGroupware\OpenID\Entities\ClientEntity;
use EGroupware\OpenID\Repositories\ClientRepository;
use League\OAuth2\Server\Exception\OAuthServerException;
use resources_bo;

class Hooks
{
	/**
	 * App name
	 * var string
	 */
	public const APPNAME = 'status';

	public const DEFAULT_VIDEOCONFERENCE_BACKEND = 'Jitsi';

	public const SERVER_RESOURCE_PREFIX_NAME = 'Meeting room ';

	/**
	 * Status items
	 *
	 * @return array returns an array of status items as sorted based on fav preferences
	 */
	public static function statusItems()
	{
		$result = $status = [];
		$hooks = Api\Hooks::implemented('status-getStatus');
		foreach ($hooks as $app) {
			$s = Api\Hooks::process(['location' => 'status-getStatus', 'app' => $app], $app);
			if (!empty($s[$app])) $status = array_merge_recursive($status, $s[$app]);
		}

		foreach ($status as &$s) {
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
	 *        [id] => [
	 *            'id' => account_lid,
	 *            'account_id' => account_id,
	 *            'icon' => Icon to show as avatar for the item,
	 *            'hint' => Text to show as tooltip for the item,
	 *            'stat' => [
	 *                [status id] => [
	 *                    'notifications' => An integer number representing number of notifications,
	 *                                        this is an aggregation value which might gets added up
	 *                                        with other stat id related to the item.
	 *                    'active' => int value to show item activeness
	 *                ]
	 *            ]
	 *        ]
	 * ]
	 *
	 * An item example:
	 * [
	 *        'hn' => [
	 *            'id' => 'hn',
	 *            'account_id' => 7,
	 *            'icon' => Api\Egw::link('/api/avatar.php', [
	 *                'contact_id' => 7,
	 *                'etag' => 11
	 *            ]),
	 *            'hint' => 'Hadi Nategh (hn@egroupware.org)',
	 *            'stat' => [
	 *                'status' => [
	 *                    'notifications' => 5,
	 *                    'active' => 1
	 *                ]
	 *            ]
	 *        ]
	 * ]
	 *
	 * @throws \JsonException
	 */
	public static function getStatus(array $data)
	{
		if ($data['app'] != self::APPNAME) return [];

		$stat = [];

		$contact_obj = new Api\Contacts();

		Api\Cache::setSession(self::APPNAME, 'account_state', md5(json_encode($users = self::getUsers(), JSON_THROW_ON_ERROR)));

		foreach ($users as $user) {
			if (in_array($user['account_lid'], ['anonymous', $GLOBALS['egw_info']['user']['account_lid']])) {
				continue;
			}
			$contact = $contact_obj->read('account:' . $user['account_id'], true);
			$id = self::getUserName($user['account_lid']);
			if ($id) {
				$stat [$id] = [
					'id' => $id,
					'account_id' => $user['account_id'],
					'icon' => $contact['photo'],
					'hint' => $contact['n_given'] . ' ' . $contact['n_family'],
					'stat' => [
						'status' => [
							'active' => $user['online'],
							'lname' => $contact['n_family'],
							'fname' => $contact['n_given'],
							'tel_prefer' => $contact[$contact['tel_prefer']],
							'tel_work' => $contact['tel_work'],
							'tel_cell' => $contact['tel_cell'],
							'tell_home' => $contact['tel_home'],
						]
					],
					'lastlogin' => $user['account_lastlogin'],
				];
			}
		}
		uasort($stat, static function ($a, $b) {
			if ($a['stat']['egw']['active'] == $b['stat']['egw']['active']) {
				return $b['lastlogin'] - $a['lastlogin'];
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
	public static function get_actions()
	{
		return [
			'call' => [
				'caption' => 'Video Call',
				'icon' => 'status/videoconference_call',
				'default' => true,
				'onExecute' => 'javaScript:app.status.handle_actions',
				'enabled' => self::isVideoconferenceDisabled() ? false : 'javaScript:app.status.isOnline'
			],
			'audiocall' => [
				'caption' => 'Audio Call',
				'icon' => 'accept_call',
				'onExecute' => 'javaScript:app.status.handle_actions',
				'enabled' => self::isVideoconferenceDisabled() ? false : 'javaScript:app.status.isOnline'
			],
			'invite' => [
				'caption' => 'Invite to current call',
				'icon' => 'status/videoconference_join',
				'onExecute' => 'javaScript:app.status.handle_actions',
				'enabled' => self::isVideoconferenceDisabled() ? false : 'javaScript:app.status.isThereAnyCall'
			],
			'fav' => [
				'caption' => 'Add to favorites',
				'allowOnMultiple' => false,
				'icon' => 'fav_filter',
				'group' => 1,
				'onExecute' => 'javaScript:app.status.handle_actions'
			],
			'unfavorite' => [
				'caption' => 'Remove from favorites',
				'allowOnMultiple' => false,
				'enabled' => false,
				'icon' => 'delete',
				'group' => 1,
				'onExecute' => 'javaScript:app.status.handle_actions'
			]
		];
	}

	/**
	 * Get all implemented stat keys
	 * @return array returns array of stat keys
	 */
	public static function getStatKeys()
	{
		return Api\Hooks::implemented('status-getStatus');
	}

	/**
	 * Get username from account_lid
	 *
	 * @param type $_user = null if user given then use user as account lid
	 * @return string return username
	 */
	public static function getUserName($_user = null)
	{
		return $_user ?: $GLOBALS['egw_info']['user']['account_lid'];
	}

	/**
	 * Update state
	 * @throws Api\Json\Exception
	 */
	public static function updateState()
	{
		$account_state = Api\Cache::getSession(self::APPNAME, 'account_state');
		$current_state = md5(json_encode(self::getUsers()));
		$response = Api\Json\Response::get();
		if ($account_state != $current_state) {
			// update the status list
			$response->call('app.status.refresh');
		}
		// nothing to update
	}

	/**
	 * Push user status to all after session is created
	 *
	 * @param array $_data
	 * @throws Api\Json\Exception
	 */
	public static function sessionCreated(array $_data)
	{
		if ($_data['session_type'] == "webgui")
		{
			$push = new Api\Json\Push(Api\Json\Push::ALL);
			$push->call('framework.execPushBroadcastAppStatus',[
				[
					'id' => $GLOBALS['egw_info']['user']['account_lid'],
					'class' => 'egw_online',
					'data' => ['status' => ['active' => true]]
				]
			]);
		}
	}

	/**
	 * Query list of active online users ordered by lastlogin
	 *
	 * @return array
	 */
	public static function getUsers()
	{
		$users = [];
		$pref_groups = $GLOBALS['egw_info']['user']['preferences']['status']['groups'] ?? [];
		if (!is_array($pref_groups))
		{
			$pref_groups = $pref_groups ? explode(',', $pref_groups) : [];
		}
		$filter = [];
		if (empty($pref_groups) || in_array('_A',$pref_groups))
		{
			$filter = 'accounts';
		}
		else
		{
			foreach ($pref_groups as $g)
			{
				switch ($g)
				{
					case "_A":
						// Skip to the next group
						continue 2;
					case "_P":
						$filter[] = $GLOBALS['egw_info']['user']['account_primary_group'];
						break;
					default :
						$filter[] = $g;
				}
			}
			$filter = implode(',', $filter);
		}
		// get list of users
		\admin_ui::get_users([
			'filter' => $filter,
			'order' => 'account_lastlogin',
			'sort' => 'DESC',
			'active' => true,
			'filter2' => 'enabled',
			'num_rows' => 50 //fetch max 50 users
		], $users);

		$push = new Api\Json\Push();
		$online = $push::online();

		$ids = array_column((array)$users, 'account_id');

		foreach ((array)$GLOBALS['egw_info']['user']['preferences']['status']['fav'] as $fav)
		{
			if (is_numeric($fav) && !in_array((array)$fav, $ids))
			{
				// add already favorite accounts which are not in the users
				$users[] = [
					'account_lid' => Api\Accounts::id2name($fav),
					'account_id' => $fav
				];
			}
		}

		foreach($users as &$user)
		{
			if (in_array($user['account_id'], $online)) $user['online'] = true;
		}
		return $users;
	}

	/**
	 * Searches rocketchat users and accounts
	 *
	 * Find entries that match query parameter (from link system) and format them
	 * as the widget expects, a list of {id: ..., label: ..., icon: ...} objects
	 * @noinspection PhpUnused
	 */
	public static function ajax_search()
	{
		$query = $_REQUEST['query'];
		$options = array();
		$links = array();

		// Only search if a query was provided - don't search for all accounts
		if ($query) {
			$options['account_type'] = 'accounts';
			$links = Api\Accounts::link_query($query, $options);
		}

		$results = array();
		foreach ($links as $id => $name) {
			$results[] = array(
				'id' => $id,
				'label' => $name,
				'icon' => Api\Egw::link('/api/avatar.php', array('account_id' => $id))
			);
		}
		$hooks = Api\Hooks::implemented('status-getSearchParticipants');
		foreach ($hooks as $app) {
			$r = Api\Hooks::process(['location' => 'status-getSearchParticipants', 'app' => $app], $app);
			if (is_array($r[$app])) $results = array_merge_recursive($results, $r[$app]);
		}
		usort($results, static function ($a, $b) use ($query) {
			$a_label = is_array($a["label"]) ? $a["label"]["label"] : $a["label"];
			$b_label = is_array($b["label"]) ? $b["label"]["label"] : $b["label"];

			similar_text($query, $a_label, $percent_a);
			similar_text($query, $b_label, $percent_b);
			return $percent_a === $percent_b ? 0 : ($percent_a > $percent_b ? -1 : 1);
		});

		// switch regular JSON response handling off
		Api\Json\Request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results);
		exit;
	}

	public static function menu($data)
	{
		if ($GLOBALS['egw_info']['user']['apps']['admin']) {
			$file = Array(
				'Site Configuration' => Api\Egw::link('/index.php', 'menuaction=admin.admin_config.index&appname=' . self::APPNAME . '&ajax=true')
			);
			if ($data['location'] == 'admin') {
				display_section(self::APPNAME, $file);
			}
		}
	}

	/**
	 * Set predefined settings
	 *
	 * @param array $config
	 * @return array with additional Api\Config to merge
	 * @throws Api\Exception\WrongParameter
	 */
	public static function config(array $config)
	{
		if (empty($config['videoconference']['backend']))
		{
			$config['videoconference']['backend'] = self::DEFAULT_VIDEOCONFERENCE_BACKEND;
		}
		if ((empty($config['videoconference']['jitsi']['jitsi_domain']) ||
			$config['videoconference']['jitsi']['jitsi_domain'] === 'jitsi.egroupware.net')
			&& in_array(self::DEFAULT_VIDEOCONFERENCE_BACKEND, (array)$config['videoconference']['backend']))
		{
			$config['videoconference']['jitsi']['jitsi_domain'] = 'meet.jit.si';
		}

		// Add videoconference categories
		foreach([
					'status_cat_videocall' => ['name' => lang('Video call'),'data' => ['color' => '#9e7daf'],'description' => lang('Created by Status configuration')]
				] as $conf => $cat)
		{
			if (empty($config[$conf]))
			{
				if (!isset($cats)) $cats = new Api\Categories(Api\Categories::GLOBAL_ACCOUNT, Api\Categories::GLOBAL_APPNAME);
				Api\Config::save_value('status_cat_videocall',$cats->add($cat), 'status');
			}
		}

		return $config;
	}

	/**
	 * Creates resource for bbb-server
	 * @param $_content
	 * @throws Api\Exception\WrongParameter
	 * @throws \Exception
	 */
	public static function config_after_save($_content)
	{
		if ($_content['location'] != 'config_after_save' && $_content['appname'] != 'status') return;
		$config = $_content['newsettings']['videoconference'];
		if (in_array('BBB', (array)$config['backend']))
		{
			if (!$GLOBALS['egw_info']['user']['apps']['resources']) return;
			$res_id = (int)Api\Config::read('status')['bbb_res_id'];
			$resources = new resources_bo($GLOBALS['egw_info']['user']['account_id']);
			$resource = $resources->read($res_id, true);
			if (!$resource || $resource['deleted']) $resource = $res_id = null;
			if ($config['bbb']['bbb_seats'] > 0)
			{
				$category = new Api\Categories('', 'resources');
				// Global category Locations seems to be case sensitive
				$cat_id = $category->name2id('Locations')!= 0 ?
					$category->name2id('Locations') : $category->name2id('locations');
				// try to create Locations global cat if not there
				if ($cat_id == 0) $cat_id = $category->add([
					'appname' => 'resources',
					'no_private'=> true,
					'access' => 'public',
					'all_cats' => 'all_no_acl',
					'name' => 'Locations',
					'owner' => Api\Categories::GLOBAL_ACCOUNT,
					'parent' => 0]);

				$resource['useable'] = $resource['quantity'] = $config['bbb']['bbb_seats'];
				$saved_res_id = $resources->save(array_merge([
					'res_id' => $res_id,
					'name' => lang(self::SERVER_RESOURCE_PREFIX_NAME.'%1','BigBlueButton'),
					'quantity' => $config['bbb']['bbb_seats'],
					'useable' => $config['bbb']['bbb_seats'],
					'cat_id' => $cat_id,
					'bookable' => true
				], $resource), true);
				if (is_numeric($saved_res_id) && $saved_res_id != $res_id)
				{
					Api\Config::save_value('bbb_res_id', $saved_res_id, 'status');
				}
			}
			elseif($res_id && $resource)
			{
				$resources->delete($res_id);
			}

			//create openid client
			$clients = new ClientRepository();
			try {
				$clients->getClientEntity($config['backend'], null, null, false);
			}
			catch (OAuthServerException $e)
			{
				unset($e);
				$client = new ClientEntity();
				$client->setIdentifier($config['backend']);
				$client->setSecret(Api\Auth::randomstring(24));
				$client->setName(lang('BigBlueButton token'));
				$client->setScopes(['8']);
				$client->setRefreshTokenTTL('P0S');	// no refresh token
				$client->setRedirectUri($GLOBALS['egw_info']['server']['webserver_url'].'/');
				$clients->persistNewClient($client);
			}
		}
	}

	/**
	 * Validate the configuration
	 *
	 * @param array $data
	 * @return string|null string with error or null on success
	 */
	public static function validate(array $data)
	{
		$error = '';

		if (in_array('BBB', $data['videoconference']['backend']))
		{
			if (!$GLOBALS['egw_info']['user']['apps']['resources']) $error = "\n-".lang("Resources app is missing!");
			if (!$data['videoconference']['bbb']['bbb_domain']) $error .= "\n-".lang("bbb domain is missing!");
			if (!$data['videoconference']['bbb']['bbb_csp']) $error .= "\n-".lang("bbb CSP wild card domain is missing!");
			if (!$data['videoconference']['bbb']['bbb_api_secret']) $error .= "\n-".lang("bbb Api secret is missing!");
		}
		return $error?? null;
	}

	public static function isVideoconferenceDisabled()
	{
		$config = Config::read('status');
		return $config['videoconference']['disable'];
	}

	/**
	 * @return false|mixed
	 */
	public static function getVideoconferenceResourceId()
	{
		$config = Config::read('status');
		return in_array('BBB', (array)$config['videoconference']['backend']) && $config['bbb_res_id'] ? $config['bbb_res_id'] : false;
	}

	public static function isVCRecordingSupported()
	{
		$config = Config::read('status');
		return in_array('BBB', (array)$config['videoconference']['backend'], true);
	}

	/**
	 * Settings for preferences
	 *
	 * @return array with settings
	 */
	public static function settings()
	{
		$groups = array(
			'_A' => lang('ALL'),
			'_P' => lang('Primary Group'),
		);
		foreach($GLOBALS['egw']->accounts->search(array('type' => 'groups')) as $acc)
		{
			$groups[$acc['account_id']] = Api\Accounts::format_username(
				$acc['account_lid'], $acc['account_firstname'], $acc['account_lastname'], $acc['account_id']);
		}
		$riningTimeouts = [];
		for($i=5;$i<60;$i+=5)
		{
			$riningTimeouts[$i] = $i.' '.lang('seconds');
		}
		return [
			'1.section' => [
				'type' => 'section',
				'title' => lang('Video Conference'),
				'no_lang' => true,
				'xmlrpc' => false,
				'admin' => false
			],
			'opencallin' => [
				'type' => 'select',
				'label' => 'Open call in',
				'name' => 'opencallin',
				'values' => [0 => lang('new window'), 1 => lang('popup')],
				'help' => 'Open call in new window/popup',
				'xmlrpc' => false,
				'admin' => false,
				'default' => 0,
			],
			'ringtone' => [
				'type' => 'select',
				'label' => 'enable ring tone',
				'name' => 'ringtone',
				'values' => [1 => lang('yes'), 0 => lang('no')],
				'help' => 'Enable ring tone while receiving a call',
				'xmlrpc' => false,
				'admin' => false,
				'default' => 1,
			],
			'ringingtimeout' => [
				'type' => 'select',
				'label' => 'ringing timeout',
				'values' => $riningTimeouts,
				'name' => 'ringingtimeout',
				'help' => 'Timeout for the ring tone for incoming calls.',
				'default' => 15
			],
			'groups' => [
				'type' => 'multiselect',
				'label' => 'Predefined group of users to be listed',
				'name'=> 'groups',
				'values' => $groups,
				'help' => 'Users of selected groups will be listed.',
				'default' => '_A'
			]
		];
	}

	/**
	 * Method to construct notifications actions
	 *
	 * @param array $params
	 * @return array
	 * @noinspection PhpUnused
	 */
	public static function notifications_actions(array $params)
	{
		Api\Translation::add_app('status');
		return [
			[
				'id' => 'callback',
				'caption' => lang('Callback'),
				'icon' => 'accept_call',
				'onExecute' => 'app.status.makeCall([{'.
					'id:"'.$params['data']['caller'].'",'.
					'name:"'.Api\Accounts::id2name($params['data']['caller'], 'account_fullname').'",'.
					'avatar:"'. 'account:'.$params['data']['caller'].'"}])'
			]
		];
	}

	/**
	 * Register Jitsi domain for CSP policies frame-src
	 *
	 * @return array
	 * @throws Api\Exception\WrongParameter
	 * @noinspection PhpUnused
	 */
	public static function csp_frame_src()
	{
		$config = self::config(Api\Config::read('status'));
		$srcs = [];
		$backend = strtolower(is_array($config['videoconference']['backend'])?$config['videoconference']['backend'][0]:$config['videoconference']['backend']);
		if (!empty($config['videoconference'][$backend][$backend.'_domain']))
		{
			$srcs[] = preg_replace('#^(https?://[^/]+)(/.*)?#', '$1', $config['videoconference'][$backend][$backend.'_domain']);
			if (in_array($config['videoconference'][$backend][$backend.'_domain'], ['jitsi.egroupware.org', 'jitsi.egroupware.net']))
			{
				$srcs[] = 'https://www.egroupware.org/';
			}
		}
		if (!empty($config['videoconference'][$backend][$backend.'_csp'])) $srcs[] = $config['videoconference'][$backend][$backend.'_csp'];
		return $srcs;
	}
}
