<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pgvector\Laravel\Vector;

class EmbeddingService
{
    protected string $provider;
    protected string $baseUrl;
    protected string $model;

    public function __construct()
    {
        $this->provider = config('embedding.default', 'ollama');

        if ($this->provider === 'ollama') {
            $this->baseUrl = config('embedding.ollama.base_url', 'http://ollama:11434');
            $this->model   = config('embedding.ollama.model', 'nomic-embed-text');
        } else {
            $this->baseUrl = 'https://api.openai.com/v1';
            $this->model   = config('embedding.openai.model', 'text-embedding-3-small');
        }
    }

    /**
     * Tạo văn bản đại diện ngữ nghĩa cho sản phẩm.
     */
    public function generateRepresentationText(Product $product): string
    {
        $parts = [];
        $parts[] = "Title: " . ($product->title ?: 'N/A');
        
        if ($product->description) {
            $parts[] = "Description: " . $product->description;
        }

        if ($product->vendor) {
            $parts[] = "Vendor: " . $product->vendor;
        }

        if ($product->product_type) {
            $parts[] = "Category: " . $product->product_type;
        }

        if ($product->tags) {
            $parts[] = "Tags: " . $product->tags;
        }

        if ($product->price !== null) {
            $parts[] = "Price: $" . number_format($product->price, 2);
        }

        return implode(". ", $parts);
    }

    /**
     * Gọi API Embedding để chuyển một đoạn văn bản thành Vector (mảng số thực).
     *
     * @return array<float>
     */
    public function embed(string $text): array
    {
        if ($this->provider === 'ollama') {
            $response = Http::timeout(60)->post("{$this->baseUrl}/api/embeddings", [
                'model'  => $this->model,
                'prompt' => $text,
            ]);

            if (!$response->successful()) {
                Log::error("Lỗi khi gọi Ollama Embedding API: " . $response->body());
                throw new \Exception("Ollama API Error ({$response->status()}): " . $response->body());
            }

            $embedding = $response->json('embedding');
            if (empty($embedding) || !is_array($embedding)) {
                throw new \Exception("Ollama không trả về vector embedding hợp lệ.");
            }

            return $embedding;
        }

        // OpenAI Driver Fallback
        $apiKey = config('embedding.openai.api_key');
        if (empty($apiKey)) {
            throw new \Exception("Chưa cấu hình OPENAI_API_KEY trong .env");
        }

        $response = Http::withToken($apiKey)->timeout(30)->post("{$this->baseUrl}/embeddings", [
            'model' => $this->model,
            'input' => $text,
        ]);

        if (!$response->successful()) {
            throw new \Exception("OpenAI API Error ({$response->status()}): " . $response->body());
        }

        return $response->json('data.0.embedding');
    }

    /**
     * Vector hóa một sản phẩm cụ thể.
     * Tối ưu Mục 6: Nếu dữ liệu không thay đổi (data_hash trùng), bỏ qua không gọi API.
     */
    public function embedProduct(Product $product, bool $force = false): bool
    {
        $text = $this->generateRepresentationText($product);
        $newHash = md5($text);

        // Kiểm tra xem dữ liệu có thay đổi không
        if (!$force && $product->embedding !== null && $product->data_hash === $newHash) {
            Log::info("Bỏ qua sản phẩm #{$product->id} ('{$product->title}') vì data_hash không đổi.");
            return false;
        }

        Log::info("Đang tạo embedding cho sản phẩm #{$product->id} ('{$product->title}') qua {$this->provider}...");

        $vector = $this->embed($text);

        $product->embedding = new Vector($vector);
        $product->data_hash = $newHash;
        $product->save();

        return true;
    }

    /**
     * Vector hóa toàn bộ sản phẩm trong Database.
     */
    public function embedAllProducts(bool $force = false): array
    {
        $products = Product::whereNull('deleted_at')->get();

        $processed = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $updated = $this->embedProduct($product, $force);
            if ($updated) {
                $processed++;
            } else {
                $skipped++;
            }
        }

        return [
            'total'     => $products->count(),
            'processed' => $processed,
            'skipped'   => $skipped,
        ];
    }
}
