<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LineController extends Controller
{
    public function webhook(Request $request, Response $response) {

    Log::debug($request);

    return response()->json([
        'message' => "yay"
    ]);
    }
}
