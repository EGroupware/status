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
var statusApp = /** @class */ (function (_super) {
    __extends(statusApp, _super);
    /**
     * Constructor
     *
     * @memberOf app.status
     */
    function statusApp() {
        // call parent
        return _super.call(this, 'status') || this;
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
        // call parent
        _super.prototype.et2_ready.call(this, _et2, _name);
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
                    });
                }
                break;
            case 'call':
                this.makeCall([{
                        id: data.account_id,
                        name: data.hint,
                        avatar: "account:" + data.account_id
                    }]);
                break;
        }
        this.refresh();
    };
    /**
     * Dialog for selecting users and add them to the favorites
     */
    statusApp.prototype.add_to_fav = function () {
        var list = this.et2.getArrayMgr('content').getEntry('list');
        var self = this;
        et2_createWidget("dialog", {
            callback: function (_button_id, _value) {
                if (_button_id == 'add' && _value) {
                    for (var i in _value.accounts) {
                        for (var j in list) {
                            if (list[j]['account_id'] == _value.accounts[i]) {
                                self.handle_actions({ id: 'fav' }, [{ data: list[j] }]);
                            }
                        }
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
        }, et2_dialog._create_parent('status'));
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
        if (_fav && typeof _fav != 'undefined') {
            fav.set_value({ content: _fav });
            content.data.fav = _fav;
        }
        if (_list && typeof _list != 'undefined') {
            list.set_value({ content: _list });
            content.data.list = _list;
        }
        this.et2.setArrayMgr('content', content);
    };
    /**
     * Merge given content with existing ones and updates the lists
     *
     * @param {array} _content
     */
    statusApp.prototype.mergeContent = function (_content) {
        var fav = this.et2.getArrayMgr('content').getEntry('fav');
        var list = this.et2.getArrayMgr('content').getEntry('list');
        for (var i in _content) {
            for (var f in fav) {
                if (fav[f] && fav[f]['id'] && _content[i]['id'] == fav[f]['id']) {
                    jQuery.extend(true, fav[f], _content[i]);
                }
            }
            for (var l in list) {
                if (list[l] && list[l]['id'] && _content[i]['id'] == list[l]['id']) {
                    jQuery.extend(true, list[l], _content[i]);
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
        return _selected[0].data.data.status.active;
    };
    /**
     * Initiate call via action
     * @param array data
     */
    statusApp.prototype.makeCall = function (data) {
        var callCancelled = false;
        var self = this;
        var button = [{ "button_id": 0, "text": 'cancel', id: '0', image: 'cancel' }];
        var dialog = et2_createWidget("dialog", {
            callback: function (_btn) {
                if (_btn == et2_dialog.CANCEL_BUTTON) {
                    callCancelled = true;
                }
            },
            title: egw.lang('Initiating call to'),
            buttons: button,
            minWidth: 300,
            minHeight: 200,
            resizable: false,
            value: {
                content: { list: data }
            },
            template: egw.webserverUrl + '/status/templates/default/call.xet'
        }, et2_dialog._create_parent(this.appname));
        setTimeout(function () {
            if (!callCancelled) {
                dialog.destroy();
                egw.json("EGroupware\\Status\\Videoconference\\Call::ajax_video_call", [data], function (_url) {
                    statusApp._openCall(_url);
                }).sendRequest();
            }
        }, 3000);
    };
    /**
     * Open call url with respecting opencallin preference
     * @param _url call url
     */
    statusApp._openCall = function (_url) {
        if (egw.preference('opencallin', statusApp.appname) == '1') {
            egw.openPopup(_url, 450, 450);
        }
        else {
            window.open(_url);
        }
    };
    /**
     * gets called after receiving pushed call
     * @param _data
     */
    statusApp.prototype.receivedCall = function (_data) {
        var button = [
            { "button_id": 1, "text": 'accept', id: '1', image: 'accept_call', default: true },
            { "button_id": 0, "text": 'reject', id: '0', image: 'hangup' }
        ];
        var self = this;
        et2_createWidget("dialog", {
            callback: function (_btn, value) {
                if (_btn == et2_dialog.OK_BUTTON) {
                    statusApp._openCall(value.url);
                }
            },
            title: '',
            buttons: button,
            minWidth: 300,
            minHeight: 200,
            value: {
                content: {
                    list: [{
                            "name": _data.caller.name,
                            "avatar": "account:" + _data.caller.account_id,
                        }],
                    "message_buttom": egw.lang('is calling'),
                    "url": _data.call
                }
            },
            resizable: false,
            template: egw.webserverUrl + '/status/templates/default/call.xet'
        }, et2_dialog._create_parent(this.appname));
        egw.notification(this.egw.lang('Status'), {
            body: this.egw.lang('You have a call from %1', _data.caller.name),
            icon: egw.webserverUrl + '/api/avatar.php?account_id=' + _data.caller.account_id,
            onclick: function () {
                window.focus();
            },
            requireInteraction: true
        });
    };
    statusApp.appname = 'status';
    return statusApp;
}(egw_app_1.EgwApp));
app.classes.status = statusApp;
//# sourceMappingURL=app.js.map