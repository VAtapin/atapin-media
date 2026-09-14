<?php

namespace App\Http\Controllers;

use App\Services\Publishing\TelegramWebsite;

class TelegramMiniAppController extends Controller
{
    public function material(string $token, TelegramWebsite $website)
    {
        return response()->json(['url' => $website->materialUrl($token)])->header('Cache-Control', 'no-store');
    }
}
