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

use EGroupware\Api;
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
	 * Expiration grace-time of token
	 */
	const EXP_GRACETIME = 3600;

	/**
	 * Not before grace-time the token
	 */
	const NBF_GRACETIME = 3600;

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

	private $extraParams = [
		'config.startAudioOnly' => false
	];

	/**
	 * Constructor
	 *
	 * @param string $room room-id
	 * @param array $context values for keys 'name', 'email', 'avatar', 'account_id'
	 * @param int $start start timestamp, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int $end expiration timestamp, default now plus gracetime of self::EXP_GRACETIME=1h
	 */
	public function __construct($_room, $_context, $_start=nul, $_end=null)
	{
		$config = Config::read('status');
		$this->config = $config['videoconference']['jitsi'];
		$this->iat = time();
		$nbf = max(($_start ?: $this->iat) - self::NBF_GRACETIME, $this->iat);
		$this->exp = ($_end ?: $this->iat) + self::EXP_GRACETIME;
		$signer = new Sha256();

		$this->payload = [
			'iss' => $this->config['jitsi_application_id'] ?: 'egroupware',
			'aud' => self::AUD,
			'sub' => str_replace('jitsi.egroupware.net', '', $this->config['jitsi_domain']) ?: 'meet.jit.si',
			'room' => $_room ? $_room : '*',
			'secret' => $this->config['jitsi_application_secret']
		];
		// Prosody in Jitsi expects all values of user context to be type of string otherwise will throw an error
		$context['user'] = array_map(function($val){return (string)$val;}, $_context['user']);
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
				->withClaim('context', $context)
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
		return 'https://'.$this->payload['sub'].'/'.$this->payload['room']."?jwt=".$this->_getToken().'#'.$this->_getExtraParams();
	}

	public function setStartAudioOnly ($value = false)
	{
		$this->extraParams['config.startAudioOnly'] = $value;
	}

	private function _getExtraParams()
	{
		return http_build_query($this->extraParams,'#');
	}

	/**
	 * @return bool
	 */
	public function isMeetingValid()
	{
		return $this->_isTokenExpired();
	}

	/**
	 * Give a regex to recognize "our" urls
	 */
	public function getRegex()
	{
		return 'https://'.$this->payload['sub'].'/'.str_replace('/' , '', Api\Header\Http::host().'.*');
	}
}
