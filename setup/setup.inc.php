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


$setup_info['status']['name']    = 'status';
$setup_info['status']['title']   = 'Status';
$setup_info['status']['version'] = '19.1';
$setup_info['status']['enable']  = 1;
$setup_info['status']['autoinstall'] = true;	// install automatically on update
$setup_info['status']['author'] = 'Hadi Nategh';
$setup_info['status']['index'] = array('menuaction' => 'status.EGroupware\\Status\\Ui.index&ajax=true');
$setup_info['status']['maintainer'] = array(
	'name'  => 'EGroupware GmbH',
	'url'   => 'http://www.egroupware.org',
);
$setup_info['status']['license']  = 'GPL';
$setup_info['status']['description'] = '';

/* The hooks this app includes, needed for hooks registration */
$setup_info['status']['hooks']['framework_header'] = 'EGroupware\Status\Hooks::framework_header';
$setup_info['status']['hooks']['status-get_rows'] = 'EGroupware\Status\Hooks::get_rows';
/* Dependencies for this app to work */
$setup_info['status']['depends'][] = array(
	'appname' => 'api',
	'versions' => array('17.1')
);