<?php

namespace App\Observers;

use App\Models\Department;
use App\Jobs\Delete\TelegramLogoutJob;
use Illuminate\Support\Facades\Log;

class DepartmentObserver
{
    /**
     * Handle the Department "deleted" event.
     *
     * @param  \App\Models\Department  $department
     * @return void
     */
    public function deleted(Department $department)
    {
        $department->load(['users.phones']);

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