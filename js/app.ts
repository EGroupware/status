/**
 * EGroupware - Status
 *
 * @link http://www.egroupware.org
 * @package Status
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @copyright (c) 2019 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {EgwApp} from "../../api/js/jsapi/egw_app";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {et2_createWidget} from "../../api/js/etemplate/et2_core_widget";
// et2_grid has no web-component replacement to migrate to (a real, distinct legacy
// implementation, not a zero-member shim - see doc/ai/projects/app-ts-modernization.md); only used
// as a type here, so import type suffices.
import type {et2_grid} from "../../api/js/etemplate/et2_widget_grid";
import type {Et2UrlPhoneReadonly} from "../../api/js/etemplate/Et2Url/Et2UrlPhoneReadonly";
// et2_button is now a shim over Et2Button (et2_widget_button.ts deleted); only used as a
// type here, so import type suffices.
import type {et2_button} from "../../api/js/etemplate/legacy-shims/et2_widget_button";
// egw/app are ambient globals (declare global {} in egw_global.d.ts, unconditionally included
// via tsconfig's "**/*.d.ts") - no import needed or possible.

export class statusApp extends EgwApp
{
	static readonly appname = 'status';

	private _ring : HTMLAudioElement = null;

	private static MISSED_CALL_TIMEOUT : number = egw.preference('ringingtimeout', 'status') ?
		parseInt(<string>egw.preference('ringingtimeout', 'status')) * 1000 : 15000;

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
					this._ring = new Audio(this.egw.webserverUrl + '/status/assets/ring.mp3');
					document.body.addEventListener('click', () =>
					{
						this._controllRingTone().initiate();
					}, {once: true});
				}
				break;
			case 'status.room':
				let room = this.et2.getArrayMgr('content').getEntry('room');
				let url = this.et2.getArrayMgr('content').getEntry('frame');
				// getDOMWidgetById() is typed as returning "typeof Et2Widget" (the constructor)
				// instead of an instance - a pre-existing bug in Et2Template.ts, worked around
				// the same way as et2_widget_placeholder.ts does throughout: cast to <any>.
				const end : any = this.et2.getDOMWidgetById('end');
				let isModerator = url.match(/isModerator\=(1|true)/i)??false;
				if (isModerator)
				{
					end.set_disabled(false);
				}
				if (url.match(/\&error\=/i) || (!isModerator && this.et2.getArrayMgr('content').getEntry('restrict')))
				{
					(<any>this.et2.getDOMWidgetById('add')).set_disabled(true);
					break;
				}
				egw(window.opener).setSessionItem('status', 'videoconference-session', room);
				window.addEventListener("beforeunload", () =>
				{
					window.opener.sessionStorage.removeItem('status-videoconference-session');
				}, false);
				break;
		}

	}

	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * @param  pushData
	 * @param {string} pushData.app application name
	 * @param {(string|number)} pushData.id id of entry to refresh or null
	 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param {number} pushData.account_id User that caused the notification
	 */
	push(pushData)
	{
		// EPL/calls does NOT care about other apps data
		if (pushData.app !== 'stylite' || pushData.type === 'delete' || typeof pushData.acl === 'undefined') return;
		egw.accountData([pushData.acl.account_id, pushData.acl.account_id2], 'account_lid',null,(account) =>
		{
			let content : any = [{
				id: account[pushData.acl.account_id],
				class3: pushData.acl.account_id && pushData.acl.busy ? 'on-phone': '',
				title3: pushData.acl.account_id && pushData.acl.busy ? account[pushData.acl.account_id]+' '+ egw.lang('is busy on the phone'): '',
			}];
			if (pushData.acl.account_id2)
			{
				 content.push({
					id: account[pushData.acl.account_id2],
					class3: pushData.acl.account_id2 && pushData.acl.busy ? 'on-phone': ''
				});
			}
			this.mergeContent(content);
		}, egw);
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
					egw.request(
						"EGroupware\\Status\\Ui::ajax_getContactofLink",
						["rocketchat", data.account_id]
					).then((contact) =>
					{
						if (contact)
						{
							this.mailto(contact[0]['email']);
						}
					})
				}
				else
				{
					egw.accountData(data.account_id, 'account_email',null,
						(_data) =>
					{
						this.mailto(_data[data.account_id]);
					}, this);
				}

				break;
			case 'audiocall':
			case 'call':
				this.makeCall([{
					id: data.account_id,
					name: data.hint,
					avatar: "account:"+data.account_id,
					audioonly: _action.id == 'audiocall',
					data: data
				}]);

				break;
			case 'invite':
				this.inviteToCall([{
					id: data.account_id,
					name: data.hint,
					avatar: "account:"+data.account_id,
					audioonly: _action.id == 'audiocall',
					data: data
				}], egw.getSessionItem('status', 'videoconference-session'));
		}
		this.refresh();
	}

	/**
	 * Write mail to, taking force_mailto preference into account
	 *
	 * @param string value email-address
	 * @private
	 */
	private mailto(value)
	{
		if (value && egw.user('apps').mail && egw.preference('force_mailto','addressbook') != '1' )
		{
			this.egw.open_link('mailto:'+value);
		}
		else
		{
			window.open("mailto:" + value);
		}
	}

	/**
	 * Dialog for selecting users and add them to the favorites
	 */
	add_to_fav()
	{
		let list = this.et2.getArrayMgr('content').getEntry('list');
		let dialog = new Et2Dialog(this.egw);
		dialog.transformAttributes({
			callback: (_button_id, _value) =>
			{
				if(_button_id == 'add' && _value)
				{
					for(let i in _value.accounts)
					{
						let added = false;
						for(let j in list)
						{
							if(list[j] && list[j]['account_id'] == _value.accounts[i])
							{
								added = true;
								this.handle_actions({id: 'fav'}, [{data: list[j]}]);
							}
						}
						if(!added)
						{
							this.handle_actions({id: 'fav'}, [{
								data: {
									account_id: _value.accounts[i]
								}
							}]);
						}
					}
				}
			},
			title: this.egw.lang('Add to favorites'),
			buttons: [
				{label: this.egw.lang("Add"), id: "add", class: "ui-priority-primary", default: true, image: "add"},
				{label: this.egw.lang("Cancel"), id: "cancel", image: "cancel"}
			],
			value: {
				content: {
					value: '',
				}
			},
			template: egw.webserverUrl + '/status/templates/default/search_list.xet',
			resizable: false,
			width: 400,
		});
		document.body.appendChild(<HTMLElement><unknown>dialog);
	}

	/**
	 * Refresh the list
	 */
	refresh()
	{
		// give it a delay to make sure the preferences data is updated before refreshing
		window.setTimeout(() =>
		{
			egw.request('EGroupware\\Status\\Ui::ajax_refresh', []).then((_data) =>
			{
				if (this.et2) this.updateContent(_data.fav, _data.list);
			});
		}, 200);
	}

	/**
	 * Update content of fav and list girds
	 * @param {array} _fav
	 * @param {array} _list
	 */
	updateContent(_fav, _list)
	{
		let fav = <et2_grid>this.et2.getWidgetById('fav');
		let content = this.et2.getArrayMgr('content');
		let list = <et2_grid>this.et2.getWidgetById('list');
		const isEqual = (_a, _b) =>
		{
			if (_a.length != _b.length) return false;
			for (let i in _a)
			{
				if (JSON.stringify(_a[i]) != JSON.stringify(_b[i])) return false;
			}
			return true;
		};

		if (_fav && typeof _fav != 'undefined' && !isEqual(fav.getArrayMgr('content').data, _fav))
		{
			fav.set_value({content:_fav});
			content.data['fav'] = _fav;
		}
		if (_list && typeof _list != 'undefined' && !isEqual(list.getArrayMgr('content').data, _list))
		{
			list.set_value({content:_list});
			content.data['list'] = _list
		}
		this.et2.setArrayMgr('content', content);
	}

	/**
	 * Merge given content with existing ones and updates the lists
	 *
	 * @param {array} _content
	 * @param {boolean} _topList if true it pushes the content to top of the list
	 */
	mergeContent(_content, _topList?: boolean)
	{
		let fav = JSON.parse(JSON.stringify(this.et2.getArrayMgr('content').getEntry('fav')));
		let list = JSON.parse(JSON.stringify(this.et2.getArrayMgr('content').getEntry('list')));
		for (let i in _content)
		{
			for (let f in fav)
			{
				if (fav[f] && fav[f]['id'] && _content[i]['id'] == fav[f]['id'])
				{
					egw.deepExtend(fav[f], _content[i]);
				}
			}
			for (let l in list)
			{
				if (list[l] && list[l]['id'] && _content[i]['id'] == list[l]['id'])
				{
					egw.deepExtend(list[l], _content[i]);
					if (_topList || _content[i]['stat1'] > 0) list.splice(1, 0, list.splice(l, 1)[0]);
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
		// app.rocketchat is EPL-only and not typed here - see feedback_epl_stylite_blind_spot
		return !(_selected[0].data.data?.rocketchat?.type == 'c') && (_selected[0].data.data?.status?.active || (<any>app.rocketchat)?.isRCActive(_action, _selected));
	}

	/**
	 * Initiate call via action
	 * @param data
	 */
	makeCall(data)
	{
		let callCancelled = false;
		let button = [{"button_id": 0, "label": egw.lang('Cancel'), id: '0', image: 'cancel'}];
		let dialog = new Et2Dialog(this.egw);
		dialog.transformAttributes({
			callback: (_btn) =>
			{
				if(_btn == Et2Dialog.CANCEL_BUTTON)
				{
					callCancelled = true;
				}
			},
			title: 'Initiating call to',
			buttons: button,
			resizable: false,
			value: {
				content: {list: data}
			},
			template: egw.webserverUrl + '/status/templates/default/call.xet'
		});
		document.body.appendChild(<HTMLElement><unknown>dialog);
		setTimeout(() =>
		{
			if(!callCancelled)
			{
				dialog.destroy();
				egw.request(
					"EGroupware\\Status\\Videoconference\\Call::ajax_video_call",
					[data, data[0]['room']]).then((_url) =>
					{
						if(_url && _url.msg)
						{
							egw.message(_url.msg.message, _url.msg.type);
						}
						if(_url.caller)
						{
							this.openCall(_url.caller);
						}
						// app.rocketchat is EPL-only and not typed here
						if((<any>app.rocketchat)?.isRCActive(null, [{data: data[0].data}]))
						{
							(<any>app.rocketchat).restapi_call('chat_PostMessage', {
								roomId: data[0].data.data.rocketchat._id,
								attachments: [
									{
										"collapsed": false,
										"color": "#009966",
										"title": egw.lang("Click to Join!"),
										"title_link": _url.callee,
										"thumb_url": "https://raw.githubusercontent.com/EGroupware/status/master/templates/default/images/videoconference_call.svg",
									}
								]
							})
						}
					});
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
			{"button_id": 1, "label": egw.lang('Join'), id: '1', image: 'accept_call', default: true},
			{"button_id": 0, "label": egw.lang('Close'), id: '0', image: 'close'}
		];
		let notify = _notify || true;
		let content = _content || {};
		this._controllRingTone().start();
		let dialog = new Et2Dialog(this.egw);
		dialog.transformAttributes({
			callback: (_btn, value) =>
			{
				if(_btn == Et2Dialog.OK_BUTTON)
				{
					this.openCall(value.url);
				}
			},
			title: '',
			buttons: buttons,
			isModal: false,
			position: "right bottom,right-100 bottom-10",
			value: {
				content: content
			},
			resizable: false,
			template: egw.webserverUrl + '/status/templates/default/scheduled_call.xet'
		});
		document.body.appendChild(<HTMLElement><unknown>dialog);
		if(notify)
		{
			egw.notification(this.egw.lang('Status'), {
				body: this.egw.lang('You have a video conference meeting in %1 minutes, initiated by %2', (content['alarm-offset'] / 60), content.owner),
				icon: egw.webserverUrl + '/api/avatar.php?account_id=' + content.account_id,
				onclick: () => window.focus(),
				requireInteraction: true
			});
		}
	}

	/**
	 * gets called after receiving pushed call
	 * @param _data
	 * @param _notify
	 * @param _buttons
	 * @param _message_top
	 * @param _message_bottom
	 */
	receivedCall(_data, _notify?, _buttons?, _message_top?, _message_bottom?)
	{
		let buttons = _buttons || [
			{"button_id": 1, "text": egw.lang('Accept'), id: '1', image: 'accept_call', default: true},
			{"button_id": 0, "text": egw.lang('Reject'), id: '0', image: 'hangup'}
		];
		let notify = _notify?? true;
		let message_bottom = _message_bottom || '';
		let message_top = _message_top || '';
		let isCallAnswered = false;
		window.setTimeout(() =>
		{
			if (!isCallAnswered)
			{
				egw.request("EGroupware\\Status\\Videoconference\\Call::ajax_setMissedCallNotification", [_data]);
				egw.accountData(_data.caller.account_id, 'account_lid', null, (account) =>
				{
					this.mergeContent([{
						id: account[_data.caller.account_id],
						class1: 'missed-call',
					}]);
				}, egw);
				dialog.destroy();
			}
		}, statusApp.MISSED_CALL_TIMEOUT);
		this._controllRingTone().start(true);
		let dialog = new Et2Dialog(this.egw);
		dialog.transformAttributes({
			callback: (_btn) =>
			{
				if(_btn == Et2Dialog.OK_BUTTON)
				{
					this.openCall(_data.call);
					isCallAnswered = true;
				}
			},
			title: 'Call from',
			buttons: buttons,
			isModal: false,
			position:"right bottom, right bottom",
			value: {
				content: {
					list:[{
						"name":_data.caller.name,
						"avatar": "account:" + _data.caller.account_id,
					}],
					"message_buttom": egw.lang(message_bottom),
					"message_top": egw.lang(message_top),
					"url": _data.call
				}
			},
			resizable: false,
			template: egw.webserverUrl + '/status/templates/default/call.xet',
			dialogClass: "recievedCall"
		});
		dialog.addEventListener('close', () => this._controllRingTone().stop());
		document.body.appendChild(<HTMLElement><unknown>dialog);
		if(notify)
		{
			egw.notification(this.egw.lang('Status'), {
				body: this.egw.lang('You have a call from %1', _data.caller.name),
				icon: egw.webserverUrl + '/api/avatar.php?account_id=' + _data.caller.account_id,
				onclick: () => window.focus(),
				requireInteraction: true
			});
		}
	}

	private _controllRingTone()
	{
		// "stop" is a plain arrow (not an object-literal method) so "initiate" below can call it
		// directly, rather than relying on its own "this" being the returned object (which an arrow
		// function there wouldn't be).
		const stop = () =>
		{
			if (!this._ring) return;
			this._ring.pause();
		};
		return {
			start: (_loop?) =>
			{
				if (!this._ring) return;
				this._ring.loop = _loop || false;
				this._ring.muted = false;
				this._ring.play().then(() =>
				{
					window.setTimeout(() =>
					{
						stop();
					}, statusApp.MISSED_CALL_TIMEOUT); // stop ringing automatically
				}, (_error) =>
				{
					console.log('Error happened: '+_error);
				});
			},
			stop,
			initiate: () =>
			{
				this._ring.muted = true;
				this._ring.play().then(() =>
				{

				}, (_error) =>
				{
					console.log('Error happened: '+_error);
				});
				stop();
			}
		}
	}

	public didNotPickUp(_data)
	{
		Et2Dialog.show_dialog((_btn) =>
		{
			if(Et2Dialog.YES_BUTTON == _btn)
			{
				this.makeCall([_data]);
			}
		}, this.egw.lang('%1 did not pickup your call, would you like to try again?', _data.name), '');
	}

	/**
	 * Missed callback dialog
	 * @param _from
	 * @param _url
	 */
	public _phoneMissedCallback (_from, _url)
	{
		return Et2Dialog.show_dialog((_btn) =>
		{
			if(_btn == Et2Dialog.YES_BUTTON)
			{
				egw.message(egw.lang("Calling back %1 ...", _from));
				let url = <Et2UrlPhoneReadonly>et2_createWidget('url-phone', {id: 'temp_url_phone', readonly: true}, this.et2);
				url.set_value(_url);
				url.click();
				url.destroy();
			}
			this.mergeContent([{id: _from, class2: '', action2: ''}])
		}, "Would you like to callback?", "Missed call", null, Et2Dialog.BUTTONS_YES_NO);
	}

	public phoneCall(_action, _selected)
	{
		let data : any = _selected[0]['data'];

		let target = '';
		switch(_action.id)
		{
			case 'addressbook_tel_work':
				target = data.data.status?.tel_work;
				break;
			case 'addressbook_tel_cell':
				target = data.data.status?.tel_cell;
				break;
			case 'addressbook_tel_prefer':
				target = data.data.status?.tel_prefer;
				break;
			case 'addressbook_tel_home':
				target = data.data.status?.tel_home;
				break;
		}
		if (target)
		{
			let url = <Et2UrlPhoneReadonly>et2_createWidget('url-phone', {id:'temp_url_phone', readonly: true}, this.et2);
			url.set_value(target);
			url.click();
			url.destroy();
		}
	}

	public phoneIsAvailable(_action, _selected)
	{
		let data : any = _selected[0]['data'];

		if (!data.data?.status) return false;

		switch(_action.id)
		{
			case 'addressbook_tel_work':
				if (data.data.status?.tel_work) return true;
				break;
			case 'addressbook_tel_cell':
				if (data.data.status?.tel_cell) return true;
				break;
			case 'addressbook_tel_prefer':
				if (data.data.status?.tel_prefer) return true;
				break;
			case 'addressbook_tel_home':
				if (data.data.status?.tel_home) return true;
				break;
		}
		return false;
	}

	public videoconference_invite ()
	{
		let url = this.et2.getArrayMgr('content').getEntry('frame');

		let dialog = new Et2Dialog(this.egw);
		dialog.transformAttributes({
			callback: (_button_id, _value) =>
			{
				if(_button_id == 'add' && _value)
				{
					let data = [];
					for(let i in _value.accounts)
					{
						data.push({
							id: _value.accounts[i],
							name: '',
							avatar: "account:"+_value.accounts[i]
						})
					}
					egw.request("EGroupware\\Status\\Videoconference\\Call::ajax_video_call",
						[data, statusApp.videoconference_fetchRoomFromUrl(url), true, true]).then((_data) =>
						{
							if (_data && _data.msg) egw(window).message(_data.msg.message, _data.msg.type);
						});
				}
			},
			title: this.egw.lang('Invite to this meeting'),
			buttons: [
				{label: this.egw.lang("Invite"), id: "add", class: "ui-priority-primary", default: true},
				{label: this.egw.lang("Cancel"), id: "cancel"}
			],
			value: {
				content: {
					value: '',
				}
			},
			template: egw.webserverUrl + '/status/templates/default/search_list.xet',
			resizable: false,
			width: 400,
		});
		document.body.appendChild(<HTMLElement><unknown>dialog);
	}

	/**
	 * end session
	 * @private
	 */
	public videoconference_endMeeting ()
	{
		let room = this.et2.getArrayMgr('content').getEntry('room');
		let url = this.et2.getArrayMgr('content').getEntry('frame');
		let isModerator = url.match(/isModerator\=(1|true)/i)??false;
		if (isModerator)
		{
			Et2Dialog.show_dialog((_b) =>
				{
					if(_b == 1)
					{
						egw(window).loading_prompt(room, true, egw.lang('Ending the session ...'));
						egw.request("EGroupware\\Status\\Videoconference\\Call::ajax_deleteRoom", [room, url])
							.then(() =>
							{
								egw(window).loading_prompt(room, false);
							});
						return true;
					}
				}, "This window will end the session for everyone, are you sure want this?",
				"End Meeting", {}, Et2Dialog.BUTTONS_OK_CANCEL, Et2Dialog.WARNING_MESSAGE);
		}
	}

	/**
	 * @param _room
	 */
	public videoconference_getRecordings(_room, _params)
	{
		egw.openPopup(egw.link('/index.php', {
			menuaction: 'status.\\EGroupware\\Status\\Ui.vc_recordings',
			room: _room,
			cal_id: _params['cal_id'],
			title: _params['title']
		}), 800, 450, 'recordings', 'status');
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
		egw.request("EGroupware\\Status\\Videoconference\\Call::ajax_video_call",
			[_data, _room , true, true]).then((_data) =>
		{
			if (_data && _data.msg) egw(window).message(_data.msg.message, _data.msg.type);
		});
	}

	public videoconference_countdown_finished() {
		let join = <et2_button>this.et2.getWidgetById('join');
		join.set_disabled(false);
	}

	public videoconference_countdown_join()
	{
		let content = this.et2.getArrayMgr('content');
		egw.request(
			"EGroupware\\Status\\Videoconference\\Call::ajax_genMeetingUrl",
			[content.getEntry('room'),
				{
					name:egw.user('account_fullname'),
					account_id:egw.user('account_id'),
					email:egw.user('account_email'),
					cal_id:content.getEntry('cal_id')
				}, content.getEntry('start'), content.getEntry('end')]).then((_data) =>
				{
					if (_data)
					{
						if (_data.err) egw.message(_data.err, 'error');
						// app.status is EgwApp-typed generically, but at runtime is this very class
						if(_data.url) (<statusApp>app.status).openCall(_data.url);
					}
				});
		window.parent.close();
	}

	public vc_deleteRecording(_event, _widget)
	{
		let recordings = this.et2.getArrayMgr('content').getEntry('recordings');
		let id = _widget.id.replace('delete', '');
		recordings[id]['cal_id'] = this.et2.getArrayMgr('content').getEntry('cal_id');
		egw.request('EGroupware\\Status\\Ui::ajax_vc_deleteRecording', recordings[id]).then((_data) =>
		{
			if (_data['success'])
			{
				this.et2.getInstanceManager().submit();
			}
			else
			{
				egw.message(_data['error'], 'error');
			}
		});
	}
}
app.classes.status = statusApp;