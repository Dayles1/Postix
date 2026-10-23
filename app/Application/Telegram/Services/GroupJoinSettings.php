<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\TelegramSetting;
use Throwable;

/**
 * Join group's knobs, kept in the panel (Join group -> Settings) and
 * read fresh by every worker on every round, so a change applies within
 * a minute and switching it off stops them.
 *
 * The accounts are chosen here one by one: each runs its own worker under
 * the group join watchdog, and while the join is on no other process (the
 * phone lookup, the contact sync) is handed them.
 *
 * Off until somebody switches it on: a deploy must not send accounts into
 * groups by itself.
 */
final class GroupJoinSettings
{
    public const KEY = 'group_join.settings';

    /**
     * What the panel may set, with the bounds it is held to.
     */
    public const LIMITS = [
        'min_members' => [0, 1000000],
        'min_messages_week' => [0, 10000],
        'max_silence_hours' => [1, 2160],
        'joins_per_day' => [1, 50],
        'accounts_per_group' => [1, 30],
        'max_groups_per_account' => [1, 1000],
        'join_delay_min' => [1, 1440],
        'join_delay_max' => [1, 1440],
        'flood_extra_min' => [0, 600],
        'flood_extra_max' => [0, 600],
        'search_every_hours' => [1, 720],
        'recheck_days' => [1, 90],
        'max_members_read' => [200, 50000],
        'members_every_days' => [1, 90],
    ];

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'enabled' => false,

            /* The accounts that join; none until somebody marks them. */
            'accounts' => [],

            /* Quality: what a group has to be to get an account. */
            'min_members' => 100,
            'min_messages_week' => 20,
            'max_silence_hours' => 72,

            /* Pace: one account, one group at a time, with a pause between. */
            'joins_per_day' => 15,
            'accounts_per_group' => 1,
            /* Telegram stops an account at 500 groups and channels (1000 with Premium). */
            'max_groups_per_account' => 450,
            'join_delay_min' => 20,
            'join_delay_max' => 60,

            /* After a FLOOD_WAIT the account waits what Telegram said, plus this many seconds. */
            'flood_extra_min' => 20,
            'flood_extra_max' => 30,

            'search_every_hours' => 24,
            'recheck_days' => 7,

            /* The people of a joined group: read at all, how many at most, how often again. */
            'read_members' => true,
            'max_members_read' => 5000,
            'members_every_days' => 7,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $saved = TelegramSetting::get(self::KEY);

        return $this->normalise(is_array($saved) ? $saved : []);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function save(array $input): array
    {
        $values = $this->normalise($input);

        TelegramSetting::set(self::KEY, $values);

        return $values;
    }

    public function reset(): array
    {
        TelegramSetting::query()->where('key', self::KEY)->delete();

        return $this->current();
    }

    public function isSaved(): bool
    {
        return is_array(TelegramSetting::get(self::KEY));
    }

    public function enabled(): bool
    {
        return (bool) $this->current()['enabled'];
    }

    /**
     * The accounts the group join holds while it is on: the account pool
     * leaves them out, so the phone lookup or the contact sync never opens
     * a session a worker has open. Empty when it is off - they are free.
     *
     * @return list<int>
     */
    public static function dedicatedIds(): array
    {
        try {
            $values = app(self::class)->current();
        } catch (Throwable) {
            return [];
        }

        return $values['enabled'] ? $values['accounts'] : [];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function normalise(array $input): array
    {
        $values = array_replace($this->defaults(), array_intersect_key($input, $this->defaults()));

        foreach (self::LIMITS as $key => [$min, $max]) {
            $values[$key] = max($min, min($max, (int) $values[$key]));
        }

        $values['join_delay_max'] = max($values['join_delay_min'], $values['join_delay_max']);
        $values['flood_extra_max'] = max($values['flood_extra_min'], $values['flood_extra_max']);
        $values['enabled'] = filter_var($values['enabled'], FILTER_VALIDATE_BOOLEAN);
        $values['read_members'] = filter_var($values['read_members'], FILTER_VALIDATE_BOOLEAN);

        /* A listener's account is never one of them: its session is the listener's. */
        $listeners = ListenerAccounts::ids();

        $values['accounts'] = collect((array) $values['accounts'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && ! in_array($id, $listeners, true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $values;
    }
}
