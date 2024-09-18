<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class SocketController extends Controller
{
     public function triggerEvent(Request $request)
    {
        $client = new Client();
        $url = 'http://192.168.10.14:3000/trigger-event'; // Ensure this endpoint is handled by your Socket.IO server

        $response = $client->post($url, [
            'json' => [
                'event' => 'test',
                'data' => $request->input('data')
            ]
        ]);

        return response()->json(['status' => 'success', 'response' => $response->getBody()->getContents()]);
    }
}
