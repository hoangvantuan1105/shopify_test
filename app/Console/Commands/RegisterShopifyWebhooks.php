<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RegisterShopifyWebhooks extends Command
{
    protected $signature = 'shopify:register-webhooks {--shop= : Domain của shop cần đăng ký webhook}';
    protected $description = 'Đăng ký các Webhook tự động với Shopify Admin API (products/create, products/update, products/delete)';

    public function handle(): int
    {
        $shopDomain = $this->option('shop');
        $shops = $shopDomain ? Shop::where('shop_domain', $shopDomain)->get() : Shop::all();

        if ($shops->isEmpty()) {
            $this->error('Không tìm thấy store nào trong cơ sở dữ liệu.');
            return Command::FAILURE;
        }

        $appUrl = rtrim(config('shopify.app_url'), '/');
        $apiVersion = config('shopify.api_version', '2024-04');

        $topics = [
            'products/create' => "{$appUrl}/webhooks/products/create",
            'products/update' => "{$appUrl}/webhooks/products/update",
            'products/delete' => "{$appUrl}/webhooks/products/delete",
        ];

        foreach ($shops as $shop) {
            $this->info("Đang kiểm tra và đăng ký webhook cho: {$shop->shop_domain}");
            $this->info("App URL đích: {$appUrl}");

            $baseUrl = "https://{$shop->shop_domain}/admin/api/{$apiVersion}";

            // 1. Lấy danh sách webhook hiện có trên Shopify
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $shop->access_token,
                'Content-Type'           => 'application/json',
            ])->get("{$baseUrl}/webhooks.json");

            if (!$response->successful()) {
                $this->error("Không thể lấy danh sách webhook từ Shopify: " . $response->body());
                continue;
            }

            $existingWebhooks = $response->json('webhooks') ?? [];
            $existingMap = [];
            foreach ($existingWebhooks as $wh) {
                $existingMap[$wh['topic']] = $wh;
            }

            // 2. Đăng ký hoặc cập nhật từng topic
            foreach ($topics as $topic => $address) {
                if (isset($existingMap[$topic])) {
                    $existingWh = $existingMap[$topic];
                    if ($existingWh['address'] === $address) {
                        $this->line("   [ĐÃ CÓ] Topic '{$topic}' đã trỏ đến: {$address}");
                        continue;
                    }

                    // Nếu địa chỉ URL thay đổi (ví dụ đổi Ngrok URL mới), cập nhật lại
                    $updateRes = Http::withHeaders([
                        'X-Shopify-Access-Token' => $shop->access_token,
                        'Content-Type'           => 'application/json',
                    ])->put("{$baseUrl}/webhooks/{$existingWh['id']}.json", [
                        'webhook' => [
                            'id'      => $existingWh['id'],
                            'address' => $address,
                        ],
                    ]);

                    if ($updateRes->successful()) {
                        $this->info("  🔄 [CẬP NHẬT] Topic '{$topic}' sang địa chỉ mới: {$address}");
                    } else {
                        $this->error("  ❌ [LỖI] Không thể cập nhật topic '{$topic}': " . $updateRes->body());
                    }
                } else {
                    // Tạo mới webhook subscription
                    $createRes = Http::withHeaders([
                        'X-Shopify-Access-Token' => $shop->access_token,
                        'Content-Type'           => 'application/json',
                    ])->post("{$baseUrl}/webhooks.json", [
                        'webhook' => [
                            'topic'   => $topic,
                            'address' => $address,
                            'format'  => 'json',
                        ],
                    ]);

                    if ($createRes->successful()) {
                        $this->info("   [THÀNH CÔNG] Đã đăng ký topic '{$topic}' -> {$address}");
                    } else {
                        $this->error("  ❌ [LỖI] Không thể đăng ký topic '{$topic}': " . $createRes->body());
                    }
                }
            }
        }

        $this->info(" Hoàn tất kiểm tra Webhook!");
        return Command::SUCCESS;
    }
}
