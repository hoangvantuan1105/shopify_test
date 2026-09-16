<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\ShopifyBulkOperationService;
use Illuminate\Console\Command;

class ShopifyBulkSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'shopify:bulk-sync {--shop= : Domain of the store} {--status : Check status of current bulk operation}';

    /**
     * The console command description.
     */
    protected $description = 'Trigger or check Shopify GraphQL Bulk Operations for large product catalogs (10,000 - 100,000+ products)';

    public function handle(ShopifyBulkOperationService $bulkService): int
    {
        $domain = $this->option('shop');
        $shop = $domain ? Shop::where('shop_domain', $domain)->first() : Shop::latest()->first();

        if (!$shop) {
            $this->error('Không tìm thấy store nào. Vui lòng kết nối OAuth trước.');
            return self::FAILURE;
        }

        $this->info("Đang xử lý Bulk Operation cho store: {$shop->shop_domain}");

        if ($this->option('status')) {
            $status = $bulkService->checkStatus($shop);
            $this->table(['Key', 'Value'], collect($status)->map(fn($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->toArray());
            return self::SUCCESS;
        }

        try {
            $result = $bulkService->startBulkOperation($shop);
            $this->info("Đã gửi yêu cầu Bulk Operation thành công!");
            $this->line("Operation ID: " . ($result['id'] ?? 'N/A'));
            $this->line("Status: " . ($result['status'] ?? 'N/A'));
            $this->comment("Sử dụng lệnh [php artisan shopify:bulk-sync --status] để kiểm tra tiến trình.");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Lỗi khởi chạy Bulk Operation: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
