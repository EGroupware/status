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
use EGroupware\Status\Videoconference\Call;

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
		'index' => true,
		'room' => true,
		'vc_recordings' => true
	];

	/**
	 * Id delimiter
	 */
	public const ID_DELIMITER = ':';

	/**
	 * List Ui
	 * @param ?array $content
	 * @return Api\Etemplate\Request|string
	 * @throws Api\Exception\AssertionFailed
	 */
	public function index(?array $content=null)
	{
		$tpl = new Api\Etemplate('status.index');

		if (!is_array($content))
		{
			$content = self::getContentStatus();
		}
		else
		{
			$content = array_merge($content, self::getContentStatus());
		}

		if (is_array($actions = self::get_actions()) && !empty($actions))
		{
			// Add actions
			$tpl::setElementAttribute('list', 'actions', $actions);
			$actions['unfavorite']['enabled'] = true;
			$tpl::setElementAttribute('fav', 'actions', $actions);
		}

		return $tpl->exec('status.EGroupware\\Status\\Ui.index', $content,array(), array());
	}

	/**
	 * Room Ui
	 * @param ?array $content
	 * @return Api\Etemplate\Request|string
	 * @throws Api\Exception\AssertionFailed
	 */
	public function room(?array $content=null)
	{
		$tpl = new Api\Etemplate('status.room');
		// now time in UTC
		$now = time();
		if ($_GET['error'])
		{
			$content = [
				'frame'=>'',
				'room' => $_GET['meetingID'],
				'error' => $now > $_GET['end'] ? lang(Call::MSG_MEETING_IN_THE_PAST) : $_GET['error'],
				// Start and End time are in UTC
				'start' => (int) ($now > $_GET['end'] ? 0 :$_GET['start']),
				'end' => $_GET['end'],
				'countdown' => (int) ($now > $_GET['end'] || $now > $_GET['start'] ? 0 : $_GET['start'] - $now),
				'cal_id' => $_GET['cal_id'],
				'preparation' => $_GET['preparation']
			];
			if ((int)($_GET['preparation'])+$now >= $_GET['start']) $tpl::setElementAttribute('join', 'disabled', false);
		}
		else
		{
			$content['frame'] = is_array($_GET['frame']) ?  $_GET['frame'][0] : $_GET['frame'];
			$content['room'] = $_GET['room'] ?: ($content['frame'] ? Videoconference\Call::fetchRoomFromUrl($content['frame']) : null);
			$content['restrict'] = Api\Config::read('status')['videoconference']['backend'] == 'BBB';

			if ($content['restrict'] && !preg_match('/error\=/',$content['frame']))
			{
				/**
				 * call no iframe is added in config in order to deal with browsers SameSite cookie policy restriction which is
				 * not being set correctly in some bbb server installations therefore the bbb client can't be open in an iframe.
				 * There's already some reports about it https://github.com/bigbluebutton/bigbluebutton/issues/9998.
				 * When the no iframe setting's on we will show a clickable link in the room dialog in order to open the
				 * call url directly in new window.
				 */
				$content['noIframe'] =  Api\Config::read('status')['videoconference']['bbb']['bbb_call_no_iframe'];
			}
		}
		return $tpl->exec('status.EGroupware\\Status\\Ui.room', $content,array(), array());
	}

	/**
	 * Refresh with new content
	 * @throws Api\Json\Exception
	 * @noinspection PhpUnused
	 */
	public static function ajax_refresh ()
	{
		$response = Api\Json\Response::get();
		$data = self::getContentStatus();
		$response->data($data);
	}

	/**
	 * Get content
	 * @return array returns an array of content
	 */
	public static function getContentStatus ()
	{
		// close session now, to not block other user actions
		$GLOBALS['egw']->session->commit_session();

		$skeys = Hooks::getStatKeys();
		$content = ['list' => [], 'fav' => []];
		$onlines = []; // preserves online users for further proceessing in list
		foreach (Hooks::statusItems() as $item)
		{
			$stat = [];
			foreach (['0','1','2','3'] as $key)
			{
				$skey = $skeys[$key];
				if (!empty($item['stat'][$skey]['active']))
				{
					if (!empty($item['stat'][$skey]['notification']))
					{
						$stat['stat'.$key] = $item['stat'][$skey]['notification'];
					}

					if (!empty($item['stat'][$skey]['class']))
					{
						$stat['class'.$key] = $item['stat'][$skey]['class'];
					}

					if (!empty($item['stat'][$skey]['bg']))
					{
						$stat['bg'.$key] = $item['stat'][$skey]['bg'];
					}
				}
			}
			$isFav = in_array(self::_fetchId($item), self::mapFavoritesIds2Names(), true);
			if (!$isFav && $item['stat']['status']['active'])
			{
				$onlines[] = array_merge([
					'id' => $item['id'],
					'account_id' => $item['account_id'],
					'hint' => $item['hint'],
					'icon' => $item['icon'],
					'class' => ($item['stat']['status']['active'] ? 'egw_online' : 'egw_offline').' '.$item['class'],
					'link_to' => $item['link_to'],
					'data' => $item['stat']
				], $stat);
			}
			else
			{
				$content[$isFav ? 'fav' : 'list'][] = array_merge([
					'id' => $item['id'],
					'account_id' => $item['account_id'],
					'hint' => $item['hint'],
					'icon' => $item['icon'],
					'class' => ($item['stat']['status']['active'] ? 'egw_online' : 'egw_offline').' '.$item['class'],
					'link_to' => $item['link_to'],
					'data' => $item['stat']
				], $stat);
			}
		}
		// push current online users in the list to the top position
		if (!empty($onlines)) $content['list'] = array_merge($onlines, $content['list']);

		if (empty($content['fav']) || count($content['fav']) < 2) {
			// need to add an emptyrow to avoid getting grid rendering error because of
			// lacking a row id
			$content['fav'][] = ['id' => 'emptyrow'];
		}
		else
		{
			$temp = [];
			// Sort fav list base on stored user fav preference
			foreach (self::mapFavoritesIds2Names() as $fav)
			{
				foreach ($content['fav'] as $item)
				{
					if (self::_fetchId($item) == $fav) $temp[] = $item;
				}
			}
			$content['fav'] = $temp;
		}
		// first row of grid is dedicated to its header
		array_unshift($content['list'], [''=>'']);
		array_unshift($content['fav'], [''=>'']);
		return $content;
	}

	/**
	 * Fetch resolved Id
	 *
	 * @param array $item
	 * @return string
	 */
	private static function _fetchId (array $item)
	{
		return strtolower((string)(strpos((string)$item['account_id'], self::ID_DELIMITER) !== false ? $item['account_id'] : $item['id']));
	}

	/**
	 * handle drag and drop sorting
	 * @param string $exec_id
	 * @param array $orders newly ordered list
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUnused
	 */
	public static function ajax_fav_sorting (string $exec_id, array $orders)
	{
		// the first row belongs to an empty placeholder and it should not participate
		// in sorting
		if (is_array($orders[0]) && $orders[0]['id'] == 'emptyrow') unset($orders[0]);
		$GLOBALS['egw']->preferences->add('status','fav', array_values(self::mapNames2Ids($orders)));
		$GLOBALS['egw']->preferences->save_repository(false,'user',false);
	}

	/**
	 * Get actions / context menu for index
	 *
	 * @return array returns defined actions as an array
	 */
	private static function get_actions()
	{
		$actions = [
			'mail' => [
				'caption' => 'Mail',
				'icon' => 'mail/navbar',
				'allowOnMultiple' => false,
				'group' => 1,
				'onExecute' => 'javaScript:app.status.handle_actions',
			]
		];
		$hooks = Api\Hooks::implemented('status-get_actions');
		foreach ($hooks as $app)
		{
			$a =  Api\Hooks::process('status-get_actions', $app, true);
			$actions += $a[$app];
		}
		foreach ($actions as $key => $action)
		{
			if ($action['default'])
			{
				uksort($actions, static function($a) use ($key) {
					return $key != $a ? 1 : -1;
				});
				break;
			}
		}
		return $actions;
	}

	/**
	 * Map favorites preference into names
	 * @return array
	 */
	private static function mapFavoritesIds2Names ()
	{
		return array_map(static function ($_id)
		{
			return (is_numeric($_id) ? strtolower(Api\Accounts::id2name($_id)) : $_id);
		}, (array)$GLOBALS['egw_info']['user']['preferences']['status']['fav']);
	}

	/**
	 * Map names into ids
	 * @param array $_names
	 * @return array
	 */
	private static function mapNames2Ids (array $_names)
	{
		return array_map(static function ($name) {
			if (strpos($name, self::ID_DELIMITER))
			{
				return $name;
			}
			return Api\Accounts::getInstance()->name2id($name);
		}, $_names);
	}

	/**
	 * Get contact info from link
	 *
	 * @param string $app
	 * @param string $id
	 * @throws Api\Json\Exception
	 * @noinspection PhpUnused
	 */
	public static function ajax_getContactofLink(string $app, string $id)
	{
		$response = Api\Json\Response::get();
		$links = array_values(Api\Link::get_links($app,$id));
		$result = [];
		if (is_array($links))
		{
			$result = $GLOBALS['egw']->contacts->search(
				['contact_id' => $links[0]['id']],
				['email', 'email_home'],
				'', '', '', false, 'OR', false);
		}
		$response->data($result);
	}

	/**
	 * @param ?array $content
	 * @throws Api\Exception\AssertionFailed
	 * @noinspection PhpUnused
	 */
	public function vc_recordings(?array $content=null)
	{
		$tpl = new Api\Etemplate('status.vc_recordings');
		$room = $_GET['room'];
		$cal_id = $_GET['cal_id'];
		$title = $_GET['title'];
		if (!$content)
		{
			$recordings =  Call::getRecordings($room, ['cal_id' => $cal_id]);
			$content = [
				'recordings'=> empty($recordings['error'])? $recordings : [],
				'room' => $room,
				'cal_id' => $cal_id,
				'title' => $title,
				'moderator' => Call::isModerator($room, $GLOBALS['egw_info']['user']['account_id'].":".$cal_id)
			];
		}
		else
		{
			$recordings = Call::getRecordings($content['room'], $content);
			$content['recordings'] = empty($recordings['error'])? $recordings : [];
		}
		if (!empty($recordings['error'])) Api\Framework::message($recordings['error']);
		$preserv = $content;
		unset($preserv['recordings']);
		// skip the first row in grid
		array_unshift($content['recordings'], []);
		$tpl->exec('status.EGroupware\\Status\\Ui.vc_recordings', $content,[],[], $preserv, 2);
	}

	/**
	 * Delete recording action
	 * @param array $_params
	 * @throws Api\Json\Exception
	 * @noinspection PhpUnused
	 */
	public static function ajax_vc_deleteRecording(array $_params)
	{
		$response = Api\Json\Response::get();
		$response->data(Call::delete_recording($_params['room'], $_params));
	}
}