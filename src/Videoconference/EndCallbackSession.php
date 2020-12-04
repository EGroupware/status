<?php


namespace EGroupware\Status\Videoconference;


use EGroupware\OpenID\Token;
use mysql_xdevapi\Exception;

class EndCallbackSession
{
	public static function create_session()
	{
		$jwt = $_GET['jwt'];
		$token = new Token();
		if (!$jwt || !($t=$token->validateJWT($jwt)))
		{
			throw new Exception('No valid Token found!');
			exit();
		}
		if ($t)
		{
			$context = $t->getClaim('context');
			//TODO: creating session and deleteing the cal event
		}
	}
}