<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Queries\ListOperators;
use App\Models\Telegram\OperationUser;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::dropIfExists('telegram_driver_checks');
        Schema::dropIfExists('telegram_drivers');
        Schema::dropIfExists('operation_users');

        Schema::create(
            'operation_users',
            function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('name_normalized')->index();
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

    public function test_status_and_dm_filters(): void
    {
        $this->assertSame(
            ['EPSILON OPERATOR'],
            $this->names(['is_active' => false]),
        );

        $this->assertSame(
            ['DELTA OPERATOR'],
            $this->names(['dm_enabled' => false]),
        );
    }

    public function test_stats_count_the_whole_filtered_set(): void
    {
        $stats = (new ListOperators())->stats([]);

        $this->assertSame(5, $stats['total']);
        $this->assertSame(4, $stats['active']);
        $this->assertSame(4, $stats['linked']);

        /*
         * Only ALPHA and BETA pass all three conditions: active, dm_enabled
         * and reachable. DELTA is muted, EPSILON is deactivated, GAMMA has no
         * contact.
         */
        $this->assertSame(2, $stats['dm_enabled']);
        $this->assertSame(1, $stats['failing']);
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
