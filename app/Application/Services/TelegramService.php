<?php

namespace App\Application\Services;

use App\Jobs\V2\ExecJob;
use App\Models\Catalog;
use App\Models\MessageGroup;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Models\UserPhone;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Keyboard\Keyboard;

class TelegramService
{
    public  Api $telegram;
    public function __construct(Api $telegram)
    {
        $this->telegram = $telegram;
    }
    public function mainMenuWithHistoryKeyboard(bool $hasActivePhone = true)
    {
        $keyboard = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true);

        $keyboard
            ->row([
                Keyboard::button('📂 Cataloglar'),
                Keyboard::button('📊 Yuborilgan xabarlar tarixi'),
            ])
            ->row([
                Keyboard::button('📘 Qo‘llanma'),
                Keyboard::button('💼 Oferta'),
            ])
            ->row([
            ]);
        if ($hasActivePhone) {
            $keyboard->row([
                Keyboard::button('📱 Telefonlarim'),
                Keyboard::button('🚀 Habar yuborish'),
            ]);
        }



        return $keyboard;
    }
    public function buildSendCatalogInlineKeyboard($user)
    {
        $keyboard = Keyboard::make()->inline();

        /** -------------------------
         *  1️⃣ USER O‘Z CATALOG'LARI
         * ------------------------- */
        $myCatalogs = Catalog::where('user_id', $user->id)->get();

        foreach ($myCatalogs as $catalog) {
            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => $catalog->title . ' (' . $user->name . ')',
                    'callback_data' => 'catalog_start_' . $catalog->id
                ])
            ]);
        }

        /** --------------------------------
         *  2️⃣ DEPARTMENT USERLARI CATALOGI
         * -------------------------------- */
        if ($user->department) {
            foreach ($user->department->users as $depUser) {

                // o‘zini yana qayta qo‘shmaslik
                if ($depUser->id === $user->id) {
                    continue;
                }

                foreach ($depUser->catalogs as $catalog) {
                    $keyboard->row([
                        Keyboard::inlineButton([
                            'text' => $catalog->title . ' (' . $depUser->name . ')',
                            'callback_data' => 'catalog_start_' . $catalog->id
                        ])
                    ]);
                }
            }
        }

        /** 🔙 Cancel */
        $keyboard->row([
            Keyboard::inlineButton([
                'text' => 'Menyuga qaytish',
                'callback_data' => 'cancel_catalog'
            ])
        ]);

        return $keyboard;
    }
    // Yangi/yangilangan method: buildCatalogKeyboardForSend
    public function buildCatalogKeyboardForSend(int $userId, int $page = 1)
    {
        // 1) Avval userning o'z kataloglari, keyin departmentdagi users kataloglari:
        $user = User::with('department.users')->find($userId);

        $userIds = [$userId];

        if ($user && $user->department) {
            // department->users may include the user himself; pluck va unique qilish
            $deptUserIds = $user->department->users->pluck('id')->toArray();
            $userIds = array_values(array_unique(array_merge($userIds, $deptUserIds)));
        }

        // 2) Cataloglarni yuklash, user relation bilan (owner nomi uchun)
        $catalogsCollection = Catalog::with('user')
            ->whereIn('user_id', $userIds)
            ->orderBy('id')
            ->get();

        // Map qilib arrayga o'tkazamiz va owner nomini qo'shamiz
        $catalogs = $catalogsCollection->map(function ($c) {
            $ownerName = $c->user->name ?? $c->user->username ?? ('user#' . $c->user_id);
            return [
                'id' => $c->id,
                'title' => $c->title,
                'owner_name' => $ownerName,
                'user_id' => $c->user_id,
            ];
        })->toArray();

        // 3) Pagination va keyboard qurish
        $perPage = 10;
        $chunks = array_chunk($catalogs, $perPage);
        $pageIndex = max(0, $page - 1);
        $pageCatalogs = $chunks[$pageIndex] ?? [];

        $keyboard = (new Keyboard)->inline();

        // Catalog tugmalari: har bir tugmada Title (Owner)
        $catalogButtons = [];
        foreach ($pageCatalogs as $catalog) {
            $text = $catalog['title'] . ' (' . $catalog['owner_name'] . ')';
            $catalogButtons[] = Keyboard::inlineButton([
                'text' => $text,
                'callback_data' => 'catalog_start_' . $catalog['id']
            ]);
        }

        // 2-ta tugma bir qatorda
        foreach (array_chunk($catalogButtons, 2) as $chunk) {
            $keyboard->row($chunk);
        }

        // Navigation tugmalari (prefikslar o'zgartirilgan)
        $navButtons = [];
        $totalPages = count($chunks);
        if ($page > 1) {
            $navButtons[] = Keyboard::inlineButton([
                'text' => '⬅ Previous',
                'callback_data' => 'catalog_send_page_' . ($page - 1)
            ]);
        }
        if ($page < $totalPages) {
            $navButtons[] = Keyboard::inlineButton([
                'text' => 'Next ➡',
                'callback_data' => 'catalog_send_page_' . ($page + 1)
            ]);
        }
        if ($navButtons) {
            $keyboard->row($navButtons);
        }

        // Bekor qilish tugmasi
        $keyboard->row([
            Keyboard::inlineButton([
                'text' => '⬅️Orqaga qaytish',
                'callback_data' => 'cancel_catalog'
            ])
        ]);

        return $keyboard;
    }

    public function buildCatalogKeyboard(int $userId, int $page = 1)
    {
        // Faqat user_id bo'yicha filtr
        $catalogs = Catalog::where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->toArray();

        $perPage = 10;
        $chunks = array_chunk($catalogs, $perPage);
        $pageCatalogs = $chunks[$page - 1] ?? [];

        $keyboard = (new Keyboard)->inline();

        $keyboard->row([
            Keyboard::inlineButton([
                'text' => '➕ Yangi Catalog yaratish',
                'callback_data' => 'catalog_create'
            ])
        ]);

        $catalogButtons = [];
        foreach ($pageCatalogs as $catalog) {
            $catalogButtons[] = Keyboard::inlineButton([
                'text' => $catalog['title'],
                'callback_data' => 'catalog_select_' . $catalog['id']
            ]);
        }

        foreach (array_chunk($catalogButtons, 2) as $chunk) {
            $keyboard->row($chunk);
        }

        $navButtons = [];

        if ($page > 1) {
            $navButtons[] = Keyboard::inlineButton([
                'text' => '⬅ Previous',
                'callback_data' => 'catalog_page_' . ($page - 1)
            ]);
        }

        if ($page < count($chunks)) {
            $navButtons[] = Keyboard::inlineButton([
                'text' => 'Next ➡',
                'callback_data' => 'catalog_page_' . ($page + 1)
            ]);
        }

        if ($navButtons) {
            $keyboard->row($navButtons);
        }

        $keyboard->row([
            Keyboard::inlineButton([
                'text' => '⬅️Orqaga qaytish',
                'callback_data' => 'cancel_catalog'
            ])
        ]);

        return $keyboard;
    }
    public function buildPhoneKeyboard(array $phones)
    {
        $keyboard = (new Keyboard)->inline();

        if (empty($phones)) {
            // Telefonlar yo'q bo'lsa, shunchaki xabar uchun tugma
            $keyboard = Keyboard::make()
                ->setResizeKeyboard(true)
                ->setOneTimeKeyboard(true)
                ->row([
                    Keyboard::inlineButton([
                        'text' => 'Menyuga qaytish',
                        'callback_data' => 'cancel_auth',
                    ]),
                ]);
        } else {
            // Telefonlar mavjud bo'lsa, har biri alohida qatorga
            foreach ($phones as $phone) {
                $keyboard->row([
                    Keyboard::inlineButton([
                        'text' => $phone['phone'],
                        'callback_data' => 'phone_select_' . $phone['id']
                    ])
                ]);
            }

            // Bekor qilish tugmasi
            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => 'Menyuga qaytish',
                    'callback_data' => 'cancel_auth'
                ])
            ]);
        }

        return $keyboard;
    }
    public function buildPhoneSelectKeyboard($phones, int $page = 1)
    {
        $perPage = 4;

        // collection → array
        $phonesArray = $phones instanceof \Illuminate\Support\Collection
            ? $phones->values()->toArray()
            : $phones;

        $chunks = array_chunk($phonesArray, $perPage);
        $pagePhones = $chunks[$page - 1] ?? [];

        $keyboard = (new Keyboard)->inline();

        // 📞 Phone buttons
        foreach ($pagePhones as $index => $phone) {

            $status = $phone['is_active'] ? '✅ Faol' : '⚪️ No faol';

            $text = (($page - 1) * $perPage + $index + 1)
                . '. ' . $phone['phone'] . ' ' . $status;

            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => $text,
                    'callback_data' => 'phone_choose_' . $phone['id'],
                ])
            ]);
        }

        // ⬅ ➡ Navigation
        $navButtons = [];

        if ($page > 1) {
            $navButtons[] = Keyboard::inlineButton([
                'text' => '⬅ Previous',
                'callback_data' => 'phone_page_' . ($page - 1),
            ]);
        }

        if ($page < count($chunks)) {
            $navButtons[] = Keyboard::inlineButton([
                'text' => 'Next ➡',
                'callback_data' => 'phone_page_' . ($page + 1),
            ]);
        }

        if ($navButtons) {
            $keyboard->row($navButtons);
        }

        // ❌ Cancel
        $keyboard->row([
            Keyboard::inlineButton([
                'text' => 'Menyuga qaytish',
                'callback_data' => 'cancel_auth',
            ])
        ]);

        return $keyboard;
    }
    public function buildGroupKeyboard(User $user, int $page = 1)
    {
        $perPage = 10;

        $phoneIds = $user->phones()->pluck('id')->toArray();

        // jami guruhlar soni (oyoq: bu tez)
        $total = MessageGroup::whereIn('user_phone_id', $phoneIds)->count();
        $lastPage = (int) max(1, ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));

        // Subquery to get latest message text per group (optional)
        $latestMsgSub = TelegramMessage::select('message_text')
            ->whereColumn('message_group_id', 'message_groups.id')
            ->latest()
            ->limit(1);

        // Oqilona select — faqat keraklilarini olamiz
        $groups = MessageGroup::whereIn('user_phone_id', $phoneIds)
            ->orderBy('updated_at', 'desc') // yoki needed order
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->withCount('messages')
            ->addSelect(['latest_message_text' => $latestMsgSub]) // qo'shimcha ustun
            ->get(['id', 'message_text', 'updated_at']); // zarur ustunlar

        $keyboard = (new Keyboard)->inline();

        foreach ($groups as $index => $group) {
            $text = $group->latest_message_text ?? $group->message_text ?? 'Xabar yo‘q';
            $num = (($page - 1) * $perPage + $index + 1);

            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => $num . '. ' . mb_strimwidth($text, 0, 25, '...') . ' — ' . $group->messages_count,
                    'callback_data' => 'group_select_' . $group->id,
                ])
            ]);
        }

        // Navigation
        $navButtons = [];
        if ($page > 1) {
            $navButtons[] = Keyboard::inlineButton([
                'text' => '⬅ Previous',
                'callback_data' => 'group_page_' . ($page - 1),
            ]);
        }
        if ($page < $lastPage) {
            $navButtons[] = Keyboard::inlineButton([
                'text' => 'Next ➡',
                'callback_data' => 'group_page_' . ($page + 1),
            ]);
        }
        if ($navButtons) {
            $keyboard->row($navButtons);
        }

        // Cancel
        $keyboard->row([
            Keyboard::inlineButton([
                'text' => 'Menyuga qaytish',
                'callback_data' => 'cancel_auth'
            ])
        ]);

        return $keyboard;
    }
    public function handleGroupSelect(string $groupId, int $chatId)
    {
        $group = MessageGroup::with('messages')->find($groupId);

        if (!$group || $group->messages->isEmpty()) {
            $this->tg(fn() => $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "⚠️ Guruh yoki xabarlar topilmadi."
            ]));
            return;
        }

        $messages = $group->messages;


        $headerText  = "📊 Guruh ma'lumotlari\n\n";
        $headerText .= "📌 Guruh ID: {$group->id}\n";
        $headerText .= "🕒 Boshlangan: " . optional($messages->min('send_at'))->format('Y-m-d H:i') . "\n";
        $headerText .= "⏰ Tugashi: " . optional($messages->max('send_at'))->format('Y-m-d H:i') . "\n\n";
        $headerText .= "⏰ Last sent at: " . optional(
            $messages->where('status', 'sent')->max('updated_at')
        )->format('Y-m-d H:i') . "\n\n";

        $headerText .= "📝 Message:\n";
        $headerText .= $group->message_text;



        /**
         * 🎹 KEYBOARD faqat birinchi xabarda
         */
        $replyKeyboard = Keyboard::make()->setResizeKeyboard(true);

        $hasPendingOrScheduled = $messages->contains(
            fn($msg) => in_array($msg->status, ['scheduled', 'pending'])
        );

        if ($hasPendingOrScheduled) {
            $replyKeyboard->row([
                Keyboard::button("❌ To‘xtatish {$group->id}"),
                Keyboard::button("🔄 Malumotlarni yangilash {$group->id}")
            ]);
        }
        $hasFailed = $messages->contains(fn($msg) => $msg->status === 'failed');

        if ($hasFailed) {
            $replyKeyboard->row([
                Keyboard::button("❌ Failed lar {$group->id}")
            ]);
        }
        $replyKeyboard->row([
            Keyboard::button("📊 Yuborilgan xabarlar tarixi"),
        ])->row([
            Keyboard::button("Menyuga qaytish")
        ]);

        $this->tg(fn() => $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $headerText,
            'reply_markup' => $replyKeyboard
        ]));

        /**
         * 2️⃣ PEERLAR BO‘YICHA HOLAT
         * Har 30 ta peer = 1 xabar
         */
        $peers = $messages->groupBy('peer')->chunk(30);
        $page = 1;

        foreach ($peers as $chunk) {

            $text = "👥 Peerlar bo‘yicha holat (qism {$page})\n\n";

            foreach ($chunk as $peer => $peerMessages) {
                $counts = $peerMessages->groupBy('status')->map->count();

                $statusText = collect([
                    'pending'   => '🕓',
                    'scheduled' => '📅',
                    'sent'      => '✅',
                    'failed'    => '❌',
                    'canceled'  => '🚫',
                ])
                    ->filter(fn($icon, $status) => ($counts[$status] ?? 0) > 0)
                    ->map(fn($icon, $status) => "{$icon} {$counts[$status]}")
                    ->implode(' | ');

                $text .= "• {$peer} — {$statusText}\n";
            }

            $this->tg(fn() => $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text
            ]));

            $page++;
        }
    }
    public function showFailedPeers(string $groupId, int $chatId)
{
    $group = MessageGroup::with('messages')->find($groupId);

    if (!$group) {
        return;
    }

    // local uzbek explanations
    $uzExpl = [
        'flood_wait' => "Juda ko‘p so‘rov yuborildi — Telegram sizni vaqtincha chekladi. Birozdan keyin qayta urinib ko‘ring.",
        'slowmode_wait'=>"Siz bu guruhga yana habar jonatish uchun kutushiz kerek",
        'chat_write_forbidden' => "Bu chatga xabar yuborish uchun ruxsat yo‘q.",
        'user_blocked' => "Foydalanuvchi sizni bloklagan yoki akkaunt o‘chirilgan — yuborish imkoni yo‘q.",
        'peer_flood' => "Ushbu chat/foydalanuvchiga yuborishda vaqtincha cheklov mavjud (flood).",
        'phone_migrate' => "Telefon sessiyasi migratsiya qilinmoqda — sozlamalarni tekshiring.",
        'session_password_needed' => "Sessiya paroli talab qilinadi — seans sozlanishi kerak.",
        'network_error' => "Tarmoq xatosi yuz berdi — internet aloqasini tekshiring.",
        'peer_not_found' => "Foydalanuvchi yoki guruh topilmadi — username yoki link noto‘g‘ri bo‘lishi mumkin.",
        'chat_guest_send_forbidden' => "Guruhga xabar yuborish uchun avval guruhga qo‘shiling yoki administratsiyadan ruxsat oling.",
        'SCHEDULE_TOO_MUCH' => "Juda ko'p rejalashtirilgan xabarlar mavjud — iltimos, biroz kuting yoki rejalashtirilgan xabarlarni kamaytiring.",
        'unknown_error' => "Noma'lum xatolik yuz berdi.",
    ];

    // Faqat failed statusdagi message larni olib, peerni normalizatsiya qilib guruhlaymiz
    $groups = $group->messages
        ->where('status', 'failed')
        ->map(function ($m) {
            $m->normalized_peer = $this->normalizePeer((string) $m->peer);
            return $m;
        })
        ->groupBy('normalized_peer');

    if ($groups->isEmpty()) {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "✅ Failed xabarlar yo‘q"
        ]);
        return;
    }

    $chunks = $groups->chunk(30);
    $page = 1;

    foreach ($chunks as $chunk) {
        $text = "👥 Peerlar bo‘yicha holat (qism {$page})\n\n";

        foreach ($chunk as $peer => $msgs) {
            $count = $msgs->count();

            // eng ko'p uchragan error_key
            $mostKey = $msgs->pluck('error_key')
                ->filter()
                ->countBy()
                ->sortDesc()
                ->keys()
                ->first() ?: 'unknown_error';

            // Dynamic flood_wait_{seconds} va slowmode_wait_{seconds} qo'llash
            if (preg_match('/^(flood_wait|slowmode_wait)_(\d+)$/', $mostKey, $matches)) {
                $type = $matches[1]; // flood_wait yoki slowmode_wait
                $seconds = (int)$matches[2];
                $min = intdiv($seconds, 60);
                $sec = $seconds % 60;

                $timeStr = $min > 0 ? "$min daqiqa" . ($sec > 0 ? " $sec soniya" : "") : "$sec soniya";

                $baseMsg = $uzExpl[$type] ?? $uzExpl['unknown_error'];
                $explanation = $baseMsg . " (Kutish vaqti: $timeStr)";
            } else {
                $explanation = $uzExpl[$mostKey] ?? $uzExpl['unknown_error'];
            }

            $text .= "• {$peer} — ❌ {$count}\n";
            $text .= $explanation . "\n\n";
        }

        $this->sendLongMessage($chatId, $text);
        $page++;
    }
}

    private function sendLongMessage(int $chatId, string $text, int $limit = 4000)
    {
        $chunks = mb_str_split($text, $limit);

        foreach ($chunks as $chunk) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => trim($chunk)
            ]);
        }
    }

    /**
     * Simple peer normalizer: t.me links -> @username, tg://resolve -> @username, trim, remove trailing slashes
     */
    private function normalizePeer(string $peer): string
    {
        $p = trim($peer);

        // t.me link -> @username
        $p = preg_replace('#^https?://t\.me/#i', '@', $p);

        // tg://resolve?domain=...
        if (preg_match('#tg://resolve\?domain=([^&/?]+)#i', $p, $m)) {
            $p = '@' . $m[1];
        }

        // remove trailing slash
        $p = rtrim($p, '/');

        return $p;
    }
    public function createMessageGroup($user, $chatId)
    {
        $data = json_decode($user->value, true);

        $phone = UserPhone::find($data['phone_id']);
        if (!$phone) {
            $this->tg(
                fn() =>
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Telefon topilmadi."
                ])
            );
            return 'ok';
        }
        $loopCount = (int) $data['loop_count'];
        $interval  = (int) $data['interval'];
        // MessageGroup yaratish
        $group = MessageGroup::create([
            'user_phone_id' => $phone->id,
            'status' => 'pending',
            'message_text' => $data['message_text'],
            'total_batches' => $loopCount,
            'interval' => $interval,
            'current_batch' => 0,
        ]);

        

        $peers = [];
            $catalog = Catalog::find($data['catalog_id']);
            if ($catalog) {
                $group->catalogs()->attach($catalog->id);

                $catalogPeers = is_array($catalog->peers)
                    ? $catalog->peers
                    : json_decode($catalog->peers ?? '[]', true);

                $peers = array_merge($peers, $catalogPeers);
            }

        
        $messagesToInsert = [];
        $base = now();

        foreach ($peers as $peer) {
            for ($i = 0; $i < $loopCount; $i++) {
                $sendAt = $base->copy()->addMinutes($i * max(0, $interval));

                $messagesToInsert[] = [
                    'message_group_id' => $group->id,
                    'peer' => $peer,
                    'send_at' => $sendAt,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($messagesToInsert)) {
            TelegramMessage::insert($messagesToInsert);
        }


        // SendTelegramMessages::dispatch($group->id)->onQueue('telegram');


        foreach (range(1, $loopCount) as $batchNo) {
            ExecJob::dispatch($group->id, $batchNo)->onQueue('telegram')->delay(now()->addMinutes(($batchNo - 1) * max(0, $interval)));
        }

        $this->tg(
            fn() =>
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "✅ Xabarlar jadvali yaratildi va navbatga qo‘yildi. \n/history - orqali ularni korishingiz mumkin ",
                'reply_markup' => $this->mainMenuWithHistoryKeyboard(true)
            ])
        );

        $user->state = null;
        $user->value = null;
        $user->save();

        return 'ok';
    }
    public  function cancelInlineKeyboard()
    {
        return (new Keyboard)->inline()
            ->row([
                Keyboard::inlineButton([
                    'text' => 'Menyuga qaytish',
                    'callback_data' => 'cancel_auth'
                ])
            ]);
    }
    public function cancelAuth(User $user, int $chatId, ?string $callbackQueryId = null)
    {
        $user->phones()
            ->whereIn('state', ['waiting_code', 'waiting_password', 'waiting_code2'])
            ->update([
                'state' => 'cancelled',
                'code' => null
            ]);

        $user->state = null;
        $user->save();

        if ($callbackQueryId) {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => 'Bekor qilindi',
                'show_alert' => false,
            ]);
        }

        // 🔹 Asosiy menyu
        $hasActivePhone = $user->phones()->where('is_active', true)->exists();
        $this->tg(fn() =>

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Bosh menyu:',
            'reply_markup' => $this->mainMenuWithHistoryKeyboard($hasActivePhone)
        ]));

        return 'ok';
    }
    public  function tg(callable $fn)
    {
        try {
            return $fn();
        } catch (TelegramResponseException $e) {

            // 🔕 User botni block qilgan — jim yutamiz
            if (
                str_contains($e->getMessage(), 'bot was blocked by the user') ||
                str_contains($e->getMessage(), 'user is deactivated')
            ) {
                return null;
            }

            // ⚠️ boshqa telegram xatolarni log qilamiz
            Log::warning('Telegram API error', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }


    public function manualText(): string
    {
        return file_get_contents(resource_path('texts/manual.md'));
    }
}
