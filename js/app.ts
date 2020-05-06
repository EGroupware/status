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
	static readonly appname = 'status';

	private _ring : JQuery = null;

	private static MISSED_CALL_TIMEOUT : number = 30000;

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
		switch (_name)
		{
			case 'status.index':
				if (egw.preference('ringtone', 'status'))
				{
					this._ring = jQuery(document.createElement('audio')).attr({id:'status-ring', src:'status/assets/ring.mp3'}).appendTo('#status-index_status-index-fav');
					let self = this;
					jQuery('body').one('click', function(){
						self._controllRingTone().initiate();
					});
				}
				break;
			case 'status.room':
				let room = this.et2.getArrayMgr('content').getEntry('room');
				egw(window.opener).setSessionItem('status', 'videoconference-session', room);
				window.addEventListener("beforeunload", function(e){
					window.opener.sessionStorage.removeItem('status-videoconference-session')
				 }, false);
				break;
		}

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
			case 'audiocall':
			case 'call':
				this.makeCall([{
					id: data.account_id,
					name: data.hint,
					avatar: "account:"+data.account_id,
					audioonly: _action.id == 'audiocall' ? true : false,
					data: data
				}]);

				break;
			case 'invite':
				this.inviteToCall([{
					id: data.account_id,
					name: data.hint,
					avatar: "account:"+data.account_id,
					audioonly: _action.id == 'audiocall' ? true : false,
					data: data
				}], egw.getSessionItem('status', 'videoconference-session'));
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
					jQuery.extend(true, fav[f], _content[i]);
				}
			}
			for (let l in list)
			{
				if (list[l] && list[l]['id'] && _content[i]['id'] == list[l]['id'])
				{
					jQuery.extend(true, list[l], _content[i]);
				}
			}
		}
		this.updateContent(fav, list);
	}

	public getEntireList()
	{
		let fav = this.et2.getArrayMgr('content').getEntry('fav');
		let list = this.et2.getArrayMgr('content').getEntry('list');
		let result = [];
		for (let f in fav)
		{
			if (fav[f] && fav[f]['id']) result.push(fav[f]);
		}
		for (let l in list)
		{
			if (list[l] && list[l]['id']) result.push(list[l]);
		}
		return result;
	}

	isOnline(_action, _selected)
	{
		return _selected[0].data.data.status.active || app.rocketchat?.isRCActive(_action, _selected);
	}

	/**
	 * Initiate call via action
	 * @param array data
	 */
	makeCall(data)
	{
		let callCancelled = false;
		let self = this;
		let button = [{"button_id": 0, "text": egw.lang('Cancel'), id: '0', image: 'cancel'}];
		let dialog = et2_createWidget("dialog",{
			callback: function(_btn){
				if (_btn == et2_dialog.CANCEL_BUTTON)
				{
					callCancelled = true;
				}
			},
			title: this.egw.lang('Initiating call to'),
			buttons: button,
			minWidth: 300,
			minHeight: 200,
			resizable: false,
			value: {
				content: {list:data}
			},
			template: egw.webserverUrl+'/status/templates/default/call.xet'
		}, et2_dialog._create_parent(this.appname));
		setTimeout(function(){
			if (!callCancelled)
			{
				dialog.destroy();
				egw.json(
					"EGroupware\\Status\\Videoconference\\Call::ajax_video_call",
					[data, data[0]['room']], function(_url){
						self.openCall(_url.caller);
						if (app.rocketchat?.isRCActive(null, [{data:data[0].data}]))
						{
							app.rocketchat.restapi_call('chat_PostMessage', {
								roomId:data[0].data.data.rocketchat._id,
								attachments:[
									{
										"collapsed": false,
										"color": "#009966",
										"title": egw.lang("Click to Join!"),
										"title_link": _url.callee,
										"thumb_url": "https://raw.githubusercontent.com/EGroupware/status/master/templates/pixelegg/images/videoconference_call.svg",
									}
								]})
						}
					}).sendRequest();
			}
		}, 3000);
	}

	/**
	 * Open call url with respecting opencallin preference
	 * @param _url call url
	 */
	openCall(_url)
	{
		let link = egw.link('/index.php', {
				menuaction: 'status.\\EGroupware\\Status\\Ui.room',
				frame: _url
			});
		if (egw.preference('opencallin', statusApp.appname) == '1')
		{
			 egw.open_link(link, '_blank');
		}
		else
		{
			egw.openPopup(link, 800, 600, '', 'status');
		}
	}

	scheduled_receivedCall(_content, _notify)
	{
		let buttons = [
			{"button_id": 1, "text": egw.lang('Join'), id: '1', image: 'accept_call', default: true},
			{"button_id": 0, "text": egw.lang('Close'), id: '0', image: 'close'}
		];
		let notify = _notify || true;
		let content = _content || {};
		let self = this;
		this._controllRingTone().start();
		et2_createWidget("dialog",{
			callback: function(_btn, value){
				if (_btn == et2_dialog.OK_BUTTON)
				{
					self.openCall(value.url);
				}
			},
			title: '',
			buttons: buttons,
			minWidth: 200,
			minHeight: 300,
			modal: false,
			position:"right bottom,right-100 bottom-10",
			value: {
				content: content
			},
			resizable: false,
			template: egw.webserverUrl+'/status/templates/default/scheduled_call.xet'
		}, et2_dialog._create_parent(this.appname));
		if (notify)
		{
			egw.notification(this.egw.lang('Status'), {
				body: this.egw.lang('You have a video conference meeting in %1 minutes, initiated by %2', (content['alarm-offset']/60), content.owner),
				icon: egw.webserverUrl+'/api/avatar.php?account_id='+ content.account_id,
				onclick: function () {
					window.focus();
				},
				requireInteraction: true
			});
		}
	}

	/**
	 * gets called after receiving pushed call
	 * @param _data
	 */
	receivedCall(_data, _notify?, _buttons?, _message_top?, _message_bottom?)
	{
		let buttons = _buttons || [
			{"button_id": 1, "text": egw.lang('Accept'), id: '1', image: 'accept_call', default: true},
			{"button_id": 0, "text": egw.lang('Reject'), id: '0', image: 'hangup'}
		];
		let notify = _notify?? true;
		let message_bottom = _message_bottom || 'is calling';
		let message_top = _message_top || '';
		let self = this;
		let isCallAnswered = false;
		let timeToPickup = window.setTimeout(function(){
			if (!isCallAnswered)
			{
				egw.json("EGroupware\\Status\\Videoconference\\Call::ajax_setMissedCallNotification", [_data], function(){}).sendRequest();
				egw.accountData(_data.caller.account_id, 'account_lid',null,function(account){
					self.mergeContent([{
						id: account[_data.caller.account_id],
						class1: 'missed-call',
					}]);
				}, egw);
				dialog.destroy();
			}
		}, statusApp.MISSED_CALL_TIMEOUT);
		this._controllRingTone().start(true);
		var dialog = et2_createWidget("dialog",{
			callback: function(_btn, value){
				if (_btn == et2_dialog.OK_BUTTON)
				{
					self.openCall(value.url);
					isCallAnswered = true;
				}
				self._controllRingTone().stop();
			},
			title: '',
			buttons: buttons,
			minWidth: 200,
			minHeight: 200,
			modal: false,
			position:"right bottom,right-50 bottom-10",
			value: {
				content: {
					list:[{
						"name":_data.caller.name,
						"avatar": "account:"+_data.caller.account_id,
					}],
					"message_buttom": egw.lang(message_bottom),
					"message_top": egw.lang(message_top),
					"url": _data.call
				}
			},
			resizable: false,
			template: egw.webserverUrl+'/status/templates/default/call.xet'
		}, et2_dialog._create_parent(this.appname));
		if (notify)
		{
			egw.notification(this.egw.lang('Status'), {
				body: this.egw.lang('You have a call from %1', _data.caller.name),
				icon: egw.webserverUrl+'/api/avatar.php?account_id='+ _data.caller.account_id,
				onclick: function () {
					window.focus();
				},
				requireInteraction: true
			});
		}
	}

	private _controllRingTone()
	{
		let self = this;
		return {
			start: function (_loop?){
				if (!self._ring) return;
				let loop = _loop || false;
				self._ring[0].loop = loop;
				self._ring[0].play().then(function(){
					window.setTimeout(function(){
						self._controllRingTone().stop();
					}, statusApp.MISSED_CALL_TIMEOUT) // stop ringing automatically after 10s
				},function(_error){
					console.log('Error happened: '+_error);
				});
			},
			stop: function (){
				if (!self._ring) return;
				self._ring[0].pause();
			},
			initiate: function(){
				self._ring[0].mute = true;
				self._ring[0].play().then(function(){

				},function(_error){
					console.log('Error happened: '+_error);
				});
				this.stop();
			}
		}
	}

	public didNotPickUp(_data)
	{
		let self = this;
		et2_dialog.show_dialog(function(_btn){
			if (et2_dialog.YES_BUTTON == _btn)
			{
				self.makeCall([_data]);
			}
		}, this.egw.lang('%1 did not pickup your call, would you like to try again?', _data.name), '');
	}

	public phoneCall(_action, _selected)
	{
		let data : any = _selected[0]['data'];

		let target = '';
		switch(_action.id)
		{
			case 'addressbook_tel_work':
				target = data.data.status.tel_work;
				break;
			case 'addressbook_tel_cell':
				target = data.data.status.tel_cell;
				break;
			case 'addressbook_tel_prefer':
				target = data.data.status.tel_prefer;
				break;
			case 'addressbook_tel_home':
				target = data.data.status.tel_home;
				break;
		}
		if (target)
		{
			let url = et2_createWidget('url-phone', {id:'temp_url_phone', readonly: true}, this.et2);
			url.set_value(target);
			url.span.click();
			url.destroy();
		}
	}

	public phoneIsAvailable(_action, _selected)
	{
		let data : any = _selected[0]['data'];

		switch(_action.id)
		{
			case 'addressbook_tel_work':
				if (data.data.status.tel_work) return true;
				break;
			case 'addressbook_tel_cell':
				if (data.data.status.tel_cell) return true;
				break;
			case 'addressbook_tel_prefer':
				if (data.data.status.tel_prefer) return true;
				break;
			case 'addressbook_tel_home':
				if (data.data.status.tel_home) return true;
				break;
		}
		return false;
	}

	public videoconference_invite ()
	{
		let url = this.et2.getArrayMgr('content').getEntry('frame');

		let self = this;
		et2_createWidget("dialog",
			{
				callback: function(_button_id, _value)
				{
					if (_button_id == 'add' && _value)
					{
						let data = [];
						for (let i in _value.accounts)
						{
							data.push({
								id: _value.accounts[i],
								name: '',
								avatar: "account:"+_value.accounts[i]
							})
						}
						egw.json("EGroupware\\Status\\Videoconference\\Call::ajax_video_call", [data, statusApp.videoconference_fetchRoomFromUrl(url), true], function(){

						}).sendRequest();
					}
				},
				title: this.egw.lang('Invite to this meeting'),
				buttons: [
					{text: this.egw.lang("Invite"), id: "add", class: "ui-priority-primary", default: true},
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

	public static videoconference_fetchRoomFromUrl(_url)
	{
		if (_url)
		{
			return _url.split(/\?jwt/)[0].split('/').pop();
		}
		return null;
	}

	public isThereAnyCall(_action, _selected)
	{
		return this.isOnline(_action, _selected) && egw.getSessionItem('status', 'videoconference-session');
	}

	public inviteToCall(_data, _room)
	{
		egw.json("EGroupware\\Status\\Videoconference\\Call::ajax_video_call", [_data, _room , true], function(){}).sendRequest();
	}
}
app.classes.status = statusApp;
