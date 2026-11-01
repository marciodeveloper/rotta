<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminRecoveryCode;
use Illuminate\Support\Facades\DB;

class AdminRecoveryCodeService
{
    public function __construct(private SensitiveHash $hash) {}

    /** @return list<string> */
    public function generate(Admin $admin, int $count = 8): array
    {
        $codes = [];
        for ($index = 0; $index < $count; $index++) {
            $plain = strtoupper(implode('-', str_split(bin2hex(random_bytes(6)), 4)));
            $admin->recoveryCodes()->create(['code_hash' => $this->hash->makeFor('admin-recovery-code', $this->normalize($plain))]);
            $codes[] = $plain;
        }

        return $codes;
    }

    public function consume(Admin $admin, string $plain): bool
    {
        $hash = $this->hash->makeFor('admin-recovery-code', $this->normalize($plain));

        return DB::transaction(function () use ($admin, $hash): bool {
            $code = AdminRecoveryCode::query()->whereBelongsTo($admin)->where('code_hash', $hash)->whereNull('used_at')->lockForUpdate()->first();
            if ($code === null) {
                return false;
            }
            $code->update(['used_at' => now()]);

            return true;
        }, 3);
    }

    private function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-F0-9]/i', '', $code) ?? '');
    }
}
