<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\ShopifyProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Số lần thử lại tối đa khi job gặp lỗi tạm thời.
     */
    public int $tries = 3;

    /**
     * Thời gian chờ giữa các lần thử lại (10s, 30s, 60s).
     */
    public array $backoff = [10, 30, 60];

    /**
     * Timeout tối đa cho job (10 phút).
     */
    public int $timeout = 600;

    public function __construct(public Shop $shop)
    {
    }

    /**
     * Thực thi đồng bộ sản phẩm từ Shopify Admin API về Database.
     */
    public function handle(ShopifyProductService $service): void
    {
        Log::info("SyncProductsJob: Bắt đầu xử lý đồng bộ background cho shop {$this->shop->shop_domain}");

        $result = $service->syncProducts($this->shop);

        Log::info("SyncProductsJob: Đồng bộ hoàn tất cho shop {$this->shop->shop_domain}", [
            'total_synced'  => $result['total_synced'] ?? 0,
            'total_created' => $result['total_created'] ?? 0,
            'total_updated' => $result['total_updated'] ?? 0,
        ]);
    }

    /**
     * Xử lý khi Job thất bại sau tất cả số lần thử lại.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SyncProductsJob thất bại sau {$this->tries} lần thử cho shop {$this->shop->shop_domain}: " . $exception->getMessage(), [
            'exception' => $exception->getTraceAsString(),
        ]);
    }
}
