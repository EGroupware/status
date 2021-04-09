<?php
/**
 * EGroupware Status - setup
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @package status
 * @copyright (c) 2019 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */


use EGroupware\Status\Hooks;

$setup_info['status']['name']    = 'status';
$setup_info['status']['title']   = 'Status';
$setup_info['status']['version'] = '20.1';
$setup_info['status']['enable']  = 5; // status 5 means load application in background, tab and sidebox will not be shown but index page will be loaded
$setup_info['status']['autoinstall'] = true;	// install automatically on update
$setup_info['status']['author'] = 'Hadi Nategh';
$setup_info['status']['index'] = array('menuaction' => 'status.EGroupware\\Status\\Ui.index&ajax=true');
$setup_info['status']['maintainer'] = array(
	'name'  => 'EGroupware GmbH',
	'url'   => 'https://www.egroupware.org',
);
$setup_info['status']['license']  = 'GPL';
$setup_info['status']['description'] = '';

/* The hooks this app includes, needed for hooks registration */
$setup_info['status']['hooks']['status-getStatus'] = Hooks::class.'::getStatus';
$setup_info['status']['hooks']['status-get_actions'] = Hooks::class.'::get_actions';
$setup_info['status']['hooks']['check_notify'] = Hooks::class.'::updateState';
$setup_info['status']['hooks']['config'] = Hooks::class.'::config';
$setup_info['status']['hooks']['admin'] = Hooks::class.'::menu';
$setup_info['status']['hooks']['settings'] = Hooks::class.'::settings';
$setup_info['status']['hooks']['notifications_actions'] = Hooks::class.'::notifications_actions';
$setup_info['status']['hooks']['session_created'] =  Hooks::class.'::sessionCreated';
$setup_info['status']['hooks']['csp-frame-src'] = Hooks::class.'::csp_frame_src';
$setup_info['status']['hooks']['config_after_save'] = Hooks::class.'::config_after_save';
$setup_info['status']['hooks']['config_validate'] = Hooks::class.'::validate';

/* Dependencies for this app to work */
$setup_info['status']['depends'][] = array(
	'appname' => 'api',
	'versions' => array('20.1')
);