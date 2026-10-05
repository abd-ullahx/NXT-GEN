<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{id}', function ($user, $id) {
    // Only allow users to listen to their own personal chat channel
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.channel.{channelId}', function ($user, $channelId) {
    // Verify the user is authenticated to listen to group channels
    return $user != null;
});
