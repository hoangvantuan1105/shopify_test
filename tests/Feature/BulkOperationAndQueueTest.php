<?php

namespace Tests\Feature;

use App\Jobs\ProcessProductWebhookJob;
use App\Jobs\SyncProductsJob;
use App\Models\Product;
use App\Models\Shop;
use App\Services\EmbeddingService;
use App\Services\ShopifyBulkOperationService;
use App\Services\ShopifyProductService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BulkOperationAndQueueTest extends TestCase
{
    protected Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::updateOrCreate(
            ['shop_domain' => 'test-bulk.myshopify.com'],
            ['access_token' => 'shpat_bulk_test_token']
        );
    }

    public function test_sync_products_job_executes_successfully(): void
    {
        $mockProductService = $this->createMock(ShopifyProductService::class);
        $mockProductService->expects($this->once())
            ->method('syncProducts')
            ->with($this->equalTo($this->shop))
            ->willReturn([
                'total_synced'  => 10,
                'total_created' => 8,
                'total_updated' => 2,
            ]);

        $job = new SyncProductsJob($this->shop);
        $job->handle($mockProductService);
    }

    public function test_product_controller_sync_dispatches_sync_job_when_async(): void
    {
        Queue::fake();

        $response = $this->post('/products/sync', [
            'shop'  => $this->shop->shop_domain,
            'async' => '1',
        ]);

        $response->assertRedirect();
        Queue::assertPushed(SyncProductsJob::class, function ($job) {
            return $job->shop->shop_domain === $this->shop->shop_domain;
        });
    }

    public function test_process_product_webhook_job_creates_product_and_embeds(): void
    {
        $mockEmbedding = $this->createMock(EmbeddingService::class);
        $mockEmbedding->expects($this->once())
            ->method('embedProduct')
            ->willReturn(true);

        $payload = [
            'id'         => 777123,
            'title'      => 'Async Webhook Product',
            'body_html'  => '<p>Handled by queue</p>',
            'vendor'     => 'AsyncVendor',
            'product_type' => 'Gadgets',
            'variants'   => [['id' => 1, 'price' => '99.00']],
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $job = new ProcessProductWebhookJob('products/create', $payload);
        $job->handle($mockEmbedding);

        $this->assertDatabaseHas('products', [
            'shopify_product_id' => 777123,
            'title'              => 'Async Webhook Product',
            'vendor'             => 'AsyncVendor',
        ]);
    }

    public function test_bulk_operation_start_sends_correct_graphql_mutation(): void
    {
        Http::fake([
            'https://test-bulk.myshopify.com/admin/api/*/graphql.json' => Http::response([
                'data' => [
                    'bulkOperationRunQuery' => [
                        'bulkOperation' => [
                            'id'     => 'gid://shopify/BulkOperation/123456789',
                            'status' => 'CREATED',
                        ],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $bulkService = app(ShopifyBulkOperationService::class);
        $result = $bulkService->startBulkOperation($this->shop);

        $this->assertEquals('gid://shopify/BulkOperation/123456789', $result['id']);
        $this->assertEquals('CREATED', $result['status']);
    }

    public function test_bulk_operation_check_status(): void
    {
        Http::fake([
            'https://test-bulk.myshopify.com/admin/api/*/graphql.json' => Http::response([
                'data' => [
                    'currentBulkOperation' => [
                        'id'          => 'gid://shopify/BulkOperation/123456789',
                        'status'      => 'COMPLETED',
                        'errorCode'   => null,
                        'objectCount' => '50000',
                        'url'         => 'https://storage.googleapis.com/shopify-bulk/output.jsonl',
                    ],
                ],
            ], 200),
        ]);

        $bulkService = app(ShopifyBulkOperationService::class);
        $result = $bulkService->checkStatus($this->shop);

        $this->assertEquals('COMPLETED', $result['status']);
        $this->assertEquals('50000', $result['objectCount']);
        $this->assertEquals('https://storage.googleapis.com/shopify-bulk/output.jsonl', $result['url']);
    }

    public function test_stream_and_process_jsonl_reads_line_by_line(): void
    {
        // Tạo file JSONL mẫu tạm thời
        $tempFile = tempnam(sys_get_temp_dir(), 'bulk_jsonl_');
        $sampleData = [
            ['id' => 'gid://shopify/Product/1', 'title' => 'Product 1', 'vendor' => 'V1'],
            ['id' => 'gid://shopify/Product/2', 'title' => 'Product 2', 'vendor' => 'V2'],
            ['id' => 'gid://shopify/Product/3', 'title' => 'Product 3', 'vendor' => 'V3'],
        ];

        $content = '';
        foreach ($sampleData as $item) {
            $content .= json_encode($item) . "\n";
        }
        file_put_contents($tempFile, $content);

        $bulkService = app(ShopifyBulkOperationService::class);
        $collectedTitles = [];

        $count = $bulkService->streamAndProcessJsonl($tempFile, function ($record) use (&$collectedTitles) {
            $collectedTitles[] = $record['title'];
        });

        unlink($tempFile);

        $this->assertEquals(3, $count);
        $this->assertEquals(['Product 1', 'Product 2', 'Product 3'], $collectedTitles);
    }
}
