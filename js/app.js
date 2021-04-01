"use strict";
/**
 * EGroupware - Status
 *
 * @link http://www.egroupware.org
 * @package Status
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @copyright (c) 2019 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
/*egw:uses
    /api/js/jsapi/egw_app.js;
 */
var egw_app_1 = require("../../api/js/jsapi/egw_app");
var et2_widget_dialog_1 = require("../../api/js/etemplate/et2_widget_dialog");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var statusApp = /** @class */ (function (_super) {
    __extends(statusApp, _super);
    /**
     * Constructor
     *
     * @memberOf app.status
     */
    function statusApp() {
        var _this = 
        // call parent
        _super.call(this, 'status') || this;
        _this._ring = null;
        return _this;
    }
    /**
     * Destructor
     */
    statusApp.prototype.destroy = function (_app) {
        // call parent
        _super.prototype.destroy.call(this, _app);
    };
    /**
     * This function is called when the etemplate2 object is loaded
     * and ready.  If you must store a reference to the et2 object,
     * make sure to clean it up in destroy().
     *
     * @param {etemplate2} _et2 newly ready object
     * @param {string} _name template name
     */
    statusApp.prototype.et2_ready = function (_et2, _name) {
        var _c;
        // call parent
        _super.prototype.et2_ready.call(this, _et2, _name);
        switch (_name) {
            case 'status.index':
                if (egw.preference('ringtone', 'status')) {
                    this._ring = new Audio('status/assets/ring.mp3');
                    var self_1 = this;
                    jQuery('body').one('click', function () {
                        self_1._controllRingTone().initiate();
                    });
                }
                break;
            case 'status.room':
                var room = this.et2.getArrayMgr('content').getEntry('room');
                var url = this.et2.getArrayMgr('content').getEntry('frame');
                var end = this.et2.getDOMWidgetById('end');
                var isModerator = (_c = url.match(/isModerator\=(1|true)/i)) !== null && _c !== void 0 ? _c : false;
                if (isModerator) {
                    end.set_disabled(false);
                }
                if (url.match(/\&error\=/i) || (!isModerator && this.et2.getArrayMgr('content').getEntry('restrict'))) {
                    this.et2.getDOMWidgetById('add').set_disabled(true);
                    break;
                }
                egw(window.opener).setSessionItem('status', 'videoconference-session', room);
                window.addEventListener("beforeunload", function () {
                    window.opener.sessionStorage.removeItem('status-videoconference-session');
                }, false);
                break;
        }
    };
    /**
     * Handle executed action on selected row and refresh the list
     *
     * @param {type} _action
     * @param {type} _selected
     */
    statusApp.prototype.handle_actions = function (_action, _selected) {
        var data = _selected[0]['data'];
        var fav = egw.preference('fav', 'status') || {};
        var favorites = Object.keys(fav).map(function (key) { return fav[key]; });
        switch (_action.id) {
            case 'fav':
                favorites.push(data.account_id);
                egw.set_preference('status', 'fav', favorites);
                break;
            case 'unfavorite':
                for (var i in favorites) {
                    if (favorites[i] == data.account_id)
                        favorites.splice(i, 1);
                }
                egw.set_preference('status', 'fav', favorites);
                break;
            case 'mail':
                if (typeof data.account_id == "string" && data.account_id.match(/:/) && data.link_to) {
                    egw.json("EGroupware\\Status\\Ui::ajax_getContactofLink", ["rocketchat", data.account_id], function (contact) {
                        if (contact) {
                            egw.open('', 'mail', 'add', { 'preset[mailto]': +contact[0]['email'] });
                        }
                    }).sendRequest();
                }
                else {
                    egw.accountData(data.account_id, 'account_email', null, function (_data) {
                        egw.open('', 'mail', 'add', { 'preset[mailto]': _data[data.account_id] });
                    }, this);
                }
                break;
            case 'audiocall':
            case 'call':
                this.makeCall([{
                        id: data.account_id,
                        name: data.hint,
                        avatar: "account:" + data.account_id,
                        audioonly: _action.id == 'audiocall',
                        data: data
                    }]);
                break;
            case 'invite':
                this.inviteToCall([{
                        id: data.account_id,
                        name: data.hint,
                        avatar: "account:" + data.account_id,
                        audioonly: _action.id == 'audiocall',
                        data: data
                    }], egw.getSessionItem('status', 'videoconference-session'));
        }
        this.refresh();
    };
    /**
     * Dialog for selecting users and add them to the favorites
     */
    statusApp.prototype.add_to_fav = function () {
        var list = this.et2.getArrayMgr('content').getEntry('list');
        var self = this;
        et2_core_widget_1.et2_createWidget("dialog", {
            callback: function (_button_id, _value) {
                if (_button_id == 'add' && _value) {
                    for (var i in _value.accounts) {
                        var added = false;
                        for (var j in list) {
                            if (list[j] && list[j]['account_id'] == _value.accounts[i]) {
                                added = true;
                                self.handle_actions({ id: 'fav' }, [{ data: list[j] }]);
                            }
                        }
                        if (!added)
                            self.handle_actions({ id: 'fav' }, [{ data: {
                                        account_id: _value.accounts[i]
                                    } }]);
                    }
                }
            },
            title: this.egw.lang('Add to favorites'),
            buttons: [
                { text: this.egw.lang("Add"), id: "add", class: "ui-priority-primary", default: true },
                { text: this.egw.lang("Cancel"), id: "cancel" }
            ],
            value: {
                content: {
                    value: '',
                }
            },
            template: egw.webserverUrl + '/status/templates/default/search_list.xet',
            resizable: false,
            width: 400,
        }, et2_widget_dialog_1.et2_dialog._create_parent('status'));
    };
    /**
     * Refresh the list
     */
    statusApp.prototype.refresh = function () {
        var self = this;
        // give it a delay to make sure the preferences data is updated before refreshing
        window.setTimeout(function () {
            egw.json('EGroupware\\Status\\Ui::ajax_refresh', [], function (_data) {
                self.updateContent(_data.fav, _data.list);
            }).sendRequest();
        }, 200);
    };
    /**
     * Update content of fav and list girds
     * @param {array} _fav
     * @param {array} _list
     */
    statusApp.prototype.updateContent = function (_fav, _list) {
        var fav = this.et2.getWidgetById('fav');
        var content = this.et2.getArrayMgr('content');
        var list = this.et2.getWidgetById('list');
        var isEqual = function (_a, _b) {
            if (_a.length != _b.length)
                return false;
            for (var i in _a) {
                if (JSON.stringify(_a[i]) != JSON.stringify(_b[i]))
                    return false;
            }
            return true;
        };
        if (_fav && typeof _fav != 'undefined' && !isEqual(fav.getArrayMgr('content').data, _fav)) {
            fav.set_value({ content: _fav });
            content.data['fav'] = _fav;
        }
        if (_list && typeof _list != 'undefined' && !isEqual(list.getArrayMgr('content').data, _list)) {
            list.set_value({ content: _list });
            content.data['list'] = _list;
        }
        this.et2.setArrayMgr('content', content);
    };
    /**
     * Merge given content with existing ones and updates the lists
     *
     * @param {array} _content
     * @param {boolean} _topList if true it pushes the content to top of the list
     */
    statusApp.prototype.mergeContent = function (_content, _topList) {
        var fav = JSON.parse(JSON.stringify(this.et2.getArrayMgr('content').getEntry('fav')));
        var list = JSON.parse(JSON.stringify(this.et2.getArrayMgr('content').getEntry('list')));
        for (var i in _content) {
            for (var f in fav) {
                if (fav[f] && fav[f]['id'] && _content[i]['id'] == fav[f]['id']) {
                    jQuery.extend(true, fav[f], _content[i]);
                }
            }
            for (var l in list) {
                if (list[l] && list[l]['id'] && _content[i]['id'] == list[l]['id']) {
                    jQuery.extend(true, list[l], _content[i]);
                    if (_topList || _content[i]['stat1'] > 0)
                        list.splice(1, 0, list.splice(l, 1)[0]);
                }
            }
        }
        this.updateContent(fav, list);
    };
    statusApp.prototype.getEntireList = function () {
        var fav = this.et2.getArrayMgr('content').getEntry('fav');
        var list = this.et2.getArrayMgr('content').getEntry('list');
        var result = [];
        for (var f in fav) {
            if (fav[f] && fav[f]['id'])
                result.push(fav[f]);
        }
        for (var l in list) {
            if (list[l] && list[l]['id'])
                result.push(list[l]);
        }
        return result;
    };
    statusApp.prototype.isOnline = function (_action, _selected) {
        var _c, _d, _e;
        return !(((_c = _selected[0].data.data.rocketchat) === null || _c === void 0 ? void 0 : _c.type) == 'c') && (((_d = _selected[0].data.data.status) === null || _d === void 0 ? void 0 : _d.active) || ((_e = app.rocketchat) === null || _e === void 0 ? void 0 : _e.isRCActive(_action, _selected)));
    };
    /**
     * Initiate call via action
     * @param data
     */
    statusApp.prototype.makeCall = function (data) {
        var callCancelled = false;
        var self = this;
        var button = [{ "button_id": 0, "text": egw.lang('Cancel'), id: '0', image: 'cancel' }];
        var dialog = et2_core_widget_1.et2_createWidget("dialog", {
            callback: function (_btn) {
                if (_btn == et2_widget_dialog_1.et2_dialog.CANCEL_BUTTON) {
                    callCancelled = true;
                }
            },
            title: this.egw.lang('Initiating call to'),
            buttons: button,
            minWidth: 300,
            minHeight: 200,
            resizable: false,
            value: {
                content: { list: data }
            },
            template: egw.webserverUrl + '/status/templates/default/call.xet'
        }, et2_widget_dialog_1.et2_dialog._create_parent(this.appname));
        setTimeout(function () {
            if (!callCancelled) {
                dialog.destroy();
                egw.json("EGroupware\\Status\\Videoconference\\Call::ajax_video_call", [data, data[0]['room']], function (_url) {
                    var _c;
                    if (_url && _url.msg)
                        egw.message(_url.msg.message, _url.msg.type);
                    if (_url.caller)
                        self.openCall(_url.caller);
                    if ((_c = app.rocketchat) === null || _c === void 0 ? void 0 : _c.isRCActive(null, [{ data: data[0].data }])) {
                        app.rocketchat.restapi_call('chat_PostMessage', {
                            roomId: data[0].data.data.rocketchat._id,
                            attachments: [
                                {
                                    "collapsed": false,
                                    "color": "#009966",
                                    "title": egw.lang("Click to Join!"),
                                    "title_link": _url.callee,
                                    "thumb_url": "https://raw.githubusercontent.com/EGroupware/status/master/templates/pixelegg/images/videoconference_call.svg",
                                }
                            ]
                        });
                    }
                }).sendRequest();
            }
        }, 3000);
    };
    /**
     * Open call url with respecting opencallin preference
     * @param _url call url
     */
    statusApp.prototype.openCall = function (_url) {
        var link = egw.link('/index.php', {
            menuaction: 'status.\\EGroupware\\Status\\Ui.room',
            frame: _url
        });
        if (egw.preference('opencallin', statusApp.appname) == '1') {
            egw.open_link(link, '_blank');
        }
        else {
            egw.openPopup(link, 800, 600, '', 'status');
        }
    };
    statusApp.prototype.scheduled_receivedCall = function (_content, _notify) {
        var buttons = [
            { "button_id": 1, "text": egw.lang('Join'), id: '1', image: 'accept_call', default: true },
            { "button_id": 0, "text": egw.lang('Close'), id: '0', image: 'close' }
        ];
        var notify = _notify || true;
        var content = _content || {};
        var self = this;
        this._controllRingTone().start();
        et2_core_widget_1.et2_createWidget("dialog", {
            callback: function (_btn, value) {
                if (_btn == et2_widget_dialog_1.et2_dialog.OK_BUTTON) {
                    self.openCall(value.url);
                }
            },
            title: '',
            buttons: buttons,
            minWidth: 200,
            minHeight: 300,
            modal: false,
            position: "right bottom,right-100 bottom-10",
            value: {
                content: content
            },
            resizable: false,
            template: egw.webserverUrl + '/status/templates/default/scheduled_call.xet'
        }, et2_widget_dialog_1.et2_dialog._create_parent(this.appname));
        if (notify) {
            egw.notification(this.egw.lang('Status'), {
                body: this.egw.lang('You have a video conference meeting in %1 minutes, initiated by %2', (content['alarm-offset'] / 60), content.owner),
                icon: egw.webserverUrl + '/api/avatar.php?account_id=' + content.account_id,
                onclick: function () {
                    window.focus();
                },
                requireInteraction: true
            });
        }
    };
    /**
     * gets called after receiving pushed call
     * @param _data
     * @param _notify
     * @param _buttons
     * @param _message_top
     * @param _message_bottom
     */
    statusApp.prototype.receivedCall = function (_data, _notify, _buttons, _message_top, _message_bottom) {
        var buttons = _buttons || [
            { "button_id": 1, "text": egw.lang('Accept'), id: '1', image: 'accept_call', default: true },
            { "button_id": 0, "text": egw.lang('Reject'), id: '0', image: 'hangup' }
        ];
        var notify = _notify !== null && _notify !== void 0 ? _notify : true;
        var message_bottom = _message_bottom || '';
        var message_top = _message_top || '';
        var self = this;
        var isCallAnswered = false;
        window.setTimeout(function () {
            if (!isCallAnswered) {
                egw.json("EGroupware\\Status\\Videoconference\\Call::ajax_setMissedCallNotification", [_data], function () { }).sendRequest();
                egw.accountData(_data.caller.account_id, 'account_lid', null, function (account) {
                    self.mergeContent([{
                            id: account[_data.caller.account_id],
                            class1: 'missed-call',
                        }]);
                }, egw);
                dialog.destroy();
            }
        }, statusApp.MISSED_CALL_TIMEOUT);
        this._controllRingTone().start(true);
        var dialog = et2_core_widget_1.et2_createWidget("dialog", {
            callback: function (_btn, value) {
                if (_btn == et2_widget_dialog_1.et2_dialog.OK_BUTTON) {
                    self.openCall(value.url);
                    isCallAnswered = true;
                }
            },
            beforeClose: function () {
                self._controllRingTone().stop();
            },
            title: 'Call from',
            buttons: buttons,
            minWidth: 200,
            minHeight: 200,
            modal: false,
            position: "right bottom, right bottom",
            value: {
                content: {
                    list: [{
                            "name": _data.caller.name,
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
        }, et2_widget_dialog_1.et2_dialog._create_parent(this.appname));
        if (notify) {
            egw.notification(this.egw.lang('Status'), {
                body: this.egw.lang('You have a call from %1', _data.caller.name),
                icon: egw.webserverUrl + '/api/avatar.php?account_id=' + _data.caller.account_id,
                onclick: function () {
                    window.focus();
                },
                requireInteraction: true
            });
        }
    };
    statusApp.prototype._controllRingTone = function () {
        var self = this;
        return {
            start: function (_loop) {
                if (!self._ring)
                    return;
                self._ring.loop = _loop || false;
                self._ring.muted = false;
                self._ring.play().then(function () {
                    window.setTimeout(function () {
                        self._controllRingTone().stop();
                    }, statusApp.MISSED_CALL_TIMEOUT); // stop ringing automatically
                }, function (_error) {
                    console.log('Error happened: ' + _error);
                });
            },
            stop: function () {
                if (!self._ring)
                    return;
                self._ring.pause();
            },
            initiate: function () {
                self._ring.muted = true;
                self._ring.play().then(function () {
                }, function (_error) {
                    console.log('Error happened: ' + _error);
                });
                this.stop();
            }
        };
    };
    statusApp.prototype.didNotPickUp = function (_data) {
        var self = this;
        et2_widget_dialog_1.et2_dialog.show_dialog(function (_btn) {
            if (et2_widget_dialog_1.et2_dialog.YES_BUTTON == _btn) {
                self.makeCall([_data]);
            }
        }, this.egw.lang('%1 did not pickup your call, would you like to try again?', _data.name), '');
    };
    statusApp.prototype.phoneCall = function (_action, _selected) {
        var _c, _d, _e, _f;
        var data = _selected[0]['data'];
        var target = '';
        switch (_action.id) {
            case 'addressbook_tel_work':
                target = (_c = data.data.status) === null || _c === void 0 ? void 0 : _c.tel_work;
                break;
            case 'addressbook_tel_cell':
                target = (_d = data.data.status) === null || _d === void 0 ? void 0 : _d.tel_cell;
                break;
            case 'addressbook_tel_prefer':
                target = (_e = data.data.status) === null || _e === void 0 ? void 0 : _e.tel_prefer;
                break;
            case 'addressbook_tel_home':
                target = (_f = data.data.status) === null || _f === void 0 ? void 0 : _f.tel_home;
                break;
        }
        if (target) {
            var url = et2_core_widget_1.et2_createWidget('url-phone', { id: 'temp_url_phone', readonly: true }, this.et2);
            url.set_value(target);
            url.span.click();
            url.destroy();
        }
    };
    statusApp.prototype.phoneIsAvailable = function (_action, _selected) {
        var _c, _d, _e, _f;
        var data = _selected[0]['data'];
        switch (_action.id) {
            case 'addressbook_tel_work':
                if ((_c = data.data.status) === null || _c === void 0 ? void 0 : _c.tel_work)
                    return true;
                break;
            case 'addressbook_tel_cell':
                if ((_d = data.data.status) === null || _d === void 0 ? void 0 : _d.tel_cell)
                    return true;
                break;
            case 'addressbook_tel_prefer':
                if ((_e = data.data.status) === null || _e === void 0 ? void 0 : _e.tel_prefer)
                    return true;
                break;
            case 'addressbook_tel_home':
                if ((_f = data.data.status) === null || _f === void 0 ? void 0 : _f.tel_home)
                    return true;
                break;
        }
        return false;
    };
    statusApp.prototype.videoconference_invite = function () {
        var url = this.et2.getArrayMgr('content').getEntry('frame');
        et2_core_widget_1.et2_createWidget("dialog", {
            callback: function (_button_id, _value) {
                if (_button_id == 'add' && _value) {
                    var data = [];
                    for (var i in _value.accounts) {
                        data.push({
                            id: _value.accounts[i],
                            name: '',
                            avatar: "account:" + _value.accounts[i]
                        });
                    }
                    egw.json("EGroupware\\Status\\Videoconference\\Call::ajax_video_call", [data, statusApp.videoconference_fetchRoomFromUrl(url), true, true], function (_data) {
                        if (_data && _data.msg)
                            egw(window).message(_data.msg.message, _data.msg.type);
                    }).sendRequest();
                }
            },
            title: this.egw.lang('Invite to this meeting'),
            buttons: [
                { text: this.egw.lang("Invite"), id: "add", class: "ui-priority-primary", default: true },
                { text: this.egw.lang("Cancel"), id: "cancel" }
            ],
            value: {
                content: {
                    value: '',
                }
            },
            template: egw.webserverUrl + '/status/templates/default/search_list.xet',
            resizable: false,
            width: 400,
        }, et2_widget_dialog_1.et2_dialog._create_parent('status'));
    };
    /**
     * end session
     * @private
     */
    statusApp.prototype.videoconference_endMeeting = function () {
        var _c;
        var room = this.et2.getArrayMgr('content').getEntry('room');
        var url = this.et2.getArrayMgr('content').getEntry('frame');
        var isModerator = (_c = url.match(/isModerator\=(1|true)/i)) !== null && _c !== void 0 ? _c : false;
        if (isModerator) {
            et2_widget_dialog_1.et2_dialog.show_dialog(function (_b) {
                if (_b == 1) {
                    egw(window).loading_prompt(room, true, egw.lang('Ending the session ...'));
                    egw.json("EGroupware\\Status\\Videoconference\\Call::ajax_deleteRoom", [room, url], function () {
                        egw(window).loading_prompt(room, false);
                    }).sendRequest();
                    return true;
                }
            }, "This window will end the session for everyone, are you sure want this?", "End Meeting", {}, et2_widget_dialog_1.et2_dialog.BUTTONS_OK_CANCEL, et2_widget_dialog_1.et2_dialog.WARNING_MESSAGE);
        }
    };
    statusApp.videoconference_fetchRoomFromUrl = function (_url) {
        if (_url) {
            return _url.split(/\?jwt/)[0].split('/').pop();
        }
        return null;
    };
    statusApp.prototype.isThereAnyCall = function (_action, _selected) {
        return this.isOnline(_action, _selected) && egw.getSessionItem('status', 'videoconference-session');
    };
    statusApp.prototype.inviteToCall = function (_data, _room) {
        egw.json("EGroupware\\Status\\Videoconference\\Call::ajax_video_call", [_data, _room, true, true], function (_data) {
            if (_data && _data.msg)
                egw(window).message(_data.msg.message, _data.msg.type);
        }).sendRequest();
    };
    statusApp.prototype.videoconference_countdown_finished = function () {
        var join = this.et2.getWidgetById('join');
        join.set_disabled(false);
    };
    statusApp.prototype.videoconference_countdown_join = function () {
        var content = this.et2.getArrayMgr('content');
        egw.json("EGroupware\\Status\\Videoconference\\Call::ajax_genMeetingUrl", [content.getEntry('room'),
            {
                name: egw.user('account_fullname'),
                account_id: egw.user('account_id'),
                email: egw.user('account_email'),
                cal_id: content.getEntry('cal_id')
            }, content.getEntry('start'), content.getEntry('end')], function (_data) {
            if (_data) {
                if (_data.err)
                    egw.message(_data.err, 'error');
                if (_data.url)
                    app.status.openCall(_data.url);
            }
        }).sendRequest();
        window.parent.close();
    };
    statusApp.appname = 'status';
    statusApp.MISSED_CALL_TIMEOUT = egw.preference('ringingtimeout', 'status') ?
        parseInt(egw.preference('ringingtimeout', 'status')) * 1000 : 15000;
    return statusApp;
}(egw_app_1.EgwApp));
app.classes.status = statusApp;
//# sourceMappingURL=app.js.map
