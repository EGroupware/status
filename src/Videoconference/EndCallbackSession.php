<?php


namespace EGroupware\Status\Videoconference;


use EGroupware\OpenID\Token;

class EndCallbackSession
{
	public static function create_session()
	{
		$jwt = $_GET['jwt'];
		$token = new Token();
		if (!$jwt || !($t=$token->validateJWT($jwt)))
		{
			return false;
		}
		if ($t)
		{
			$context = $t->getClaim('context');
			return $GLOBALS['egw']->session->create($context->account_lid, '', 'text', true, false);
		}
		return false;
	}
}
