<?php
// app/Helpers/pusher_helper.php

use Pusher\Pusher;

function get_pusher() {
    return new Pusher(
        env('pusher.key'),
        env('pusher.secret'),
        env('pusher.app_id'),
        ['cluster' => env('pusher.cluster'), 'useTLS' => true]
    );
}