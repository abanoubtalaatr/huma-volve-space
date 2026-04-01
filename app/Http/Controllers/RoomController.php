<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function join(\App\Models\Room $room, Request $request)
    {
        $user = $request->user();

        // Find current presence if any
        $oldPresence = \App\Models\UserPresence::where('user_id', $user->id)->first();
        if ($oldPresence && $oldPresence->room_id && $oldPresence->room_id != $room->id) {
            broadcast(new \App\Events\UserLeftRoom($user->id, $oldPresence->room_id))->toOthers();
        }

        \App\Models\UserPresence::updateOrCreate(
            ['user_id' => $user->id],
            ['room_id' => $room->id, 'status' => 'online']
        );

        broadcast(new \App\Events\UserJoinedRoom($user, $room))->toOthers();

        // Generate LiveKit Token
        $options = (new \Agence104\LiveKit\AccessTokenOptions())
            ->setIdentity((string) $user->id)
            ->setName($user->name);

        $token = new \Agence104\LiveKit\AccessToken(
            config('services.livekit.api_key'),
            config('services.livekit.api_secret'),
            $options
        );

        // Using VideoGrant
        $videoGrant = new \Agence104\LiveKit\VideoGrant();
        $videoGrant->setRoomJoin(true);
        $videoGrant->setRoomName('room_' . $room->id);
        $token->setGrant($videoGrant);

        return response()->json([
            'message' => 'Joined room ' . $room->name,
            'token' => $token->toJwt(),
            'livekit_url' => config('services.livekit.url'),
        ]);
    }

    public function leave(Request $request)
    {
        $user = $request->user();
        $oldPresence = \App\Models\UserPresence::where('user_id', $user->id)->first();

        if ($oldPresence && $oldPresence->room_id) {
            broadcast(new \App\Events\UserLeftRoom($user->id, $oldPresence->room_id))->toOthers();
        }

        \App\Models\UserPresence::where('user_id', $user->id)->update([
            'room_id' => null,
            'status' => 'offline'
        ]);

        return response()->json(['message' => 'Left room']);
    }
}
