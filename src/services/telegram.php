<?php

function sendTelegramMessage(string $message): bool
{
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    $chatId   = getenv('TELEGRAM_CHAT_ID');

    if (!$botToken || !$chatId) {
        return false;
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $payload = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);

    curl_exec($ch);
    curl_close($ch);

    return true;
}
