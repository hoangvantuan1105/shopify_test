<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Services\EmbeddingService;
use App\Services\ShopifyProductService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RateLimitAndRetryTest extends TestCase
{
    protected Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::updateOrCreate(
            ['shop_domain' => 'test-ratelimit.myshopify.com'],
            ['access_token' => 'shpat_ratelimit_token_123']
        );
    }

    public function test_shopify_product_service_retries_on_transient_failure(): void
    {
        Http::fake([
            'https://test-ratelimit.myshopify.com/admin/api/*/products.json*' => Http::sequence()
                ->push(['error' => 'Temporary Server Error'], 500)
                ->push([
                    'products' => [
                        [
                            'id'         => 991,
                            'title'      => 'Retried Product',
                            'body_html'  => '<p>Success on retry</p>',
                            'vendor'     => 'Brand',
                            'product_type' => 'Apparel',
                            'variants'   => [['id' => 1, 'price' => '25.00']],
                            'created_at' => now()->toIso8601String(),
                            'updated_at' => now()->toIso8601String(),
                        ]
                    ]
                ], 200, ['X-Shopify-Shop-Api-Call-Limit' => '10/40']),
            'http://ollama:11434/*' => Http::response(['embedding' => array_fill(0, 768, 0.01)], 200),
        ]);

        $service = app(ShopifyProductService::class);
        $result = $service->syncProducts($this->shop);

        $this->assertEquals(1, $result['total_synced']);
        $this->assertDatabaseHas('products', [
            'shopify_product_id' => 991,
            'title'              => 'Retried Product',
        ]);
    }

    public function test_shopify_rate_limit_header_handling(): void
    {
        // Giả lập Shopify trả về Call Limit đạt ngưỡng cao 38/40
        Http::fake([
            'https://test-ratelimit.myshopify.com/admin/api/*/products.json*' => Http::response([
                'products' => [
                    [
                        'id'         => 992,
                        'title'      => 'Throttled Product',
                        'body_html'  => '<p>Handled rate limit</p>',
                        'vendor'     => 'Brand',
                        'product_type' => 'Apparel',
                        'variants'   => [['id' => 2, 'price' => '30.00']],
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ]
                ]
            ], 200, ['X-Shopify-Shop-Api-Call-Limit' => '38/40']),
            'http://ollama:11434/*' => Http::response(['embedding' => array_fill(0, 768, 0.01)], 200),
        ]);

        $service = app(ShopifyProductService::class);
        $result = $service->syncProducts($this->shop);

        $this->assertEquals(1, $result['total_synced']);
        $this->assertDatabaseHas('products', [
            'shopify_product_id' => 992,
            'title'              => 'Throttled Product',
        ]);
    }

    public function test_embedding_service_retries_on_ollama_failure(): void
    {
        Http::fake([
            'http://ollama:11434/api/embeddings' => Http::sequence()
                ->push(['error' => 'Ollama busy'], 503)
                ->push(['embedding' => array_fill(0, 768, 0.123)], 200),
        ]);

        $service = app(EmbeddingService::class);
        $embedding = $service->embed('Test product retry text');

        $this->assertNotNull($embedding);
        $this->assertCount(768, $embedding);
        $this->assertEquals(0.123, $embedding[0]);
    }
}
