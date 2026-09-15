<?php

namespace App\Console\Commands;

use App\Services\EmbeddingService;
use Illuminate\Console\Command;

class EmbedProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:embed {--force : Bắt buộc tạo lại vector kể cả khi data_hash không đổi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chuyển hóa dữ liệu sản phẩm thành Vector Embedding lưu vào PostgreSQL';

    /**
     * Execute the console command.
     */
    public function handle(EmbeddingService $service)
    {
        $this->info('🚀 Bắt đầu quá trình Vector hóa sản phẩm...');
        $force = $this->option('force');

        try {
            $result = $service->embedAllProducts($force);

            $this->table(
                ['Tổng số sản phẩm', 'Đã xử lý (Tạo vector mới)', 'Bỏ qua (Hash không đổi)'],
                [[$result['total'], $result['processed'], $result['skipped']]]
            );

            $this->info(' Hoàn thành Vector hóa sản phẩm thành công!');
        } catch (\Exception $e) {
            $this->error(' Lỗi: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
