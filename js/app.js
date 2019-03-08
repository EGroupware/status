/**
 * EGroupware - Status
 *
 * @link http://www.egroupware.org
 * @package Status
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @copyright (c) 2019 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

app.classes.status = AppJS.extend(
{
	appname: 'status',

	/**
	 * Constructor
	 *
	 * @memberOf app.status
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} _et2 newly ready object
	 * @param {string} _name template name
	 */
	et2_ready: function(_et2, _name)
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Handle executed action on selected row and refresh the list
	 *
	 * @param {type} _action
	 * @param {type} _selected
	 * @TODO Implementing Fav preference and refresh list
	 */
	handle_actions: function (_action, _selected)
	{
		var id = _selected[0]['id'];
		switch (_action.id)
		{
			case 'fav':
				console.log(id);
				break;

		}

	},

	/**
	 * Refresh the list
	 */
	refresh: function ()
	{
		// TODO
	}
});