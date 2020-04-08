<?php
/**
 * JWT Token for jitsi backend
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn-At-egroupware.org>
 * @package Status
 * @copyright (c) 2020 by Hadi Nategh <hn-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Status\Videoconference\Backends;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use EGroupware\Api\Config;

class Jitsi implements Iface
{
	/**
	 * JWT HEADER
	 */
	const HEADER = ['alg' => 'HS256', 'typ' => 'JWT'];

	/**
	 * the audience (aud claim)
	 */
	const AUD = 'EGroupware';

	/**
	 * uid number
	 */
	const UID = 1;

	/**
	 * the time token can live after being issued
	 */
	const EXP = 3600;

	/**
	 * the time that the token can be used after being issued
	 */
	const BNF = 0;

	/**
	 * @var object \Lcobucci\JWT\Token
	 */
	private $token;

	/**
	 * the time token is issued
	 * @var int
	 */
	private $iat;

	/**
	 * the token expiration time
	 * @var int
	 */
	private $exp;

	/**
	 * contains jwt payload
	 * @var array
	 */
	private $payload;

	/**
	 * @var mixed
	 */
	private $config;

	/**
	 * token constructor.
	 * @param $_room string room id
	 * @param $_context array of users data
	 *
	 * @return object|bool token object| false
	 */
	public function __construct($_room, $_context)
	{
		$config = Config::read('status');
		$this->config = $config['videoconference']['jitsi'];
		$this->iat = time();
		$nbf = $this->iat + self::BNF;
		$this->exp = $this->iat + self::EXP;
		$signer = new Sha256();

		$this->payload = [
			'iss' => $this->config['jitsi_application_id'] ?: 'egroupware',
			'aud' => self::AUD,
			'sub' => $this->config['jitsi_domain'] ?: 'jitsi.egroupware.net',
			'room' => $_room ? $_room : '*',
			'secret' => $this->config['jitsi_application_secret']
		];
		try {
			$this->token = (new Builder())
				// Configures the issuer (iss claim)
				->issuedBy($this->payload['iss'])
				// Configure headers
				->withHeader('alg', self::HEADER['alg'])
				->withHeader('typ', self::HEADER['typ'])
				// Configure the audience (aud claim)
				->permittedFor($this->payload['aud'])
				// Configure the domain (sub claim)
				->relatedTo($this->payload['sub'])
				// Configures the time that the token was issue (iat claim)
				->issuedAt($this->iat)
				// Configures the time that the token can be used (nbf claim)
				->canOnlyBeUsedAfter($nbf)
				// Configures the expiration time of the token (exp claim)
				->expiresAt($this->exp)
				// Configure room
				->withClaim('room', $this->payload['room'])
				// Set context
				->withClaim('context', $_context)
				// Get token
				->getToken($signer, new Key ($this->payload['secret']));
		}
		catch (\Exception $e)
		{
			error_log(__METHOD__."() failed to generate token:".$e->getMessage());
		}
	}

	/**
	 * @return bool returns false if generated token is already expired otherwise false
	 */
	private function _isTokenExpired ()
	{
		return $this->token->isExpired();
	}

	/**
	 * Get Jitsi Meet JWT Token
	 * @return string \Lcobucci\JWT\Token
	 */
	private function _getToken ()
	{
		return $this->token->__toString();
	}

	public function getMeetingUrl ()
	{
		return 'https://'.$this->payload['sub'].'/'.$this->payload['room']."?jwt=".$this->_getToken();
	}

	/**
	 * @return bool
	 */
	public function isMeetingValid()
	{
		return $this->_isTokenExpired();
	}
}