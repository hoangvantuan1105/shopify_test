<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\EmbeddingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProductWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Số lần thử lại tối đa khi xử lý webhook background.
     */
    public int $tries = 3;

    /**
     * Thời gian chờ giữa các lần thử (5s, 15s, 30s).
     */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public string $topic,
        public array $payload
    ) {
    }

    /**
     * Xử lý webhook trong background queue.
     */
    public function handle(EmbeddingService $embeddingService): void
    {
        $id = $this->payload['id'] ?? null;
        Log::info("ProcessProductWebhookJob: Bắt đầu xử lý webhook async [{$this->topic}] cho product #{$id}");

        switch ($this->topic) {
            case 'products/create':
                $this->handleCreate($this->payload, $embeddingService);
                break;

            case 'products/update':
                $this->handleUpdate($this->payload, $embeddingService);
                break;

            case 'products/delete':
                $this->handleDelete($this->payload);
                break;

            default:
                Log::warning("ProcessProductWebhookJob: Bỏ qua topic không được hỗ trợ [{$this->topic}]");
        }
    }

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

        $dataRepresentation = "{$title}|{$description}|{$vendor}|{$productType}|{$tags}|{$price}";
        $dataHash = md5($dataRepresentation);

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

    protected function handleCreate(array $payload, EmbeddingService $embeddingService): void
    {
        $data = $this->extractProductData($payload);
        if (!$data['shopify_product_id']) return;

        $product = Product::updateOrCreate(
            ['shopify_product_id' => $data['shopify_product_id']],
            $data
        );

        $embeddingService->embedProduct($product);
        Log::info("ProcessProductWebhookJob: Đã tạo và vector hóa sản phẩm #{$product->shopify_product_id}");
    }

    protected function handleUpdate(array $payload, EmbeddingService $embeddingService): void
    {
        $data = $this->extractProductData($payload);
        if (!$data['shopify_product_id']) return;

        $product = Product::where('shopify_product_id', $data['shopify_product_id'])->first();

        if ($product) {
            $oldHash = $product->data_hash;
            $product->update($data);

            if ($oldHash !== $data['data_hash'] || $product->embedding === null) {
                Log::info("ProcessProductWebhookJob: Nội dung thay đổi, đang tái tạo vector cho #{$product->id}");
                $embeddingService->embedProduct($product, force: true);
            } else {
                Log::info("ProcessProductWebhookJob: Data hash trùng khớp ({$oldHash}), bỏ qua tái tạo embedding.");
            }
        } else {
            $product = Product::create($data);
            $embeddingService->embedProduct($product);
        }
    }

    protected function handleDelete(array $payload): void
    {
        $shopifyId = $payload['id'] ?? null;
        if (!$shopifyId) return;

        $product = Product::where('shopify_product_id', $shopifyId)->first();
        if ($product) {
            $product->deleted_at = now();
            $product->embedding = null;
            $product->save();
            Log::info("ProcessProductWebhookJob: Đã đánh dấu xóa mềm và hủy vector cho sản phẩm #{$shopifyId}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessProductWebhookJob thất bại sau các lần thử: " . $exception->getMessage(), [
            'topic'   => $this->topic,
            'payload' => $this->payload,
        ]);
    }
}
