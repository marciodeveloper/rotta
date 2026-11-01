<?php

namespace Tests\Unit\Services;

use App\Services\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_generates_expected_rfc_6238_code(): void
    {
        $totp = new TotpService;

        $code = $totp->codeAt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 1);

        $this->assertSame('287082', $code);
    }

    public function test_rejects_non_numeric_code(): void
    {
        $totp = new TotpService;

        $result = $totp->verify('JBSWY3DPEHPK3PXP', '12A456', 59);

        $this->assertFalse($result);
    }
}
