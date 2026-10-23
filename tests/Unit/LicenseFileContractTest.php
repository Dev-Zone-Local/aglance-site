<?php

namespace Tests\Unit;

use App\Support\LicenseFile\Packer;
use App\Support\LicenseFile\PayloadBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\LicenseFixtures;

/**
 * The issuer against the shared test vectors (workspace contracts/fixtures). The console
 * verifies the same samples. Set ATGLANCE_CONTRACTS_DIR to the workspace contracts/ folder
 * to also check that the copy is up to date.
 */
class LicenseFileContractTest extends TestCase
{
    private function packer(): Packer
    {
        $key = LicenseFixtures::key();

        return Packer::fromEncoded($key['key_id'], $key['ed25519_private_pem'], $key['aes_key_hex']);
    }

    public function test_private_key_matches_the_fixture_public_key(): void
    {
        $this->assertSame(LicenseFixtures::key()['ed25519_public_b64'], $this->packer()->publicKey());
    }

    public function test_single_line_base64_private_key_is_accepted(): void
    {
        $pem = LicenseFixtures::key()['ed25519_private_pem'];
        $oneLine = preg_replace('/-----[^-]+-----|\s+/', '', $pem);

        $this->assertSame(Packer::seedFromPrivateKey($pem), Packer::seedFromPrivateKey($oneLine));
        $this->assertSame(Packer::seedFromPrivateKey($pem), Packer::seedFromPrivateKey(str_replace("\n", '\n', $pem)));
    }

    public function test_payload_builder_reproduces_the_fixture_payload(): void
    {
        $expected = json_decode((string) file_get_contents(LicenseFixtures::dir().'/valid.payload.json'), true);

        $payload = PayloadBuilder::build(
            licenseId: $expected['license_id'],
            keyId: 'test-2026',
            customer: $expected['customer'],
            email: $expected['email'],
            plan: $expected['plan'],
            seats: $expected['seats'],
            features: $expected['features'],
            validDays: 365,
            minVersion: $expected['min_version'],
            installId: null,
            now: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertSame($expected, $payload);
    }

    public function test_packed_file_has_the_contract_layout(): void
    {
        $expected = json_decode((string) file_get_contents(LicenseFixtures::dir().'/valid.payload.json'), true);
        $bytes = $this->packer()->pack(PayloadBuilder::encode($expected));
        $sample = (string) file_get_contents(LicenseFixtures::dir().'/valid.atgl');

        // Same header and the same total size as the sample; IV and ciphertext differ.
        $this->assertSame(substr($sample, 0, 15), substr($bytes, 0, 15));
        $this->assertSame(strlen($sample), strlen($bytes));
        $this->assertNotSame($sample, $bytes);

        $decoded = LicenseFixtures::decode($bytes);
        $this->assertSame('test-2026', $decoded['key_id']);
        $this->assertSame($expected, $decoded['payload']);
    }

    public function test_fixture_sample_decodes_with_the_test_keys(): void
    {
        $sample = (string) file_get_contents(LicenseFixtures::dir().'/valid.atgl');
        $expected = json_decode((string) file_get_contents(LicenseFixtures::dir().'/valid.payload.json'), true);

        $this->assertSame($expected, LicenseFixtures::decode($sample)['payload']);
    }

    public function test_fixture_copy_matches_the_workspace_contract(): void
    {
        $contracts = getenv('ATGLANCE_CONTRACTS_DIR');
        if (! $contracts) {
            $this->markTestSkipped('ATGLANCE_CONTRACTS_DIR is not set; using the copy in tests/fixtures/licensing.');
        }

        $source = rtrim($contracts, '/\\').'/fixtures';
        $this->assertDirectoryExists($source);
        $names = fn (string $d) => array_map('basename', glob($d.'/*') ?: []);
        $this->assertSame($names($source), $names(LicenseFixtures::dir()), 'Run contracts/tools/sync-fixtures.php');
        foreach ($names($source) as $name) {
            $this->assertSame(hash_file('sha256', "{$source}/{$name}"), hash_file('sha256', LicenseFixtures::dir()."/{$name}"), "{$name} differs: run contracts/tools/sync-fixtures.php");
        }
    }
}
