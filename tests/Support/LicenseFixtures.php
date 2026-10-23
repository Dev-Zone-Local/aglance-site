<?php

namespace Tests\Support;

use RuntimeException;

/**
 * The shared TEST keys and samples (workspace contracts/fixtures, copied to
 * tests/fixtures/licensing). Also decodes .atgl files the way the console does, so tests
 * can check what atglance.live issues.
 */
final class LicenseFixtures
{
    public static function dir(): string
    {
        return dirname(__DIR__).'/fixtures/licensing';
    }

    /**
     * @return array{key_id: string, ed25519_private_pem: string, ed25519_public_b64: string, aes_key_hex: string}
     */
    public static function key(string $keyId = 'test-2026'): array
    {
        foreach (json_decode((string) file_get_contents(self::dir().'/keys.json'), true)['keys'] as $key) {
            if ($key['key_id'] === $keyId) {
                return $key;
            }
        }
        throw new RuntimeException("No test key {$keyId}");
    }

    /** Points config('atglance.license_file') at the TEST key. */
    public static function configure(): void
    {
        $key = self::key();
        config([
            'atglance.license_file.key_id' => $key['key_id'],
            'atglance.license_file.signing_key' => $key['ed25519_private_pem'],
            'atglance.license_file.enc_key' => $key['aes_key_hex'],
        ]);
    }

    /**
     * Decrypts and verifies a file with the TEST key (format checks only, no date rules).
     *
     * @return array{key_id: string, payload: array<string, mixed>, payload_bytes: string}
     */
    public static function decode(string $bytes): array
    {
        $key = self::key();
        if (substr($bytes, 0, 4) !== 'ATGL' || ord($bytes[4]) !== 1) {
            throw new RuntimeException('bad header');
        }
        $kidLength = ord($bytes[5]);
        $header = substr($bytes, 0, 6 + $kidLength);
        $iv = substr($bytes, 6 + $kidLength, 12);
        $tag = substr($bytes, 18 + $kidLength, 16);
        $plain = openssl_decrypt(substr($bytes, 34 + $kidLength), 'aes-256-gcm', hex2bin($key['aes_key_hex']), OPENSSL_RAW_DATA, $iv, $tag, $header);
        if (! is_string($plain)) {
            throw new RuntimeException('decrypt failed');
        }
        $sigLength = unpack('n', substr($plain, 0, 2))[1];
        $signature = substr($plain, 2, $sigLength);
        $payload = substr($plain, 2 + $sigLength);
        if ($sigLength !== 64 || ! sodium_crypto_sign_verify_detached($signature, $payload, base64_decode($key['ed25519_public_b64']))) {
            throw new RuntimeException('signature invalid');
        }

        return ['key_id' => substr($bytes, 6, $kidLength), 'payload' => json_decode($payload, true), 'payload_bytes' => $payload];
    }
}
