<?php
require_once __DIR__ . '/functions.php';

// configuration
const BOT_TOKEN = '7954391684:AAEUOWnBMhLb1BbR7uBOsI_ETTLQ5v_9jBs';
const ADMIN_ID  = 7505722949;

$content = file_get_contents('php://input');
$update = json_decode($content, true);
if (!$update) {
    exit;
}

// collect join requests as they arrive
if (isset($update['chat_join_request'])) {
    $r = $update['chat_join_request'];
    addJoinRequest($r['chat']['id'], $r['from']['id']);
    exit;
}

// handle channel admin status updates to keep list
if (isset($update['my_chat_member'])) {
    $member = $update['my_chat_member'];
    $chat   = $member['chat'];
    $newStatus = $member['new_chat_member']['status'] ?? '';
    if ($chat['type'] === 'channel') {
        if ($newStatus === 'administrator') {
            addChannel($chat);
        } elseif (in_array($newStatus, ['kicked','left'])) {
            removeChannel($chat['id']);
        }
    }
    exit;
}

$chat_id = null;
$user_id = null;
if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
} elseif (isset($update['callback_query'])) {
    $chat_id = $update['callback_query']['message']['chat']['id'];
    $user_id = $update['callback_query']['from']['id'];
}

if ($user_id != ADMIN_ID) {
    exit; // ignore non admin
}

$session = loadSession($chat_id);

if (isset($update['message'])) {
    $text = trim($update['message']['text']);
    if ($text === '/start' || $text === 'رجوع إلى القائمة الرئيسية') {
        resetSession($chat_id);
        showChannels($chat_id);
        exit;
    }
    if (($session['step'] ?? '') === 'awaiting_count') {
        $count = intval($text);
        if ($count > 0) {
            $channel_id = $session['channel_id'];
            $approved = approveRequests($channel_id, $count);
            resetSession($chat_id);
            sendMessage($chat_id, "✅ تم قبول {$approved} عضو", [
                'keyboard' => [[['text' => 'رجوع إلى القائمة الرئيسية']]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ]);
        } else {
            sendMessage($chat_id, 'يرجى إدخال رقم صحيح.');
        }
        exit;
    }
}

if (isset($update['callback_query'])) {
    $data = $update['callback_query']['data'];
    if (strpos($data, 'channel:') === 0) {
        $channel_id = substr($data, 8);
        $session['channel_id'] = $channel_id;
        $session['step'] = 'confirm_approve';
        saveSession($chat_id, $session);
        askApprove($chat_id);
        exit;
    } elseif ($data === 'approve_yes') {
        $session['step'] = 'awaiting_count';
        saveSession($chat_id, $session);
        sendMessage($chat_id, 'كم عدد الطلبات التي تريد الموافقة عليها؟');
        exit;
    } elseif ($data === 'approve_no') {
        resetSession($chat_id);
        showChannels($chat_id);
        exit;
    }
}

function showChannels($chat_id) {
    $channels = getChannels();
    if (!$channels) {
        sendMessage($chat_id, 'لا توجد قنوات مسجلة حالياً.');
        return;
    }
    $keyboard = ['inline_keyboard' => []];
    foreach ($channels as $id => $title) {
        $keyboard['inline_keyboard'][] = [[
            'text' => $title ?: $id,
            'callback_data' => 'channel:' . $id
        ]];
    }
    sendMessage($chat_id, 'اختر القناة التي تريد إدارتها:', $keyboard);
}

function askApprove($chat_id) {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '✅ نعم', 'callback_data' => 'approve_yes'],
                ['text' => '❌ لا', 'callback_data' => 'approve_no']
            ]
        ]
    ];
    sendMessage($chat_id, 'هل تريد الموافقة على طلبات الانضمام المعلّقة؟', $keyboard);
}

function approveRequests($channel_id, $limit) {
    // use stored pending requests (fallback if API method unavailable)
    $users = popJoinRequests($channel_id, $limit);
    $approved = 0;
    foreach ($users as $uid) {
        $ok = apiRequest('approveChatJoinRequest', [
            'chat_id' => $channel_id,
            'user_id' => $uid
        ]);
        if (isset($ok['ok']) && $ok['ok']) $approved++;
    }
    return $approved;
}


