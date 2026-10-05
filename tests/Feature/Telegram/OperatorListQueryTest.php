<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Queries\ListOperators;
use App\Models\Telegram\OperationUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperatorListQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The project migrations are MySQL-only (duplicate index names across
         * tables), so only the tables this query touches are created here.
         */
        Schema::dropIfExists('telegram_client_checks');
        Schema::dropIfExists('telegram_driver_checks');
        Schema::dropIfExists('telegram_drivers');
        Schema::dropIfExists('operation_users');

        Schema::create(
            'operation_users',
            function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('name_normalized')->index();
                $table->string('role', 16)->default('operation');
                $table->string('telegram_username')->nullable();
                $table->unsignedBigInteger('telegram_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('dm_enabled')->default(true);
                $table->timestamp('dm_last_sent_at')->nullable();
                $table->text('dm_last_error')->nullable();
                $table->timestamps();
            }
        );

        Schema::create(
            'telegram_drivers',
            function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('name_normalized')->index();
                $table->unsignedBigInteger('operation_user_id')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
            }
        );

        Schema::create(
            'telegram_driver_checks',
            function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('operation_user_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            }
        );

        Schema::create(
            'telegram_client_checks',
            function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('operation_user_id')->nullable();
                $table->string('status', 16)->default('pending');
                $table->timestamps();
            }
        );

        $this->seedOperators();
    }

    private function seedOperators(): void
    {
        $rows = [
            // Reachable and switched on.
            [
                'name' => 'ALPHA OPERATOR',
                'telegram_username' => 'alpha',
                'telegram_id' => null,
                'is_active' => true,
                'dm_enabled' => true,
                'dm_last_error' => null,
            ],
            // Reachable by id only.
            [
                'name' => 'BETA OPERATOR',
                'telegram_username' => null,
                'telegram_id' => 555000111,
                'is_active' => true,
                'dm_enabled' => true,
                'dm_last_error' => null,
            ],
            // Auto-created, contact never filled in.
            [
                'name' => 'GAMMA OPERATOR',
                'telegram_username' => null,
                'telegram_id' => null,
                'is_active' => true,
                'dm_enabled' => true,
                'dm_last_error' => null,
            ],
            // Reachable but muted.
            [
                'name' => 'DELTA OPERATOR',
                'telegram_username' => 'delta',
                'telegram_id' => null,
                'is_active' => true,
                'dm_enabled' => false,
                'dm_last_error' => null,
            ],
            // Reachable, switched on, but deactivated - and failing.
            [
                'name' => 'EPSILON OPERATOR',
                'telegram_username' => 'epsilon',
                'telegram_id' => null,
                'is_active' => false,
                'dm_enabled' => true,
                'dm_last_error' => 'USERNAME_NOT_OCCUPIED',
            ],
        ];

        foreach ($rows as $row) {
            OperationUser::query()->create([
                ...$row,
                'name_normalized' => OperationUser::normalizeName($row['name']),
            ]);
        }
    }

    private function names(array $filters): array
    {
        return (new ListOperators())
            ->execute($filters)
            ->pluck('name')
            ->all();
    }

    public function test_it_lists_every_operator_by_name(): void
    {
        $this->assertSame(
            [
                'ALPHA OPERATOR',
                'BETA OPERATOR',
                'DELTA OPERATOR',
                'EPSILON OPERATOR',
                'GAMMA OPERATOR',
            ],
            $this->names([]),
        );
    }

    public function test_search_matches_name_username_and_id(): void
    {
        $this->assertSame(
            ['ALPHA OPERATOR'],
            $this->names(['search' => 'alpha']),
        );

        $this->assertSame(
            ['BETA OPERATOR'],
            $this->names(['search' => '555000']),
        );
    }

    public function test_linked_filter_separates_reachable_operators(): void
    {
        /*
         * "Linked" is the question the page exists to answer: an operator is
         * reachable through either a username or an id.
         */
        $this->assertSame(
            [
                'ALPHA OPERATOR',
                'BETA OPERATOR',
                'DELTA OPERATOR',
                'EPSILON OPERATOR',
            ],
            $this->names(['linked' => true]),
        );

        $this->assertSame(
            ['GAMMA OPERATOR'],
            $this->names(['linked' => false]),
        );
    }

    public function test_dm_filter(): void
    {
        $this->assertSame(
            ['DELTA OPERATOR'],
            $this->names(['dm_enabled' => false]),
        );

        /*
         * The active/inactive switch is gone (dm_enabled is the one switch),
         * so the old filter is ignored rather than narrowing the list.
         */
        $this->assertCount(5, $this->names(['is_active' => false]));
    }

    public function test_stats_count_the_whole_filtered_set(): void
    {
        $stats = (new ListOperators())->stats([]);

        $this->assertSame(5, $stats['total']);
        $this->assertArrayNotHasKey('active', $stats);
        $this->assertSame(4, $stats['linked']);

        /*
         * dm_enabled and reachable: ALPHA, BETA and EPSILON. DELTA is muted,
         * GAMMA has no contact. EPSILON's leftover is_active = false no
         * longer counts.
         */
        $this->assertSame(3, $stats['dm_enabled']);
        $this->assertSame(1, $stats['failing']);
    }

    /*
    |--------------------------------------------------------------------------
    | Operation | Sales
    |--------------------------------------------------------------------------
    */

    private function sales(string $name, array $attributes = []): OperationUser
    {
        return OperationUser::query()->create([
            'name' => $name,
            'name_normalized' => OperationUser::normalizeName($name),
            'role' => OperationUser::ROLE_SALES,
            ...$attributes,
        ]);
    }

    public function test_the_two_roles_are_never_listed_together(): void
    {
        $this->sales('ZETA SALES', ['telegram_username' => 'zeta']);

        /* No role means operators - the list is never mixed. */
        $this->assertNotContains('ZETA SALES', $this->names([]));
        $this->assertCount(5, $this->names(['role' => OperationUser::ROLE_OPERATION]));

        $this->assertSame(['ZETA SALES'], $this->names(['role' => OperationUser::ROLE_SALES]));

        /* An unknown role falls back to operators instead of listing everyone. */
        $this->assertCount(5, $this->names(['role' => 'boss']));
    }

    public function test_stats_follow_the_role_and_the_tabs_count_both(): void
    {
        $this->sales('ZETA SALES', ['telegram_username' => 'zeta']);
        $this->sales('ETA SALES');

        $sales = (new ListOperators())->stats(['role' => OperationUser::ROLE_SALES]);

        $this->assertSame(2, $sales['total']);
        $this->assertSame(1, $sales['linked']);
        $this->assertSame(
            [OperationUser::ROLE_OPERATION => 5, OperationUser::ROLE_SALES => 2],
            $sales['roles'],
        );

        /* The tab counters ignore the other filters too. */
        $filtered = (new ListOperators())->stats(['search' => 'alpha']);

        $this->assertSame(1, $filtered['total']);
        $this->assertSame(5, $filtered['roles'][OperationUser::ROLE_OPERATION]);
    }

    public function test_penalties_are_counted_and_sortable(): void
    {
        $zeta = $this->sales('ZETA SALES');
        $eta = $this->sales('ETA SALES');

        foreach ([$zeta, $zeta, $eta] as $person) {
            DB::table('telegram_client_checks')->insert([
                'operation_user_id' => $person->id,
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rows = (new ListOperators())->execute([
            'role' => OperationUser::ROLE_SALES,
            'sort' => 'penalties',
            'direction' => 'desc',
        ]);

        $this->assertSame(['ZETA SALES', 'ETA SALES'], $rows->pluck('name')->all());
        $this->assertSame([2, 1], $rows->pluck('penalties_count')->map(fn ($n) => (int) $n)->all());
        $this->assertNotNull($rows->first()->last_penalty_at);
    }

    public function test_per_page_is_honoured(): void
    {
        $paginator = (new ListOperators())->execute([
            'per_page' => 2,
        ]);

        $this->assertSame(2, $paginator->perPage());
        $this->assertSame(5, $paginator->total());
        $this->assertCount(2, $paginator->items());
    }
}
