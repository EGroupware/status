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
		$content = array('list' => Hooks::statusItems());

		// first row of grid is dedicated to its header
		array_unshift($content['list'], [''=>'']);

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
	 * @todo arrange sorting and refresh client
	 */
	public static function ajax_sorting ($orders)
	{

		//TODO: set newly sorted list into preferences


		//Calling to referesh after sort happens
		$response = Api\Json\Response::get();
		$response->call('app.status.refresh');
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
}
