<?php

namespace App\Services\Support;

use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramService
{
    public function send(string $content, ?int $chatId = null): void
    {
        if ($chatId === null) {
            $chatId = 235988277; // todo
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $content,
        ]);
    }
}
