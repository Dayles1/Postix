<?php

namespace App\Console\Commands\Ban;

use App\Jobs\Delete\TelegramLogoutJob;
use App\Models\Ban;
use App\Models\Department;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncBansCommand extends Command
{
    protected $signature = 'bans:sync';
    protected $description = 'Sync ban states (activate / deactivate / cleanup)';

    public function handle()
    {
        Log::info('wwork');
        Ban::query()
            ->where('active', false)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->update([
                'active' => true
            ]);



        $departments = Department::where('plan', 'trial')
            ->whereHas('ban', function ($q) {
                $q->where('active', true);
            })
            ->with(['users.phones'])
            ->get();

        foreach ($departments as $department) {
            $this->handleDepartmentBan($department);
        }
        $this->info('Bans synced successfully');
    }

    private function handleDepartmentBan(Department $department)
    {
        foreach ($department->users as $user) {

            $user->email = "deleted_{$user->id}_" . substr($user->email, 0, 150);
            $user->save();

            foreach ($user->phones as $phone) {
                if ($phone->is_active) {
                    TelegramLogoutJob::dispatch($phone->id)->afterCommit();
                    Log::info("Dispatch TelegramLogoutJob for Phone ID: {$phone->id}");
                }
            }

            $user->delete();
        }
    }
}
