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
		var data = _selected[0]['data'];
		var id = _selected[0]['id'];
		var favorites = Object.values(egw.preference('fav', 'status'));
		switch (_action.id)
		{
			case 'fav':
				favorites.push(data.account_id);
				egw.set_preference('status', 'fav', favorites);
				break;
			case 'unfavorite':
				for (var i in favorites)
				{
					if (favorites[i] == data.account_id) favorites.splice(i,1);
				}
				egw.set_preference('status', 'fav', favorites);
				break;

		}

	},

	/**
	 * Dialog for selecting users and add them to the favorites
	 */
	add_to_fav: function ()
	{
		var list = this.et2.getArrayMgr('content').getEntry('list');
		var self = this;
		et2_createWidget("dialog",
		{
			callback: function(_button_id, _value)
			{
				if (_button_id == 'add' && _value)
				{
					for (var i in _value.accounts)
					{
						for (var j in list)
						{
							if (list[j]['account_id'] == _value.accounts[i])
							{
								self.handle_actions({id: 'fav'}, [{data: list[j]}]);
							}
						}
					}
				}
			},
			title: egw.lang('Add to favorites'),
			buttons: [
				{text: this.egw.lang("Add"), id: "add", class: "ui-priority-primary", default: true},
				{text: this.egw.lang("Cancel"), id:"cancel"}
			],
			value:{
				content:{
					value: '',
			}},
			template: egw.webserverUrl+'/status/templates/default/search_list.xet',
			resizable: false
		}, et2_dialog._create_parent('status'));
	},

	/**
	 * Refresh the list
	 */
	refresh: function ()
	{
		// TODO
	}
});