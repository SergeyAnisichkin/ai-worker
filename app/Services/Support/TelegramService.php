<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramService
{
    public function send(string $content, ?int $chatId = null): void
    {
        if ($chatId === null) {
            $chatId = 235988277; // todo
        }

        $res = Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $content,
        ]);

        Log::info("TG send message ", ['response' => $res]);
    }

    public function getUpdates(int $updateId): array
    {
         return Telegram::getUpdates([
             'offset' => $updateId,
             'timeout' => 30, // second
        ]);
    }
}
