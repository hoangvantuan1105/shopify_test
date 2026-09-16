<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyProductService
{
    /**
     * Đồng bộ toàn bộ sản phẩm từ Shopify về database, có xử lý phân trang (Link header pagination).
     */
    public function syncProducts(Shop $shop): array
    {
        $apiVersion = config('shopify.api_version', '2024-04');
        $baseUrl = "https://{$shop->shop_domain}/admin/api/{$apiVersion}";
        $url = "{$baseUrl}/products.json?limit=250";

        $totalSynced = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $syncedIds = [];
        $page = 1;

        do {
            Log::info("Đang đồng bộ trang {$page} từ shop {$shop->shop_domain}...");

            // 1. Retry mechanism: Tự động thử lại 3 lần nếu mạng lag hoặc dính lỗi 429/5xx
            $response = Http::retry(3, 500, function ($exception, $request) {
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    $status = $exception->response?->status();
                    return $status === 429 || $status >= 500;
                }
                return true;
            }, throw: false)->withHeaders([
                'X-Shopify-Access-Token' => $shop->access_token,
                'Content-Type'           => 'application/json',
            ])->timeout(30)->get($url);

            // 2. Rate-limit handling: Đọc header X-Shopify-Shop-Api-Call-Limit (ví dụ: 36/40)
            $callLimitHeader = $response->header('X-Shopify-Shop-Api-Call-Limit');
            if ($callLimitHeader && str_contains($callLimitHeader, '/')) {
                [$callsUsed, $callLimit] = explode('/', $callLimitHeader);
                $used = (int) trim($callsUsed);
                $limit = (int) trim($callLimit);
                Log::info("Shopify Rate Limit: {$used}/{$limit} requests đã dùng.");

                // Nếu số request đã dùng >= 35/40 (gần chạm trần), chủ động tạm dừng 0.5s để xô Leaky Bucket xả bớt
                if ($used >= ($limit - 5)) {
                    Log::warning("Gần chạm ngưỡng Rate-limit ({$used}/{$limit}), tạm dừng 500ms để làm rỗng xô Leaky Bucket...");
                    usleep(500000); // 500ms
                }
            }

            // 3. Xử lý khi bị lỗi 429 Too Many Requests: Đọc header Retry-After và sleep đúng số giây
            if ($response->status() === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 2);
                Log::warning("Bị Shopify phản hồi 429 Too Many Requests. Tạm nghỉ {$retryAfter}s theo header Retry-After...");
                sleep($retryAfter);

                // Thử lại request sau khi đã nghỉ đủ thời gian
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                    'Content-Type'           => 'application/json',
                ])->timeout(30)->get($url);
            }

            if (!$response->successful()) {
                Log::error("Lỗi khi gọi Shopify Products API: " . $response->body());
                throw new \Exception("Lỗi từ Shopify API ({$response->status()}): " . $response->body());
            }

            $products = $response->json('products') ?? [];

            foreach ($products as $p) {
                $shopifyId   = $p['id'];
                $syncedIds[] = $shopifyId;
                $title       = $p['title'] ?? '';
                // Làm sạch HTML tags trong description
                $rawDesc     = $p['body_html'] ?? '';
                $description = trim(strip_tags($rawDesc));
                $vendor      = $p['vendor'] ?? null;
                $productType = $p['product_type'] ?? null;
                $tags        = is_array($p['tags'] ?? null) ? implode(', ', $p['tags']) : ($p['tags'] ?? null);
                $variants    = $p['variants'] ?? [];

                // Lấy giá của variant đầu tiên
                $price = null;
                if (!empty($variants) && isset($variants[0]['price'])) {
                    $price = (float) $variants[0]['price'];
                }

                // Lấy ảnh đại diện
                $imageUrl = $p['image']['src'] ?? ($p['images'][0]['src'] ?? null);

                $createdAt = isset($p['created_at']) ? Carbon::parse($p['created_at']) : null;
                $updatedAt = isset($p['updated_at']) ? Carbon::parse($p['updated_at']) : null;

                // Tính toán hash dữ liệu đại diện để tối ưu tạo Vector sau này
                $dataRepresentation = "{$title}|{$description}|{$vendor}|{$productType}|{$tags}|{$price}";
                $dataHash = md5($dataRepresentation);

                $product = Product::where('shopify_product_id', $shopifyId)->first();

                if ($product) {
                    $product->update([
                        'title'              => $title,
                        'description'        => $description,
                        'vendor'             => $vendor,
                        'product_type'       => $productType,
                        'tags'               => $tags,
                        'variants'           => $variants,
                        'price'              => $price,
                        'image_url'          => $imageUrl,
                        'shopify_created_at' => $createdAt,
                        'shopify_updated_at' => $updatedAt,
                        'synced_at'          => now(),
                        'data_hash'          => $dataHash,
                    ]);
                    $totalUpdated++;
                } else {
                    Product::create([
                        'shopify_product_id' => $shopifyId,
                        'title'              => $title,
                        'description'        => $description,
                        'vendor'             => $vendor,
                        'product_type'       => $productType,
                        'tags'               => $tags,
                        'variants'           => $variants,
                        'price'              => $price,
                        'image_url'          => $imageUrl,
                        'shopify_created_at' => $createdAt,
                        'shopify_updated_at' => $updatedAt,
                        'synced_at'          => now(),
                        'data_hash'          => $dataHash,
                    ]);
                    $totalCreated++;
                }

                $totalSynced++;
            }

            // Xử lý phân trang thông qua Link header của Shopify
            $linkHeader = $response->header('Link');
            $nextUrl = null;

            if ($linkHeader) {
                // Link header format: <https://.../products.json?limit=250&page_info=xxx>; rel="next"
                $links = explode(',', $linkHeader);
                foreach ($links as $link) {
                    if (str_contains($link, 'rel="next"')) {
                        if (preg_match('/<([^>]+)>/', $link, $matches)) {
                            $nextUrl = $matches[1];
                            break;
                        }
                    }
                }
            }

            $url = $nextUrl;
            $page++;
        } while ($url !== null);

        // Đánh dấu deleted_at và loại bỏ vector đối với các sản phẩm đã bị xóa trên Shopify (Yêu cầu Mục 5)
        $totalDeleted = 0;
        if (!empty($syncedIds)) {
            $totalDeleted = Product::whereNotIn('shopify_product_id', $syncedIds)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'embedding'  => null,
                ]);
            if ($totalDeleted > 0) {
                Log::info("Đã đánh dấu xóa và loại bỏ vector của {$totalDeleted} sản phẩm không còn trên Shopify.");
            }
        }

        return [
            'total_synced'  => $totalSynced,
            'total_created' => $totalCreated,
            'total_updated' => $totalUpdated,
            'total_deleted' => $totalDeleted,
        ];
    }
}
