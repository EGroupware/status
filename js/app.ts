/**
 * EGroupware - Status
 *
 * @link http://www.egroupware.org
 * @package Status
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @copyright (c) 2019 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
	/api/js/jsapi/egw_app.js;
 */
import {EgwApp} from "../../api/js/jsapi/egw_app";

class statusApp extends EgwApp
{

	/**
	 * Constructor
	 *
	 * @memberOf app.status
	 */
	constructor()
	{
		// call parent
		super('status');
	}

	/**
	 * Destructor
	 */
	destroy(_app)
	{
		// call parent
		super.destroy(_app)
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} _et2 newly ready object
	 * @param {string} _name template name
	 */
	et2_ready(_et2, _name)
	{
		// call parent
		super.et2_ready(_et2, _name);
	}

	/**
	 * Handle executed action on selected row and refresh the list
	 *
	 * @param {type} _action
	 * @param {type} _selected
	 */
	handle_actions(_action, _selected)
	{
		let data = _selected[0]['data'];
		let fav = egw.preference('fav', 'status') || {};
		let favorites = Object.keys(fav).map(key => fav[key]);
		switch (_action.id)
		{
			case 'fav':
				favorites.push(data.account_id);
				egw.set_preference('status', 'fav', favorites);
				break;
			case 'unfavorite':
				for (let i in favorites)
				{
					if (favorites[i] == data.account_id) favorites.splice(<number><unknown>i,1);
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
			case 'call':
				this.makeCall(data);

				break;
		}
		this.refresh();
	}

	/**
	 * Dialog for selecting users and add them to the favorites
	 */
	add_to_fav()
	{
		let list = this.et2.getArrayMgr('content').getEntry('list');
		let self = this;
		et2_createWidget("dialog",
			{
				callback: function(_button_id, _value)
				{
					if (_button_id == 'add' && _value)
					{
						for (let i in _value.accounts)
						{
							for (let j in list)
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
	}

	/**
	 * Refresh the list
	 */
	refresh()
	{
		let self = this;
		// give it a delay to make sure the preferences data is updated before refreshing
		window.setTimeout(function(){
			egw.json('EGroupware\\Status\\Ui::ajax_refresh', [], function(_data){
				self.updateContent(_data.fav, _data.list);
			}).sendRequest();
		}, 200);
	}

	/**
	 * Update content of fav and list girds
	 * @param {array} _fav
	 * @param {array} _list
	 */
	updateContent(_fav, _list)
	{
		let fav = this.et2.getWidgetById('fav');
		let content = this.et2.getArrayMgr('content');
		let list = this.et2.getWidgetById('list');
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
	}

	/**
	 * Merge given content with existing ones and updates the lists
	 *
	 * @param {array} _content
	 */
	mergeContent(_content)
	{
		let fav = this.et2.getArrayMgr('content').getEntry('fav');
		let list = this.et2.getArrayMgr('content').getEntry('list');
		for (let i in _content)
		{
			for (let f in fav)
			{
				if (fav[f] && fav[f]['id'] && _content[i]['id'] == fav[f]['id'])
				{
					jQuery.extend(fav[f], _content[i]);
				}
			}
			for (let l in list)
			{
				if (list[l] && list[l]['id'] && _content[i]['id'] == list[l]['id'])
				{
					jQuery.extend(list[l], _content[i]);
				}
			}
		}
		this.updateContent(fav, list);
	}

	isOnline(_action, _selected)
	{
		return _selected[0].data.data.status.active;
	}

	/**
	 * Initiate call via action
	 * @param data
	 */
	makeCall(data)
	{
		let callCancelled = false;
		let button = [{"button_id": 0, "text": 'cancel', id: '0', image: 'cancel'}];
		let dialog = et2_createWidget("dialog",{
			callback: function(_btn){
				if (_btn == et2_dialog.CANCEL_BUTTON)
				{
					callCancelled = true;
				}
			},
			title: '',
			buttons: button,
			minWidth: 300,
			minHeight: 200,
			resizable: false,
			type:et2_dialog.PLAIN_MESSAGE,
			template: egw.webserverUrl+'/status/templates/default/call.xet',
			value: {content: {
					"name":data.hint,
					"avatar": "account:"+data.account_id,
					"message_top": egw.lang('Initiating call to')
			}}
		});
		setTimeout(function(){
			if (!callCancelled)
			{
				dialog.destroy();
				egw.json(
					"EGroupware\\Status\\Videoconference\\Call::ajax_video_call",
					[data.account_id], function(_url){
						window.open(_url);
					}).sendRequest();
			}
		}, 3000);
	}

	/**
	 * gets called after receiving pushed call
	 * @param _data
	 */
	receivedCall(_data)
	{
		let button = [
			{"button_id": 1, "text": 'accept', id: '0', image: 'check', default: true},
			{"button_id": 0, "text": 'reject', id: '0', image: 'cancel'}
		];
		et2_createWidget("dialog",{
			callback: function(_btn, value){
				if (_btn == et2_dialog.BUTTONS_OK)
				{
					window.open(value.url);
				}
			},
			title: '',
			buttons: button,
			minWidth: 300,
			minHeight: 200,
			template: egw.webserverUrl+'/status/templates/default/call.xet',
			value: {content: {
					"name":_data.caller.name,
					"avatar": "account:"+_data.caller.acc_id,
					"message_buttom": egw.lang('is calling'),
					"url": _data.call
			}},
			resizable: false
		}, et2_dialog._create_parent('status'))
	}
}
app.classes.status = statusApp;
