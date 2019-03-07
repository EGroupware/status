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
		$content = array('list' => self::get_rows());

		// first row of grid is dedicated to its header
		array_unshift($content['list'], [''=>'']);

		if (is_array($actions = self::get_actions()) && !empty($actions))
		{
			// Add actions
			$tpl->setElementAttribute('list', 'actions', $actions);
		}

		$tpl->exec('status.EGroupware\\Status\\Ui.index', $content,array());
	}

	public static function ajax_sorting ()
	{

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
			$actions += Api\Hooks::process('status-get_actions', $app, true);
		}
		return $actions;
	}

	/**
	 * Get rows
	 * @return array
	 */
	static function get_rows ()
	{
		$rows = [];
		$hooks = Api\Hooks::implemented('status-get_rows');
		foreach($hooks as $app)
		{
			$r = Api\Hooks::process('status-get_rows', $app, true);
			$rows += $r[$app];
		}
		return $rows;
	}
}
