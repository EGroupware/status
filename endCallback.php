<?php
namespace EGroupware\Status;
// switch evtl. set output-compression off, as we cant calculate a Content-Length header with transparent compression

use EGroupware\OpenID\Token;

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
	$jwt = $_GET['jwt'];
	$token = new Token();
	if (($t=$token->validateJWT($jwt))) {
		$context = $t->getClaim('context');
		$cal = new \calendar_boupdate();
		$cal->delete($context->cal_id);
	}







