<?php

namespace App\Services\Notification;

use Kreait\Firebase\Messaging\CloudMessage;

class PushNotificationService
{

    public static function sendMessage(string $fcm_token, array $data): void
    {
        $messaging = app('firebase.messaging');

        $message = CloudMessage::fromArray([
            'token' => $fcm_token,
            'notification' => [
                'title' => $data['title'],
                'body' => $data['body']
            ]
        ]);
        $messaging->send($message);
    }
}
