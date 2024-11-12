<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class LineController extends Controller
{
    private $channelAccessToken;
    private $channelSecret;

    public function __construct()
    {
        $this->channelAccessToken = env('LINE_CHANNEL_ACCESS_TOKEN');
        $this->channelSecret = env('LINE_CHANNEL_SECRET');
    }

    public function webhook(Request $request, Response $response) {
        Log::debug($request);

        return response()->json([
            'message' => "yay"
        ]);
    }

    public function webhook2(Request $request, Response $response) {
        // リクエストボディを取得
        $body = $request->getContent();

        // 署名検証
        $signature = $request->header('X-Line-Signature');
        if (!$this->isSignatureValid($body, $signature)) {
            return response('Invalid signature', 400);
        }

        // ログにリクエスト内容を出力（デバッグ用）
        Log::debug($request);

        // JSONをデコード
        $events = json_decode($body, true)['events'];

        foreach ($events as $event) {
            // イベントがメッセージイベントか確認
            if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
                $replyToken = $event['replyToken'];

                // 返信メッセージの作成
                $responseMessage = [
                    'replyToken' => $replyToken,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => 'Hello World!'
                        ]
                    ]
                ];

                // LINE Messaging APIにリプライ
                $this->replyMessage($responseMessage);
            }
        }

        return response('OK', 200);
    }


    /**
     * LINEの署名を検証する
     */
    private function isSignatureValid($body, $signature)
    {
        $hash = hash_hmac('sha256', $body, $this->channelSecret, true);
        $expectedSignature = base64_encode($hash);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * LINE Messaging APIにメッセージを返信する
     */
    private function replyMessage($message)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->channelAccessToken,
        ])->post('https://api.line.me/v2/bot/message/reply', $message);

        if ($response->failed()) {
            Log::error('Failed to send reply:', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } else {
            Log::debug('Reply sent successfully.');
        }
    }
}
