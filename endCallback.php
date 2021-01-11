<?php
namespace EGroupware\Status\Videoconference;
// switch evtl. set output-compression off, as we cant calculate a Content-Length header with transparent compression

ini_set('zlib.output_compression', 0);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => true,
		'noheader'  => true,
		'nonavbar' => 'always',
		'currentapp' => 'status',
		'autocreate_session_callback' => 'EGroupware\\Status\\Videoconference\\EndCallbackSession::create_session'
	));

	require('../header.inc.php');







