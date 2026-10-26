<?php

namespace Tests\Unit;

use App\Services\SecretVault;
use Illuminate\Contracts\Encryption\DecryptException;
use PHPUnit\Framework\TestCase;

class SecretVaultTest extends TestCase
{
    public function test_round_trip_and_ciphertext_hides_plaintext(): void
    {
        $vault = new SecretVault(SecretVault::generateKey());

        $payload = $vault->encrypt('super-secret-value');

        $this->assertNotSame('super-secret-value', $payload);
        $this->assertStringNotContainsString('super-secret', $payload);
        $this->assertSame('super-secret-value', $vault->decrypt($payload));
    }

    public function test_same_plaintext_produces_different_ciphertexts(): void
    {
        $vault = new SecretVault(SecretVault::generateKey());

        $this->assertNotSame($vault->encrypt('abc'), $vault->encrypt('abc'));
    }

    public function test_null_and_empty_stay_null(): void
    {
        $vault = new SecretVault(SecretVault::generateKey());

        $this->assertNull($vault->encrypt(null));
        $this->assertNull($vault->encrypt(''));
        $this->assertNull($vault->decrypt(null));
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $vault = new SecretVault(SecretVault::generateKey());
        $payload = json_decode(base64_decode($vault->encrypt('abc')), true);
        $payload['value'] = base64_encode('tampered-'.base64_decode($payload['value']));

        $this->expectException(DecryptException::class);
        $vault->decrypt(base64_encode(json_encode($payload)));
    }

    public function test_other_key_cannot_decrypt(): void
    {
        $payload = (new SecretVault(SecretVault::generateKey()))->encrypt('abc');

        $this->expectException(DecryptException::class);
        (new SecretVault(SecretVault::generateKey()))->decrypt($payload);
    }

    public function test_key_rotation_with_previous_keys(): void
    {
        $old = SecretVault::generateKey();
        $new = SecretVault::generateKey();
        $payload = (new SecretVault($old))->encrypt('rotating');

        $rotated = new SecretVault($new, [$old]);

        $this->assertSame('rotating', $rotated->decrypt($payload));

        $reencrypted = $rotated->reencrypt($payload);
        $this->assertSame('rotating', (new SecretVault($new))->decrypt($reencrypted));
    }

    public function test_uses_gcm_cipher(): void
    {
        $payload = json_decode(base64_decode((new SecretVault(SecretVault::generateKey()))->encrypt('x')), true);

        $this->assertArrayHasKey('tag', $payload);
        $this->assertNotEmpty($payload['tag']);
    }
}
