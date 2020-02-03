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
		var fav = egw.preference('fav', 'status') || {};
		var favorites = Object.values(fav);
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
			case 'mail':
				if (typeof data.account_id == "string" && data.account_id.match(/:/) && data.link_to)
				{
					egw.json(
						"EGroupware\\Status\\Ui::ajax_getContactofLink",
						["rocketchat", data.account_id],
						function(contact){
							if (contact)
							{
								egw.open('', 'mail', 'add',{'preset[mailto]': +contact[0]['email']});
							}
						}
					).sendRequest()
				}
				else
				{
					egw.accountData(data.account_id, 'account_email',null, function(_data){
						egw.open('', 'mail', 'add', {'preset[mailto]':_data[data.account_id]});
					});
				}

				break;
		}
		this.refresh();
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
			title: this.egw.lang('Add to favorites'),
			buttons: [
				{text: this.egw.lang("Add"), id: "add", class: "ui-priority-primary", default: true},
				{text: this.egw.lang("Cancel"), id:"cancel"}
			],
			value:{
				content:{
					value: '',
			}},
			template: egw.webserverUrl+'/status/templates/default/search_list.xet',
			resizable: false,
			width: 400,
		}, et2_dialog._create_parent('status'));
	},

	/**
	 * Refresh the list
	 */
	refresh: function ()
	{
		var self = this;
		// give it a delay to make sure the preferences data is updated before refreshing
		window.setTimeout(function(){
			egw.json('EGroupware\\Status\\Ui::ajax_refresh', [], function(_data){
				self.updateContent(_data.fav, _data.list);
			}).sendRequest();
		}, 200);
	},

	/**
	 * Update content of fav and list girds
	 * @param {array} _fav
	 * @param {array} _list
	 */
	updateContent: function (_fav, _list)
	{
		var fav = this.et2.getWidgetById('fav');
		var content = this.et2.getArrayMgr('content');
		var list = this.et2.getWidgetById('list');
		if (_fav && typeof _fav != 'undefined')
		{
			fav.set_value({content:_fav});
			content.data.fav = _fav;
		}
		if (_list && typeof _list != 'undefined')
		{
			list.set_value({content:_list});
			content.data.list = _list
		}
		this.et2.setArrayMgr('content', content);
	},

	/**
	 * Merge given content with existing ones and updates the lists
	 *
	 * @param {array} _content
	 */
	mergeContent: function (_content)
	{
		var fav = this.et2.getArrayMgr('content').getEntry('fav');
		var list = this.et2.getArrayMgr('content').getEntry('list');
		for (var i in _content)
		{
			for (var f in fav)
			{
				if (fav[f] && fav[f]['id'] && _content[i]['id'] == fav[f]['id'])
				{
					jQuery.extend(fav[f], _content[i]);
				}
			}
			for (var l in list)
			{
				if (list[l] && list[l]['id'] && _content[i]['id'] == list[l]['id'])
				{
					jQuery.extend(list[l], _content[i]);
				}
			}
		}
		this.updateContent(fav, list);
	}
});