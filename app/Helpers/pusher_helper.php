<?php
// app/Helpers/pusher_helper.php

use Pusher\Pusher;

if (!class_exists('SafePusherClient')) {
    class SafePusherClient
    {
        private ?Pusher $client;

        public function __construct(?Pusher $client = null)
        {
            $this->client = $client;
        }

        public function trigger($channels, string $event, array $data = [], array $params = [], bool $alreadyEncoded = false)
        {
            if (!$this->client) {
                log_message('warning', 'Pusher trigger skipped for {event}: realtime service is not configured.', [
                    'event' => $event,
                ]);
                return false;
            }

            try {
                return $this->client->trigger($channels, $event, $data, $params, $alreadyEncoded);
            } catch (\Throwable $e) {
                log_message('error', 'Pusher trigger failed for {event}: {message}', [
                    'event' => $event,
                    'message' => $e->getMessage(),
                ]);
                return false;
            }
        }
    }
}

function get_pusher() {
    $key = env('pusher.key');
    $secret = env('pusher.secret');
    $appId = env('pusher.app_id');
    $cluster = env('pusher.cluster');

    if (!$key || !$secret || !$appId || !$cluster) {
        return new SafePusherClient();
    }

    try {
        return new SafePusherClient(new Pusher(
            $key,
            $secret,
            $appId,
            ['cluster' => $cluster, 'useTLS' => true]
        ));
    } catch (\Throwable $e) {
        log_message('error', 'Pusher client could not be created: {message}', [
            'message' => $e->getMessage(),
        ]);
        return new SafePusherClient();
    }
}
