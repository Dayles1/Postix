<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\MessageGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPhone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class TelegramSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        DB::disableQueryLog();

        $userRole  = Role::firstOrCreate(['name' => 'user']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $departmentsCount = 1;
        $usersCount       = 40;
        $phonesCount      = 1;
        $groupsCount      = 7;
        $peersCount       = 80;
        $messagesPerPeer  = 200;

        $peerList = [];
        for ($x = 1; $x <= $peersCount; $x++) {
            $peerList[] = 'Peer ' . str_pad($x, 3, '0', STR_PAD_LEFT);
        }

        for ($d = 1; $d <= $departmentsCount; $d++) {

            $departmentId = DB::table('departments')->insertGetId([
                'name' => $faker->company,
                'plan' => $faker->randomElement(['trial', 'pro']),
                'type' => 'department',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            for ($i = 0; $i < $usersCount; $i++) {

                $userId = DB::table('users')->insertGetId([
                    'name' => $faker->name,
                    'email' => $faker->unique()->safeEmail,
                    'password' => Hash::make('password'),
                    'department_id' => $departmentId,
                    'role_id' => $faker->boolean(90) ? $userRole->id : $adminRole->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                for ($p = 0; $p < $phonesCount; $p++) {

                    $phoneId = DB::table('user_phones')->insertGetId([
                        'user_id' => $userId,
                        'phone' => '+' . $faker->numberBetween(998900000000, 998999999999),
                        'is_active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    for ($g = 0; $g < $groupsCount; $g++) {

                        $groupId = DB::table('message_groups')->insertGetId([
                            'user_phone_id' => $phoneId,
                            'status' => $faker->randomElement(['failed', 'pending', 'completed']),
                            'message_text' => $faker->sentence(20),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $batch = [];

                        foreach ($peerList as $peer) {
                            for ($m = 0; $m < $messagesPerPeer; $m++) {

                                $status = $faker->randomElement(['pending', 'sent', 'failed']);

                                $batch[] = [
                                    'message_group_id' => $groupId,
                                    'telegram_message_id' => $faker->numberBetween(100000, 999999),
                                    'peer' => $peer,
                                    'send_at' => now()->subMinutes(rand(0, 50000)),
                                    'sent_at' => now()->subMinutes(rand(0, 50000)),
                                    'status' => $status,
                                    'error_key' => $status === 'failed'
                                        ? $faker->randomElement([
                                            'flood_wait_120',
                                            'slowmode_wait_300',
                                            'peer_not_found',
                                            'chat_guest_send_forbidden',
                                            'chat_write_forbidden',
                                        ])
                                        : null,
                                    'attempts' => rand(1, 5),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];

                                // 🔥 batch insert every 500 rows
                                if (count($batch) >= 500) {
                                    DB::table('telegram_messages')->insert($batch);
                                    $batch = [];
                                }
                            }
                        }

                        if (!empty($batch)) {
                            DB::table('telegram_messages')->insert($batch);
                        }

                        unset($batch);
                        gc_collect_cycles();
                    }
                }
            }
        }

        DB::enableQueryLog();
    }
}
