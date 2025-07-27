<?php

// Telegram Bot API helper functions

function apiRequest($method, $params = []) {
    $token = BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$token}/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function sendMessage($chat_id, $text, $keyboard = null) {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    return apiRequest('sendMessage', $params);
}

// session helpers
function sessionFile($chat_id) {
    return __DIR__ . '/data/' . $chat_id . '.json';
}

function loadSession($chat_id) {
    $file = sessionFile($chat_id);
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return [];
}

function saveSession($chat_id, $data) {
    file_put_contents(sessionFile($chat_id), json_encode($data));
}

function resetSession($chat_id) {
    $file = sessionFile($chat_id);
    if (file_exists($file)) unlink($file);
}

// channels list stored in data/channels.json
function addChannel($chat) {
    $file = __DIR__ . '/data/channels.json';
    $channels = [];
    if (file_exists($file)) {
        $channels = json_decode(file_get_contents($file), true) ?: [];
    }
    $channels[$chat['id']] = $chat['title'] ?? '';
    file_put_contents($file, json_encode($channels));
}

function removeChannel($chat_id) {
    $file = __DIR__ . '/data/channels.json';
    if (!file_exists($file)) return;
    $channels = json_decode(file_get_contents($file), true) ?: [];
    if (isset($channels[$chat_id])) {
        unset($channels[$chat_id]);
        file_put_contents($file, json_encode($channels));
    }
}

function getChannels() {
    $file = __DIR__ . '/data/channels.json';
    if (!file_exists($file)) return [];
    $channels = json_decode(file_get_contents($file), true);
    return is_array($channels) ? $channels : [];
}

// pending join requests per channel
function pendingFile($channel_id) {
    return __DIR__ . '/data/pending_' . $channel_id . '.json';
}

function addJoinRequest($channel_id, $user_id) {
    $file = pendingFile($channel_id);
    $pending = [];
    if (file_exists($file)) {
        $pending = json_decode(file_get_contents($file), true) ?: [];
    }
    $pending[] = $user_id;
    file_put_contents($file, json_encode($pending));
}

function popJoinRequests($channel_id, $limit) {
    $file = pendingFile($channel_id);
    if (!file_exists($file)) return [];
    $pending = json_decode(file_get_contents($file), true) ?: [];
    $slice = array_splice($pending, 0, $limit);
    file_put_contents($file, json_encode($pending));
    return $slice;
}


