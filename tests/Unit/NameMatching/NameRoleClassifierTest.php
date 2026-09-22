<?php

declare(strict_types=1);

namespace Tests\Unit\NameMatching;

use App\Application\Telegram\Services\NameMatching\NameNormalizer;
use App\Application\Telegram\Services\NameMatching\NameTokenizer;
use App\Application\Telegram\Services\NameMatching\Roles\NameRole;
use App\Application\Telegram\Services\NameMatching\Roles\NameRoleClassifier;
use App\Application\Telegram\Services\NameMatching\Token;
use PHPUnit\Framework\TestCase;

final class NameRoleClassifierTest extends TestCase
{
    private NameRoleClassifier $classifier;

    private NameTokenizer $tokenizer;

    private NameNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new NameRoleClassifier;
        $this->tokenizer = new NameTokenizer;
        $this->normalizer = new NameNormalizer;
    }

    /**
     * @return array<string, string> token display => role
     */
    private function roles(string $name): array
    {
        $tokens = $this->classifier->classify(
            $this->tokenizer->tokenize($name),
            $this->normalizer->normalize($name),
        );

        $roles = [];

        foreach ($tokens as $token) {
            $roles[$token->displayUpper()] = $token->role->value;
        }

        return $roles;
    }

    public function test_document_order_surname_given_patronymic(): void
    {
        $this->assertSame(
            [
                'SAYDAKHMEDOV' => NameRole::Surname->value,
                'ADILKHAN' => NameRole::GivenName->value,
                'JALALKHANOVICH' => NameRole::Patronymic->value,
            ],
            $this->roles('SAYDAKHMEDOV ADILKHAN JALALKHANOVICH'),
        );
    }

    public function test_lineage_marker_makes_the_last_name_the_fathers_name(): void
    {
        $this->assertSame(
            [
                'AHMADIYOV' => NameRole::Surname->value,
                'ANVAR' => NameRole::GivenName->value,
                'ALI' => NameRole::Patronymic->value,
            ],
            $this->roles('АҲМАДИЁВ АНВАР АЛИ ЎҒЛИ'),
        );
    }

    public function test_a_lineage_marker_never_costs_us_the_only_given_name(): void
    {
        // "TOJIMATOV ABDULKODIR UGLI" is missing the father's name that
        // UGLI belongs to. Demoting ABDULKODIR would leave the driver
        // with no given name at all, so the marker is ignored here.
        $this->assertSame(
            [
                'TOJIMATOV' => NameRole::Surname->value,
                'ABDULKODIR' => NameRole::GivenName->value,
            ],
            $this->roles('TOJIMATOV ABDULKODIR UGLI'),
        );
    }

    public function test_two_tokens_are_surname_and_given_name(): void
    {
        $this->assertSame(
            [
                'ALIYEV' => NameRole::Surname->value,
                'AKMAL' => NameRole::GivenName->value,
            ],
            $this->roles('ALIYEV AKMAL'),
        );
    }

    public function test_a_name_followed_only_by_a_patronymic_is_the_given_name(): void
    {
        $this->assertSame(
            [
                'KURBONALI' => NameRole::GivenName->value,
                'ANVAROVICH' => NameRole::Patronymic->value,
            ],
            $this->roles('KURBONALI ANVAROVICH'),
        );
    }

    public function test_a_single_token_name_is_the_given_name(): void
    {
        $this->assertSame(
            ['BEKZOD' => NameRole::GivenName->value],
            $this->roles('BEKZOD'),
        );
    }

    public function test_a_fourth_token_stays_unknown_rather_than_being_guessed(): void
    {
        $roles = $this->roles('MATYAKUBOV BEKHZOD ALLABERGAN SOBIROVICH');

        $this->assertSame(NameRole::Surname->value, $roles['MATYAKUBOV']);
        $this->assertSame(NameRole::GivenName->value, $roles['BEKHZOD']);
        $this->assertSame(NameRole::Unknown->value, $roles['ALLABERGAN']);
        $this->assertSame(NameRole::Patronymic->value, $roles['SOBIROVICH']);
    }

    public function test_classifying_an_empty_token_list_is_a_no_op(): void
    {
        $this->assertSame([], $this->classifier->classify([], ''));
    }

    public function test_roles_do_not_disturb_the_comparison_forms(): void
    {
        $token = new Token('elyorbek');

        $this->assertSame(
            [$token->canonical, $token->core, $token->phonetic],
            [
                $token->withRole(NameRole::GivenName)->canonical,
                $token->withRole(NameRole::GivenName)->core,
                $token->withRole(NameRole::GivenName)->phonetic,
            ],
        );
    }
}
