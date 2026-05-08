<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MarkPastScheduledAsSentCommand extends Command
{
    protected $signature = 'telegram:mark-past-scheduled 
        {--daemon : Run as daemon (infinite loop)} 
        {--sleep=60 : Sleep seconds between loops in daemon mode} 
        {--chunk=500 : Rows to process per chunk}';
    protected $description = 'Mark scheduled messages as sent if their send_at time has already passed';

    /**
     * Default table name (overriden by config/telegram.php)
     */
    protected $defaultTable = 'telegram_messages';

    public function handle()
    {
        $isDaemon = $this->option('daemon');
        $sleep = (int) $this->option('sleep');
        $chunkSize = (int) $this->option('chunk');

        // Use config table or default
        $table = config('telegram.table', $this->defaultTable);

        if ($isDaemon) {
            $this->info("Daemon mode started (sleep={$sleep}s, chunk={$chunkSize}). Use Ctrl+C to stop.");
            if (function_exists('pcntl_signal')) {
                pcntl_async_signals(true);
                pcntl_signal(SIGTERM, function () {
                    $this->info("Daemon stopping (SIGTERM).");
                    exit;
                });
                pcntl_signal(SIGINT, function () {
                    $this->info("Daemon stopping (SIGINT).");
                    exit;
                });
            }

            while (true) {
                $this->processOnce($table, $chunkSize);
                sleep($sleep);
            }
        }

        // one-off run
        $this->processOnce($table, $chunkSize);

        return Command::SUCCESS;
    }

    /**
     * Process one iteration: find scheduled rows with send_at <= now and mark them 'sent'
     *
     * Uses Carbon instances for time comparison/updates to match how you create rows.
     *
     * @param string $table
     * @param int $chunkSize
     * @return void
     */
    protected function processOnce(string $table, int $chunkSize = 500): void
    {
        // Use Carbon object (matches your insert code which uses Carbon objects)
        $now = Carbon::now();

        try {
            // Quick existence check
            $exists = DB::table($table)
                ->where('status', 'scheduled')
                ->where('send_at', '<=', $now)
                ->limit(1)
                ->exists();

            if (! $exists) {
                // Log::info("telegram:mark-past-scheduled — nothing to update at {$now->toDateTimeString()}");
                return;
            }

            // Process in chunks by id to avoid memory issues
            DB::table($table)
                ->where('status', 'scheduled')
                ->where('send_at', '<=', $now)
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use ($table, $now) {
                    $ids = $rows->pluck('id')->toArray();
                    if (empty($ids)) {
                        return;
                    }

                    try {
                        DB::transaction(function () use ($table, $ids, $now) {
                            $updated = DB::table($table)
                                ->whereIn('id', $ids)
                                ->where('status', 'scheduled')
                                ->update([
                                    'status' => 'sent',
                                    'sent_at' => $now,
                                    'updated_at' => $now,
                                ]);

                            $sampleIds = implode(',', array_slice($ids, 0, 10));
                            Log::info("telegram:mark-past-scheduled — updated {$updated} rows (sample ids: {$sampleIds})");
                        });
                    } catch (\Throwable $e) {
                        Log::error("telegram:mark-past-scheduled — chunk update error: " . $e->getMessage(), [
                            'exception' => $e,
                            'sample_ids' => $ids,
                        ]);
                    }
                });

        } catch (\Throwable $e) {
            Log::error("telegram:mark-past-scheduled — fatal error: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
