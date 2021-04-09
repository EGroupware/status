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
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use EGroupware\Api\Config;

class Jitsi implements Iface
{
	/**
	 * JWT HEADER
	 */
	protected const HEADER = ['alg' => 'HS256', 'typ' => 'JWT'];

	/**
	 * the audience (aud claim)
	 */
	protected const AUD = 'EGroupware';

	/**
	 * uid number
	 */
	protected const UID = 1;

	/**
	 * Expiration grace-time of token
	 */
	protected const EXP_GRACETIME = 3600;

	/**
	 * Not before grace-time the token
	 */
	protected const NBF_GRACETIME = 3600;

	/**
	 * @var object \Lcobucci\JWT\Token
	 */
	private $token;

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
	 * @param string $_room room-id
	 * @param array $_context values for keys 'name', 'email', 'avatar', 'account_id'
	 * @param int|null $_start start UTC timestamp, default now (gracetime of self::NBF_GRACETIME=1h is applied)
	 * @param int|null $_end expiration UTC timestamp, default now plus gracetime of self::EXP_GRACETIME=1h
	 */
	public function __construct($_room='', $_context=[], $_start=null, $_end=null)
	{
		$config = Config::read('status');
		$this->config = $config['videoconference']['jitsi'];
		$iat = time();
		$nbf = max(($_start ?: $iat) - self::NBF_GRACETIME, $iat);
		$exp = ($_end ?: $iat) + self::EXP_GRACETIME;
		$signer = new Sha256();

		$this->payload = [
			'iss' => $this->config['jitsi_application_id'] ?: 'egroupware',
			'aud' => self::AUD,
			'sub' => str_replace('jitsi.egroupware.net', '', $this->config['jitsi_domain']) ?: 'meet.jit.si',
			'room' => $_room ?: '*',
			'secret' => $this->config['jitsi_application_secret']
		];
		// Prosody in Jitsi expects all values of user context to be type of string otherwise will throw an error
		$context['user'] = array_map(static function($val){
			return (string)$val;
		}, (array)$_context['user']);
		// Jitsi doesn't like more context params, we use these params in other backends though
		unset($context['user']['cal_id'], $context['user']['title']);

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
				->issuedAt($iat)
				// Configures the time that the token can be used (nbf claim)
				->canOnlyBeUsedAfter($nbf)
				// Configures the expiration time of the token (exp claim)
				->expiresAt($exp)
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
		return $this->token->toString();
	}

	/**
	 * @param ?array $_context
	 * @return string
	 */
	public function getMeetingUrl (?array $_context=null)
	{
		$jwt = !empty($this->config['jitsi_application_id']) ? "?jwt=".$this->_getToken() : '';
		return 'https://'.$this->payload['sub'].'/'.$this->payload['room'].$jwt.'#'.$this->_getExtraParams();
	}

	/**
	 * @param false $value
	 */
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

	/**
	 * @param string $url
	 * @return mixed|string returns room id
	 */
	public static function fetchRoomFromUrl($url='')
	{
		$parts = [];
		if ($url)
		{
			$parts = explode('?jwt=', $url);
			if (is_array($parts)) $parts = explode('/', $parts[0]);
		}
		return is_array($parts) ? array_pop($parts) : "";
	}
}