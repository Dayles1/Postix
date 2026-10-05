<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Actions\ResolveOperationUser;
use App\Application\Telegram\Queries\ListOperators;
use App\Models\Telegram\OperationUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperatorRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('telegram_client_checks');
        Schema::dropIfExists('telegram_driver_checks');
        Schema::dropIfExists('telegram_drivers');
        Schema::dropIfExists('operation_users');

        Schema::create('operation_users', function (Blueprint $table): void {
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
        });

        Schema::create('telegram_drivers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_driver_checks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_client_checks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_a_resolved_person_is_an_operator_by_default(): void
    {
        $operator = app(ResolveOperationUser::class)->execute('KARIMOV AZIZ');

        $this->assertSame(OperationUser::ROLE_OPERATION, $operator->refresh()->role);
    }

    public function test_the_role_is_set_on_create_and_never_changed_by_a_match(): void
    {
        $resolve = app(ResolveOperationUser::class);

        $sales = $resolve->execute('BELYAKOVA ANNA', OperationUser::ROLE_SALES);
        $this->assertSame(OperationUser::ROLE_SALES, $sales->refresh()->role);

        $operator = $resolve->execute('KARIMOV AZIZ');
        $again = $resolve->execute('karimov aziz', OperationUser::ROLE_SALES);

        $this->assertSame($operator->id, $again->id);
        $this->assertSame(OperationUser::ROLE_OPERATION, $again->refresh()->role);
    }

    public function test_operators_list_filters_by_role(): void
    {
        $resolve = app(ResolveOperationUser::class);

        $resolve->execute('KARIMOV AZIZ');
        $resolve->execute('BELYAKOVA ANNA', OperationUser::ROLE_SALES);

        $query = app(ListOperators::class);

        $this->assertSame(
            ['BELYAKOVA ANNA'],
            collect($query->execute(['role' => 'sales'])->items())->pluck('name')->all(),
        );

        $this->assertSame(1, $query->stats(['role' => 'operation'])['total']);

        /*
         * The two roles live on two pages: no role is the operators page,
         * never a mixed list. Both are still counted for the tabs.
         */
        $this->assertSame(1, $query->stats([])['total']);
        $this->assertSame(['operation' => 1, 'sales' => 1], $query->stats([])['roles']);
    }
}
