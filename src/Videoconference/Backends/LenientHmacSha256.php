<?php
/**
 * HS256 signer tolerating legacy, too short jitsi_application_secret
 *
 * @link http://www.egroupware.org
 * @package Status
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Status\Videoconference\Backends;

use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;

/**
 * lcobucci/jwt 5.x enforces the RFC 2104 recommended minimum HMAC key-length of 256 bits,
 * throwing instead of signing tokens with a shorter jitsi_application_secret that worked fine
 * with lcobucci/jwt 3.x (and still works with Jitsi/Prosody, which just uses the raw secret as
 * HMAC key without any length requirement).
 *
 * New or changed secrets are required to be >=32 bytes by Hooks::validate(), so this signer is
 * only ever used to keep already configured, shorter legacy secrets working.
 */
class LenientHmacSha256 implements Signer
{
	public function algorithmId(): string
	{
		return 'HS256';
	}

	public function sign(string $payload, Key $key): string
	{
		return hash_hmac('sha256', $payload, $key->contents(), true);
	}

	public function verify(string $expected, string $payload, Key $key): bool
	{
		return hash_equals($expected, $this->sign($payload, $key));
	}
}
