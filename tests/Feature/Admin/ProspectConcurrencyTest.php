<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Prospect;
use App\Services\CreateProspect;
use App\Services\ManageProspectContact;
use App\Services\UpdateProspect;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProspectConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private function parallel(array $operations): array
    {
        $this->assertTrue(function_exists('pcntl_fork'), 'pcntl is required for concurrency regression');
        $directory = sys_get_temp_dir().'/rotta-concurrency-'.Str::uuid();
        mkdir($directory, 0700);
        DB::disconnect();
        $pids = [];
        try {
            foreach ($operations as $index => $operation) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new \RuntimeException('Could not fork test process');
                }
                if ($pid === 0) {
                    pcntl_async_signals(true);
                    pcntl_signal(SIGALRM, fn () => exit(2));
                    pcntl_alarm(15);
                    DB::purge();
                    try {
                        file_put_contents($directory.'/ready-'.$index, 'ready');
                        while (! is_file($directory.'/go')) {
                            usleep(1000);
                        }
                        $result = ['ok' => true, 'id' => $operation()];
                    } catch (ValidationException $exception) {
                        $result = ['ok' => false, 'errors' => array_keys($exception->errors())];
                    } catch (\Throwable $exception) {
                        $result = ['ok' => false, 'exception' => get_class($exception)];
                    }
                    file_put_contents($directory.'/result-'.$index, json_encode($result, JSON_THROW_ON_ERROR));
                    DB::disconnect();
                    exit(0);
                }
                $pids[] = $pid;
            }
            $deadline = microtime(true) + 10;
            while (count(glob($directory.'/ready-*')) !== count($operations) && microtime(true) < $deadline) {
                usleep(1000);
            }
            file_put_contents($directory.'/go', 'go');
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertSame(0, pcntl_wexitstatus($status));
            }
            $results = [];
            foreach (array_keys($operations) as $index) {
                $results[] = json_decode(file_get_contents($directory.'/result-'.$index), true, 512, JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
            DB::purge();
        }
    }

    public function test_creation_and_identifier_update_cannot_race_around_block(): void
    {
        $admin = Admin::factory()->create();
        $input = ['business_name' => 'QA concorrência antiga', 'city' => 'Vassouras', 'state' => 'RJ', 'email' => 'old@example.invalid', 'priority' => 'medium', 'submission_token' => (string) Str::uuid()];
        $prospect = app(CreateProspect::class)->handle($input, $admin);
        app(ManageProspectContact::class)->handle($prospect, $admin, ['blocked' => 1, 'lock_version' => 1, 'reason' => 'Bloqueio de contato antes da concorrência.']);
        $results = $this->parallel([
            fn () => app(UpdateProspect::class)->handle(Prospect::findOrFail($prospect->id), [...$input, 'business_name' => 'QA concorrência nova', 'email' => 'new@example.invalid', 'lock_version' => 2], Admin::findOrFail($admin->id))->id,
            fn () => app(CreateProspect::class)->handle([...$input, 'submission_token' => (string) Str::uuid()], Admin::findOrFail($admin->id))->id,
        ]);
        $this->assertTrue($results[0]['ok']);
        $this->assertFalse($results[1]['ok']);
        $this->assertContains('blocked_match_acknowledged', $results[1]['errors']);
        $this->assertDatabaseCount('prospects', 1);
        $this->assertNotNull($prospect->fresh()->contact_blocked_at);
        $this->assertSame(4, $prospect->identifiers()->count());
    }

    public function test_simultaneous_retries_create_one_prospect_and_one_history_event(): void
    {
        $admin = Admin::factory()->create();
        $input = ['business_name' => 'QA concorrência idempotente', 'city' => 'Vassouras', 'state' => 'RJ', 'priority' => 'medium', 'submission_token' => (string) Str::uuid()];
        $operation = fn () => app(CreateProspect::class)->handle($input, Admin::findOrFail($admin->id))->id;
        $results = $this->parallel([$operation, $operation]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('prospects', 1);
        $this->assertDatabaseCount('prospect_sources', 1);
        $this->assertDatabaseCount('prospect_activities', 1);
        $this->assertDatabaseCount('prospect_identifiers', 1);
    }
}
