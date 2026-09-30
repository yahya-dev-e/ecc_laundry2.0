<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('users.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

Broadcast::channel('machines.{id}', function (User $user, int $id) {
    return true; // All authenticated students can listen to real-time machine status changes
});
