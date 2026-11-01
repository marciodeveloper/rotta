<?php

namespace Tests\Feature;

use App\Models\PrivacyNotice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PrivacyNoticeIntegrityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_one_current_notice_can_exist(): void
    {
        PrivacyNotice::factory()->published()->create();

        $this->expectException(QueryException::class);
        PrivacyNotice::factory()->published()->create();
    }

    public function test_draft_notice_cannot_be_current(): void
    {
        $this->expectException(QueryException::class);
        PrivacyNotice::factory()->create(['is_current' => true]);
    }

    public function test_published_notice_requires_legal_fields(): void
    {
        $this->expectException(QueryException::class);
        PrivacyNotice::factory()->create([
            'status' => 'published',
            'published_at' => now(),
            'legal_basis' => null,
            'retention_days' => null,
        ]);
    }
}
