<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\AutoReplyStore;
use App\Application\Telegram\Services\ClientCheckRulesStore;
use App\Application\Telegram\Services\PersonalAnswers;
use App\Application\Telegram\Services\UzbekScript;
use App\Models\Telegram\OperationUser;
use Illuminate\Console\Command;

/**
 * The Uzbek texts saved in the panel - auto replies, penalty phrases and the
 * personal answers of people who are written to in Uzbek - rewritten in
 * Cyrillic, so the panel shows what the person gets.
 *
 * The stores do the same on every save; this is for what was saved before.
 * Safe to run again: Cyrillic stays as it is. Keywords are not touched.
 */
final class UzbekCyrillicCommand extends Command
{
    protected $signature = 'telegram:cyrillic-uz';

    protected $description = 'Rewrite the saved Uzbek auto replies, penalty phrases and personal answers in Cyrillic';

    public function handle(AutoReplyStore $autoReplies, ClientCheckRulesStore $penalties): int
    {
        if (! UzbekScript::enabled()) {
            $this->warn('auto_replies.uz_script is not "cyrillic": nothing to do.');

            return self::SUCCESS;
        }

        if ($autoReplies->exists() && $autoReplies->error() === null) {
            $autoReplies->save($autoReplies->current());
            $this->line('Auto replies: saved in Cyrillic.');
        }

        if ($penalties->isCustomised()) {
            $penalties->save($penalties->current());
            $this->line('Penalties: saved in Cyrillic.');
        }

        $changed = 0;

        OperationUser::query()
            ->whereNotNull('personal_answers')
            ->each(function (OperationUser $person) use (&$changed): void {
                if ($person->messageLanguage() !== OperationUser::LANGUAGE_UZ) {
                    return;
                }

                $answers = PersonalAnswers::sanitize($person->personal_answers ?? []);
                $before = $answers;

                foreach ($answers as $slot => $data) {
                    foreach ($data['items'] as $i => $item) {
                        if ($item['type'] === PersonalAnswers::TYPE_TEXT) {
                            $answers[$slot]['items'][$i]['text'] = UzbekScript::toCyrillic($item['text']);
                        }
                    }
                }

                if ($answers !== $before) {
                    $person->update(['personal_answers' => $answers]);
                    $changed++;
                }
            });

        $this->info("Personal answers rewritten for {$changed} people.");

        return self::SUCCESS;
    }
}
