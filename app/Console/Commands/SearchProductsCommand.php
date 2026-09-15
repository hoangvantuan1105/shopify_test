<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;
use Pgvector\Laravel\Vector;

class SearchProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:search {query : Từ khóa hoặc câu truy vấn ngữ nghĩa}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tìm kiếm sản phẩm theo ngữ nghĩa (Semantic Vector Search) trả về Top 5';

    /**
     * Execute the console command.
     */
    public function handle(EmbeddingService $service)
    {
        $query = $this->argument('query');
        $this->info(" Đang tìm kiếm theo ngữ nghĩa cho: '{$query}'...");

        $startTime = microtime(true);

        try {
            $queryVector = $service->embed($query);
            $vectorString = (string) new Vector($queryVector);

            $results = Product::whereNull('deleted_at')
                ->whereNotNull('embedding')
                ->select([
                    'title',
                    'vendor',
                    'price',
                    'product_type',
                ])
                ->selectRaw("round(((1 - (embedding <=> ?::vector)) * 100)::numeric, 1) AS similarity_score", [$vectorString])
                ->orderByRaw("embedding <=> ?::vector ASC", [$vectorString])
                ->limit(5)
                ->get();

            $duration = round((microtime(true) - $startTime) * 1000, 1);

            if ($results->isEmpty()) {
                $this->warn("⚠️  Không tìm thấy sản phẩm nào có vector trong database.");
                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($results as $index => $item) {
                $rows[] = [
                    'Top ' . ($index + 1),
                    $item->title,
                    $item->vendor,
                    $item->price ? '$' . number_format($item->price, 2) : '—',
                    $item->similarity_score . '%',
                ];
            }

            $this->table(['Thứ hạng', 'Tên sản phẩm', 'Vendor', 'Giá', 'Độ tương đồng (Similarity)'], $rows);
            $this->info("⚡ Thời gian xử lý: {$duration} ms (Top 5 sản phẩm phù hợp nhất)");

        } catch (\Exception $e) {
            $this->error(' Lỗi: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
