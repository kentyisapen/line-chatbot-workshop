<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\RichMenu;
use App\Models\User;

class LineController extends Controller
{
    private $channelAccessToken;
    private $channelSecret;

    public function __construct()
    {
        $this->channelAccessToken = env('LINE_CHANNEL_ACCESS_TOKEN');
        $this->channelSecret = env('LINE_CHANNEL_SECRET');
    }

    // 疎通確認
    public function webhook(Request $request, Response $response) {
        Log::debug($request);

        return response()->json([
            'message' => "yay"
        ]);
    }

    // 返信テスト(+署名検証)
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
                $responseMessage = 
                        [
                            'type' => 'text',
                            'text' => 'Hello World!'
                        ];

                // LINE Messaging APIにリプライ
                $this->replyMessage($replyToken, $responseMessage);
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
    private function replyMessage($replyToken, $messages)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->channelAccessToken,
        ])->post('https://api.line.me/v2/bot/message/reply', [
            'replyToken' => $replyToken,
            'messages' => $messages,
        ]);

        if ($response->failed()) {
            Log::error('Failed to send reply:', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } else {
            Log::debug('Reply sent successfully.');
        }
    }

    public function webhook3(Request $request)
    {
        // ログにリクエスト内容を出力（デバッグ用）
        Log::debug($request);

        $body = $request->getContent();
        $signature = $request->header('X-Line-Signature');

        if (!$this->isSignatureValid($body, $signature)) {
            Log::warning('Invalid signature.');
            return response('Invalid signature', 400);
        }

        $events = json_decode($body, true)['events'] ?? [];

        foreach ($events as $event) {
            $userId = $event['source']['userId'] ?? null;

            if (!$userId) {
                continue;
            }

            // ユーザーを取得または作成
            $user = User::firstOrCreate(
                ['user_id' => $userId],
                ['state' => 'idle']
            );

            switch ($event['type']) {
                case 'follow':
                    $this->handleFollow($user, $event);
                    break;

                case 'unfollow':
                    $this->handleUnfollow($user, $event);
                    break;

                case 'message':
                    $this->handleMessage($user, $event);
                    break;

                case 'postback':
                    $this->handlePostback($user, $event);
                    break;

                default:
                    Log::info("Unhandled event type: {$event['type']}");
            }
        }

        return response('OK', 200);
    }

    private function handleFollow(User $user, $event)
    {
        Log::info("User {$user->user_id} followed the bot.");
        Log::info($event);

        // 初期メッセージを送信
        $this->replyMessage($event['replyToken'], [
            [
                'type' => 'text',
                'text' => '友達登録ありがとうございます！「受診を開始する」を選択してください。',
            ],
        ]);

        // 「受診を開始する」リッチメニューを割り当て
        $this->linkRichMenu($user->user_id, 'start_consultation');
    }

    private function handleUnfollow(User $user, $event)
    {
        Log::info("User {$user->user_id} unfollowed the bot.");
        // 必要に応じて、ユーザー情報を更新または削除
    }

    private function handleMessage(User $user, $event)
    {
        $message = $event['message']['text'] ?? '';

        // 現在のユーザー状態に基づいて処理を分岐
        if ($user->state === 'idle') {
            // 状態がidleの場合の処理（必要に応じて実装）
            $this->replyMessage($event['replyToken'], [
                [
                    'type' => 'text',
                    'text' => '「受診を開始する」を選択してください。',
                ],
            ]);
        } else {
            // 他の状態の場合の処理（必要に応じて実装）
            $this->replyMessage($event['replyToken'], [
                [
                    'type' => 'text',
                    'text' => '現在、他の操作を行っています。しばらくお待ちください。',
                ],
            ]);
        }
    }

    private function handlePostback(User $user, $event)
    {
        $data = $event['postback']['data'] ?? '';

        // ユーザーの現在の状態を取得
        $currentState = $user->state;

        // 状態が 'started_consultation' でない場合、アクションに反応しない
        if ($currentState !== 'started_consultation' && $data !== "action=start_consultation") {
            // 必要に応じて、何もしないか、特定のメッセージを送信
            $this->replyMessage($event['replyToken'], [
                [
                    'type' => 'text',
                    'text' => '現在、受診ガイドが開始されていません。「受診を開始する」を押してください。',
                ],
            ]);
            return;
        }

        // アクションデータと対応するラベルのマッピング
        $actionLabels = [
            'start_consultation' => '受診を開始する',
            'interrupt_consultation' => '受診を中断する',
            'call_no_response' => '呼びかけても反応がない',
            'other_situation' => 'それ以外',
        ];

        // アクションに対応するラベルを取得
        $actionLabel = $actionLabels[$data] ?? '未知のアクション';

        switch ($data) {
            case 'action=start_consultation':
                // 「受診を開始する」アクションの処理
                $user->update(['state' => 'started_consultation']);

                $this->replyMessage($event['replyToken'], [
                    [
                        'type' => 'template',
                        'altText' => '救急受診ガイド',
                        'template' => [
                            'type' => 'buttons',
                            'title' => '救急受診ガイド',
                            'text' => '以下の選択肢から選んでください。',
                            'actions' => [
                                [
                                    'type' => 'postback',
                                    'label' => '呼びかけても反応がない',
                                    'data' => 'action=call_no_response',
                                ],
                                [
                                    'type' => 'postback',
                                    'label' => 'それ以外',
                                    'data' => 'action=other_situation',
                                ],
                            ],
                        ],
                    ],
                ]);

                // リッチメニューを「受診を中断する」に切り替え
                $this->linkRichMenu($user->user_id, 'interrupt_consultation');

                // ユーザーが押したボタンのラベルをメッセージとして表示
                $this->replyMessage($event['replyToken'], [
                    [
                        'type' => 'text',
                        'text' => "あなたは「{$actionLabel}」を選択しました。",
                    ],
                ]);

                break;

            case 'action=interrupt_consultation':
                // 「受診を中断する」アクションの処理
                $user->update(['state' => 'idle']);

                $this->replyMessage($event['replyToken'], [
                    [
                        'type' => 'text',
                        'text' => '受診を中断しました。',
                    ],
                ]);

                // リッチメニューを「受診を開始する」に戻す
                $this->linkRichMenu($user->user_id, 'start_consultation');

                // ユーザーが押したボタンのラベルをメッセージとして表示
                $this->replyMessage($event['replyToken'], [
                    [
                        'type' => 'text',
                        'text' => "あなたは「{$actionLabel}」を選択しました。",
                    ],
                ]);

                break;

            case 'action=call_no_response':
                // 「119を呼びましょう」という返信
                $this->replyMessage($event['replyToken'], [
                    [
                        'type' => 'text',
                        'text' => '119を呼びましょう。',
                    ],
                ]);

                // リッチメニューを「受診を開始する」に戻す
                $this->linkRichMenu($user->user_id, 'start_consultation');
                $user->update(['state' => 'idle']);

                // ユーザーが押したボタンのラベルをメッセージとして表示
                $this->replyMessage($event['replyToken'], [
                    [
                        'type' => 'text',
                        'text' => "あなたは「{$actionLabel}」を選択しました。",
                    ],
                ]);

                break;

            case 'action=other_situation':
                // 「様子を見ましょう」という返信
                $this->replyMessage($event['replyToken'], [
                    [
                        'type' => 'text',
                        'text' => '様子を見ましょう。',
                    ],
                ]);

                // リッチメニューを「受診を開始する」に戻す
                $this->linkRichMenu($user->user_id, 'start_consultation');
                $user->update(['state' => 'idle']);

                // ユーザーが押したボタンのラベルをメッセージとして表示
                $this->replyMessage($event['replyToken'], [
                    [
                        'type' => 'text',
                        'text' => "あなたは「{$actionLabel}」を選択しました。",
                    ],
                ]);

                break;

            default:
                Log::info("Unhandled postback data: {$data}");
                // 必要に応じて、ユーザーにエラーメッセージを送信
                $this->replyMessage($event['replyToken'], [
                    [
                        'type' => 'text',
                        'text' => '不明な操作が行われました。',
                    ],
                ]);
        }
    }

    /**
     * リッチメニューをユーザーにリンクする
     */
    private function linkRichMenu($userId, $menuName)
    {
        $richMenu = RichMenu::where('name', $menuName)->first();

        if (!$richMenu || !$richMenu->rich_menu_id) {
            Log::error("Rich menu '{$menuName}' not found or not registered.");
            return;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->channelAccessToken,
        ])->post("https://api.line.me/v2/bot/user/{$userId}/richmenu/{$richMenu->rich_menu_id}");

        if ($response->successful()) {
            Log::info("Rich menu '{$menuName}' linked to user {$userId}.");
        } else {
            Log::error("Failed to link rich menu '{$menuName}' to user {$userId}. Response: " . $response->body());
        }
    }
}
