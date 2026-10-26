<?php

namespace Tests\Unit;

use App\Services\AuditService;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    public function test_sensitive_keys_are_redacted_recursively(): void
    {
        $out = (new AuditService)->sanitize([
            'name' => 'x',
            'secret' => 'plain',
            'password' => 'plain',
            'nested' => ['api_key' => 'plain', 'ok' => 1],
            'empty_secret' => null,
        ]);

        $this->assertSame('x', $out['name']);
        $this->assertSame(AuditService::REDACTED, $out['secret']);
        $this->assertSame(AuditService::REDACTED, $out['password']);
        $this->assertSame(AuditService::REDACTED, $out['nested']['api_key']);
        $this->assertSame(1, $out['nested']['ok']);
        $this->assertNull($out['empty_secret']);
    }

    public function test_diff_only_returns_changed_keys(): void
    {
        [$old, $new] = (new AuditService)->diff(['a' => 1, 'b' => 2], ['a' => 1, 'b' => 3]);

        $this->assertSame(['b' => 2], $old);
        $this->assertSame(['b' => 3], $new);
    }
}
