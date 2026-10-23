<?php

namespace App\Support\LicenseFile;

use InvalidArgumentException;

/**
 * Signs, encrypts and packs .atgl licence files exactly as LICENSE_FORMAT.md (copy in
 * docs/LICENSING.md). No framework code: sodium (Ed25519) and openssl (AES-256-GCM) only.
 *
 * Exceptions never include key material.
 */
final class Packer
{
    public const MAGIC = 'ATGL';

    public const FORMAT_VERSION = 1;

    /** PKCS#8 DER prefix of an Ed25519 private key; the 32-byte seed follows. */
    private const PKCS8_ED25519_PREFIX = '302e020100300506032b657004220420';

    public function __construct(
        private readonly string $keyId,
        private readonly string $signingSeed,
        private readonly string $encKey,
    ) {
        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $keyId) !== 1) {
            throw new InvalidArgumentException('LICENSE_KEY_ID must be 1-64 characters from A-Z a-z 0-9 . _ -');
        }
        if (strlen($signingSeed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            throw new InvalidArgumentException('The signing key is not an Ed25519 private key.');
        }
        if (strlen($encKey) !== 32) {
            throw new InvalidArgumentException('LICENSE_ENC_KEY must be 32 bytes (64 hex characters).');
        }
    }

    /**
     * @param  string  $privateKey  PKCS#8 PEM, or the base64 of its DER body
     * @param  string  $encKeyHex  64 hex characters
     */
    public static function fromEncoded(string $keyId, string $privateKey, string $encKeyHex): self
    {
        $encKeyHex = trim($encKeyHex);
        $enc = ctype_xdigit($encKeyHex) && strlen($encKeyHex) === 64 ? hex2bin($encKeyHex) : '';

        return new self(trim($keyId), self::seedFromPrivateKey($privateKey), (string) $enc);
    }

    public static function seedFromPrivateKey(string $privateKey): string
    {
        // Env files often store a PEM with literal "\n".
        $body = preg_replace('/-----[^-]+-----|\\\\n|\s+/', '', $privateKey);
        $der = base64_decode((string) $body, true);
        $prefix = hex2bin(self::PKCS8_ED25519_PREFIX);
        if (! is_string($der) || strlen($der) !== strlen($prefix) + 32 || ! hash_equals($prefix, substr($der, 0, strlen($prefix)))) {
            throw new InvalidArgumentException('The signing key is not an Ed25519 private key in PKCS#8 form.');
        }

        return substr($der, strlen($prefix));
    }

    /**
     * A new key set for `license:generate-keys`.
     *
     * @return array{private_pem: string, private_b64: string, public_b64: string, public_pem: string, enc_key_hex: string}
     */
    public static function generateKeys(): array
    {
        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $public = sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($seed));
        $der = hex2bin(self::PKCS8_ED25519_PREFIX).$seed;
        $publicDer = hex2bin('302a300506032b6570032100').$public;

        return [
            'private_pem' => "-----BEGIN PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PRIVATE KEY-----\n",
            'private_b64' => base64_encode($der),
            'public_b64' => base64_encode($public),
            'public_pem' => "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($publicDer), 64, "\n")."-----END PUBLIC KEY-----\n",
            'enc_key_hex' => bin2hex(random_bytes(32)),
        ];
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    /** Base64 public key, for the console keyring. */
    public function publicKey(): string
    {
        return base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($this->signingSeed)));
    }

    /**
     * @param  string  $payload  the exact payload bytes (UTF-8 JSON) that get signed
     */
    public function pack(string $payload): string
    {
        $pair = sodium_crypto_sign_seed_keypair($this->signingSeed);
        $signature = sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair));
        sodium_memzero($pair);

        $header = self::MAGIC.chr(self::FORMAT_VERSION).chr(strlen($this->keyId)).$this->keyId;
        $plain = pack('n', strlen($signature)).$signature.$payload;
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $this->encKey, OPENSSL_RAW_DATA, $iv, $tag, $header, 16);
        if (! is_string($ciphertext) || strlen($tag) !== 16) {
            throw new \RuntimeException('AES-256-GCM encryption failed.');
        }

        return $header.$iv.$tag.$ciphertext;
    }
}
