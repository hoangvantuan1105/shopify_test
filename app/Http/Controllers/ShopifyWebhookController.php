<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\EmbeddingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    /**
     * Trích xuất thông tin chuẩn từ Shopify Product payload.
     */
    protected function extractProductData(array $payload): array
    {
        $shopifyId   = $payload['id'] ?? null;
        $title       = $payload['title'] ?? '';
        $rawDesc     = $payload['body_html'] ?? '';
        $description = trim(strip_tags($rawDesc));
        $vendor      = $payload['vendor'] ?? null;
        $productType = $payload['product_type'] ?? null;
        $tags        = is_array($payload['tags'] ?? null) ? implode(', ', $payload['tags']) : ($payload['tags'] ?? null);
        $variants    = $payload['variants'] ?? [];

        $price = null;
        if (!empty($variants) && isset($variants[0]['price'])) {
            $price = (float) $variants[0]['price'];
        }

        $imageUrl = $payload['image']['src'] ?? ($payload['images'][0]['src'] ?? null);
        $createdAt = isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : null;
        $updatedAt = isset($payload['updated_at']) ? Carbon::parse($payload['updated_at']) : null;

        // Tính toán hash chuẩn hóa theo văn bản đại diện ngữ nghĩa
        $dataHash = Product::calculateDataHash($title, $description, $vendor, $productType, $tags, $price);

        return [
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
            'deleted_at'         => null,
            'data_hash'          => $dataHash,
        ];
    }

    /**
     * Webhook xử lý Product Created: lưu Product, tạo embedding và lưu vector.
     */
    public function handleProductCreated(Request $request, EmbeddingService $embeddingService): JsonResponse
    {
        $payload = $request->all();
        $data = $this->extractProductData($payload);

        if (!$data['shopify_product_id']) {
            return response()->json(['error' => 'Missing product id in payload'], 400);
        }

        Log::info("Webhook Product Created: Đang xử lý sản phẩm #{$data['shopify_product_id']} ('{$data['title']}')");

        $product = Product::updateOrCreate(
            ['shopify_product_id' => $data['shopify_product_id']],
            $data
        );

        // Sinh vector embedding và lưu vào database
        try {
            $embeddingService->embedProduct($product);
        } catch (\Exception $e) {
            Log::error("Lỗi khi sinh vector cho sản phẩm mới qua Webhook: " . $e->getMessage());
        }

        return response()->json([
            'status'     => 'success',
            'message'    => 'Product created and vectorized successfully',
            'product_id' => $product->id,
        ], 200);
    }

    /**
     * Webhook xử lý Product Updated: cập nhật Product, tạo lại embedding khi cần và cập nhật vector.
     */
    public function handleProductUpdated(Request $request, EmbeddingService $embeddingService): JsonResponse
    {
        $payload = $request->all();
        $data = $this->extractProductData($payload);

        if (!$data['shopify_product_id']) {
            return response()->json(['error' => 'Missing product id in payload'], 400);
        }

        $product = Product::where('shopify_product_id', $data['shopify_product_id'])->first();

        if ($product) {
            $oldHash = $product->data_hash;

            $product->update($data);

            // Kiểm tra xem data_hash có thay đổi hoặc vector chưa có hay không
            if ($oldHash !== $data['data_hash'] || $product->embedding === null) {
                Log::info("Webhook Product Updated: Nội dung thay đổi, đang tạo lại vector cho #{$product->id}...");
                try {
                    $embeddingService->embedProduct($product, force: true);
                } catch (\Exception $e) {
                    Log::error("Lỗi khi cập nhật vector qua Webhook: " . $e->getMessage());
                }
                $message = 'Product updated and vector re-embedded';
            } else {
                Log::info("Webhook Product Updated: Bỏ qua tạo vector vì data_hash không đổi (chỉ đổi tồn kho/giá trị không ảnh hưởng ngữ nghĩa).");
                $message = 'Product updated (embedding skipped: unchanged data_hash)';
            }
        } else {
  
            $product = Product::create($data);
            try {
                $embeddingService->embedProduct($product);
            } catch (\Exception $e) {
                Log::error("Lỗi khi sinh vector cho sản phẩm mới qua Webhook: " . $e->getMessage());
            }
            $message = 'Product created and vectorized from update webhook';
        }

        return response()->json([
            'status'     => 'success',
            'message'    => $message,
            'product_id' => $product->id,
        ], 200);
    }

    /**
     */
    public function handleProductDeleted(Request $request): JsonResponse
    {
        $shopifyId = $request->input('id');

        if (!$shopifyId) {
            return response()->json(['error' => 'Missing product id in payload'], 400);
        }

        Log::info("Webhook Product Deleted: Nhận yêu cầu xóa sản phẩm #{$shopifyId}");

        $product = Product::where('shopify_product_id', $shopifyId)->first();

        if ($product) {
            $product->update([
                'deleted_at' => now(),
                'embedding'  => null,
            ]);
            Log::info("Webhook Product Deleted: Đã soft-delete và loại bỏ vector cho sản phẩm #{$shopifyId}");
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Product marked as deleted and vector cleared',
        ], 200);
    }
}
