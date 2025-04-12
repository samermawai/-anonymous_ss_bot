<?php
require __DIR__ . '/vendor/autoload.php';

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;

// Load environment variables (e.g., BOT_TOKEN, WEBHOOK_URL)
$bot_api_key = getenv('BOT_TOKEN');
$bot_username = 'AnonymousSSBot'; // Replace with your bot username
$webhook_url = getenv('WEBHOOK_URL');

try {
    // Create Telegram API object
    $telegram = new Telegram($bot_api_key, $bot_username);

    // Set webhook
    $result = Request::setWebhook(['url' => $webhook_url]);
    if ($result->isOk()) {
        echo "Webhook set successfully.\n";
    }

    // Handle incoming updates
    $telegram->addCommandsPath(__DIR__ . '/commands');
    $telegram->enableAdmins([getenv('ADMIN_ID')]); // Set your admin ID as env variable

    // Load or initialize users.json
    $usersFile = __DIR__ . '/users.json';
    $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : ['waiting_user' => null, 'connections' => [], 'all_users' => []];

    // Handle webhook updates
    $update = Request::getWebhookUpdates();
    if ($update instanceof Update) {
        $message = $update->getMessage();
        $callbackQuery = $update->getCallbackQuery();

        if ($message) {
            $chat_id = $message->getChat()->getId();
            $text = $message->getText();

            $users['all_users'][$chat_id] = true;
            file_put_contents($usersFile, json_encode($users));

            switch (strtolower(trim($text))) {
                case '/start':
                    $reply = "🌐 Welcome to Anonymous Chat Bot! 🌐\nStay anonymous and connect with others! Use /connect to find a chat partner or /invite to bring your friends! 😎";
                    Request::sendMessage(['chat_id' => $chat_id, 'text' => $reply]);
                    break;

                case '/connect':
                    if (isset($users['connections'][$chat_id])) {
                        Request::sendMessage(['chat_id' => $chat_id, 'text' => "⚠️ You're already chatting! Use /disconnect to end. 😶"]);
                        break;
                    }

                    if ($users['waiting_user'] === $chat_id) {
                        Request::sendMessage(['chat_id' => $chat_id, 'text' => "⏳ You're already waiting for a partner. Please wait..."]);
                        break;
                    }

                    if ($users['waiting_user'] === null) {
                        $users['waiting_user'] = $chat_id;
                        file_put_contents($usersFile, json_encode($users));
                        Request::sendMessage(['chat_id' => $chat_id, 'text' => "⏳ Waiting for another user to join... (45s timeout)"]);
                    } else {
                        if ($users['waiting_user'] === $chat_id) {
                            Request::sendMessage(['chat_id' => $chat_id, 'text' => "❌ You can't connect to yourself! Wait for another user. 😅"]);
                            break;
                        }
                        $users['connections'][$chat_id] = $users['waiting_user'];
                        $users['connections'][$users['waiting_user']] = $chat_id;
                        $users['waiting_user'] = null;
                        file_put_contents($usersFile, json_encode($users));
                        Request::sendMessage(['chat_id' => $users['waiting_user'], 'text' => "✅ Connected anonymously! Start chatting! 💬"]);
                        Request::sendMessage(['chat_id' => $chat_id, 'text' => "✅ Connected anonymously! Start chatting! 💬"]);
                    }
                    break;

                case '/disconnect':
                    if (isset($users['connections'][$chat_id])) {
                        $partner_id = $users['connections'][$chat_id];
                        unset($users['connections'][$chat_id]);
                        unset($users['connections'][$partner_id]);
                        file_put_contents($usersFile, json_encode($users));
                        Request::sendMessage(['chat_id' => $partner_id, 'text' => "🚪 Your partner disconnected. Use /connect to find a new one!"]);
                        Request::sendMessage(['chat_id' => $chat_id, 'text' => "✅ Disconnected! Use /connect to reconnect."]);
                    } else {
                        Request::sendMessage(['chat_id' => $chat_id, 'text' => "❌ You're not connected!"]);
                    }
                    break;

                case '/invite':
                    $invite_link = Request::exportChatInviteLink(['chat_id' => getenv('CHAT_ID')]);
                    Request::sendMessage(['chat_id' => $chat_id, 'text' => "Invite your friends to join the Anonymous Chat Community! 📩\nClick this link to join: $invite_link\nMore friends = more fun! 😄"]);
                    break;

                default:
                    if (isset($users['connections'][$chat_id])) {
                        $partner_id = $users['connections'][$chat_id];
                        Request::sendMessage(['chat_id' => $partner_id, 'text' => "💬 " . $text]);
                    } else {
                        Request::sendMessage(['chat_id' => $chat_id, 'text' => "❌ You're not connected! Use /connect to start."]);
                    }
                    break;
            }
        } elseif ($callbackQuery) {
            $chat_id = $callbackQuery->getMessage()->getChat()->getId();
            $data = $callbackQuery->getData();
            $message_id = $callbackQuery->getMessage()->getMessageId();

            if ($data === 'try_again') {
                // Simulate /connect command
                $callbackQuery->answer();
                $text = '/connect';
                $update->setMessage($message); // Reuse message object for consistency
                $message->setText($text);
                // Recursively call the message handler
                $telegram->handle();
            } elseif (preg_match('/^reveal_(yes|no)_(\d+)$/', $data, $matches)) {
                $action = $matches[1];
                $partner_id = (int)$matches[2];
                if (isset($users['connections'][$chat_id]) && $users['connections'][$chat_id] === $partner_id) {
                    if ($action === 'yes') {
                        $user = Request::getChat(['chat_id' => $chat_id]);
                        $partner = Request::getChat(['chat_id' => $partner_id]);
                        $user_name = $user->getFirstName() ?: 'Anonymous';
                        $partner_name = $partner->getFirstName() ?: 'Anonymous';
                        Request::sendMessage(['chat_id' => $partner_id, 'text' => "🎉 Identity revealed! Your partner is $user_name"]);
                        Request::sendMessage(['chat_id' => $chat_id, 'text' => "🎉 Identity revealed! Your partner is $partner_name"]);
                        Request::editMessageText(['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "✅ Identity revealed successfully! 🎉"]);
                    } else {
                        Request::sendMessage(['chat_id' => $partner_id, 'text' => "😔 Your partner declined to reveal their identity."]);
                        Request::editMessageText(['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "❌ Reveal request declined! 😔"]);
                    }
                }
                $callbackQuery->answer();
            }
        }

        // Timeout check (simulated via webhook response)
        if ($users['waiting_user'] && time() - filemtime($usersFile) > 45) {
            $waiting_id = $users['waiting_user'];
            $keyboard = new InlineKeyboard([['text' => 'Try Again 🔄', 'callback_data' => 'try_again']]);
            Request::sendMessage([
                'chat_id' => $waiting_id,
                'text' => "⏰ No user found, please try again or invite your friends to talk!",
                'reply_markup' => $keyboard
            ]);
            $users['waiting_user'] = null;
            file_put_contents($usersFile, json_encode($users));
        }
    }
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
    echo "Error: " . $e->getMessage() . "\n";
}