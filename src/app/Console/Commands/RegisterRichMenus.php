<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RichMenu;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class RegisterRichMenus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'line:register-rich-menus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register rich menus for LINE bot';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Registering rich menus...');

        $channelAccessToken = env('LINE_CHANNEL_ACCESS_TOKEN');

        if (!$channelAccessToken) {
            $this->error('LINE_CHANNEL_ACCESS_TOKEN is not set in .env');
            return 1;
        }

        // リッチメニューの定義
        $richMenus = [
            [
                'name' => 'start_consultation',
                'size' => [
                    'width' => 2500,
                    'height' => 1686,
                ],
                'selected' => false,
                'chatBarText' => '受診を開始する',
                'areas' => [
                    [
                        'bounds' => [
                            'x' => 0,
                            'y' => 0,
                            'width' => 2500,
                            'height' => 1686,
                        ],
                        'action' => [
                            'type' => 'postback',
                            'data' => 'action=start_consultation',
                            'label' => '受診を開始する',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'interrupt_consultation',
                'size' => [
                    'width' => 2500,
                    'height' => 1686,
                ],
                'selected' => false,
                'chatBarText' => '受診を中断する',
                'areas' => [
                    [
                        'bounds' => [
                            'x' => 0,
                            'y' => 0,
                            'width' => 2500,
                            'height' => 1686,
                        ],
                        'action' => [
                            'type' => 'postback',
                            'data' => 'action=interrupt_consultation',
                            'label' => '受診を中断する',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($richMenus as $menu) {
            // 既に登録されているか確認
            $existingMenu = RichMenu::where('name', $menu['name'])->first();

            if ($existingMenu && $existingMenu->rich_menu_id) {
                $this->info("Rich menu '{$menu['name']}' already exists with ID {$existingMenu->rich_menu_id}.");
                continue;
            }

            // リッチメニューの作成
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $channelAccessToken,
                'Content-Type' => 'application/json',
            ])->post('https://api.line.me/v2/bot/richmenu', $menu);

            if ($response->successful()) {
                $richMenuId = $response->json()['richMenuId'];
                $this->info("Rich menu '{$menu['name']}' created with ID {$richMenuId}.");

                // データベースに保存
                RichMenu::updateOrCreate(
                    ['name' => $menu['name']],
                    ['rich_menu_id' => $richMenuId]
                );

                // 画像のアップロード
                $imagePath = "rich_menu_images/{$menu['name']}.jpg"; // 画像はresources/rich_menu_imagesに配置
                if (!Storage::exists($imagePath)) {
                    $this->error("Image for rich menu '{$menu['name']}' not found at storage/app/{$imagePath}.");
                    continue;
                }

                $imageContent = Storage::get($imagePath);

                $imageResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $channelAccessToken,
                    'Content-Type' => 'image/png',
                ])->post("https://api.line.me/v2/bot/richmenu/{$richMenuId}/content", $imageContent);

                if ($imageResponse->successful()) {
                    $this->info("Image uploaded for rich menu '{$menu['name']}'.");
                } else {
                    $this->error("Failed to upload image for rich menu '{$menu['name']}'. Response: " . $imageResponse->body());
                }
            } else {
                $this->error("Failed to create rich menu '{$menu['name']}'. Response: " . $response->body());
            }
        }

        $this->info('Rich menu registration completed.');

        return 0;
    }
}