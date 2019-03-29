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

		$content = [];
		$skeys = Hooks::getStatKeys();
		foreach (Hooks::statusItems() as $item)
		{
			$stat = [];
			foreach (['0','1','2','3'] as $key)
			{
				$skey = $skeys[$GLOBALS['egw_info']['user']['preferences']['status']['status'.$key]] ?
						$skeys[$GLOBALS['egw_info']['user']['preferences']['status']['status'.$key]] : $skeys[$key];
				if (!empty($item['stat'][$skey]['active']))
				{
					$stat['stat'.$key] = true;
					if (!empty($item['stat'][$skey]['notification']))
					{
						$stat['notification'.$key] = $item['stat'][$skey]['notification'];
					}

					if (!empty($item['stat'][$skey]['bg']))
					{
						$stat['bg'.$key] = $item['stat'][$skey]['bg'];
					}
				}
			}
			$isFav = in_array($item['id'], $GLOBALS['egw_info']['user']['preferences']['status']['fav']);
			$content[$isFav ? 'fav' : 'list'][] = array_merge([
				'id' => $item['id'],
				'hint' => $item['hint'],
				'icon' => $item['icon'],
			], (array)$stat);
		}

		if (count($content['fav']) < 2) {
			// need to add an emptyrow to avoid getting grid rendering error because of
			// lacking a row id
			$content['fav'][] = ['id' => 'emptyrow'];
		}
		else
		{
			$temp = [];
			// Sort fav list base on stored user fav preference
			foreach ($GLOBALS['egw_info']['user']['preferences']['status']['fav'] as $fav)
			{
				foreach ($content['fav'] as $item)
				{
					if ($item['id'] == $fav) $temp[] = $item;
				}
			}
			$content['fav'] = $temp;
		}

		// first row of grid is dedicated to its header
		array_unshift($content['list'], [''=>'']);
		array_unshift($content['fav'], [''=>'']);

		if (is_array($actions = self::get_actions()) && !empty($actions))
		{
			// Add actions
			$tpl->setElementAttribute('list', 'actions', $actions);
		}

		$tpl->exec('status.EGroupware\\Status\\Ui.index', $content,array());
	}

	/**
	 * handle drag and drop sorting
	 *
	 * @param array $orders newly ordered list
	 *
	 */
	public static function ajax_sorting ($orders)
	{
	}

	/**
	 * handle drag and drop sorting
	 *
	 * @param array $orders newly ordered list
	 */
	public static function ajax_fav_sorting ($orders)
	{
		// the first row belongs to an empty placeholder and it should not participate
		// in sorting
		if ($orders[0] && $orders[0]['id'] == 'emptyrow') unset($orders[0]);

		$GLOBALS['egw']->preferences->add('status','fav', array_values($orders));
		$GLOBALS['egw']->preferences->save_repository(false,'user',false);
	}

	/**
	 * Get actions / context menu for index
	 *
	 * @return {array} returns defined actions as an array
	 */
	private static function get_actions()
	{
		$actions = [];
		$hooks = Api\Hooks::implemented('status-get_actions');
		foreach ($hooks as $app)
		{
			$a =  Api\Hooks::process('status-get_actions', $app, true);
			$actions += $a[$app];
		}
		return $actions;
	}

	private static function get_favActions()
	{
		return [
			'unfavorite' => [
				'caption' => 'Remove from favorites',
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.status.handle_actions'
			]
		];
	}
}
