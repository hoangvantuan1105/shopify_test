<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\EmbeddingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopifyWebhookTest extends TestCase
{
    protected string $secret = 'test_webhook_secret_key_123';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('shopify.client_secret', $this->secret);

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shopify_product_id')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('vendor')->nullable();
                $table->string('product_type')->nullable();
                $table->text('tags')->nullable();
                $table->text('variants')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->string('image_url')->nullable();
                $table->timestamp('shopify_created_at')->nullable();
                $table->timestamp('shopify_updated_at')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->string('data_hash')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Tạo headers giả lập của Shopify Webhook kèm chữ ký HMAC hợp lệ.
     */
    protected function createWebhookHeaders(array $payload, ?string $webhookId = null): array
    {
        $content = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $content, $this->secret, true));

        $headers = [
            'X-Shopify-Hmac-Sha256'   => $hmac,
            'X-Shopify-Shop-Domain'   => 'test-store.myshopify.com',
            'Content-Type'            => 'application/json',
        ];

        if ($webhookId) {
            $headers['X-Shopify-Webhook-Id'] = $webhookId;
        }

        return $headers;
    }

    public function test_webhook_rejected_without_hmac_header(): void
    {
        $response = $this->postJson('/webhooks/products/create', ['id' => 999]);

        $response->assertStatus(401);
    }

    public function test_webhook_rejected_with_invalid_hmac(): void
    {
        $response = $this->postJson('/webhooks/products/create', ['id' => 999], [
            'X-Shopify-Hmac-Sha256' => 'invalid_signature_base64',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_creates_product_and_generates_embedding(): void
    {
        // Mock EmbeddingService
        $mockEmbedding = $this->createMock(EmbeddingService::class);
        $dummyVector = array_fill(0, 768, 0.05);
        $mockEmbedding->method('embed')->willReturn($dummyVector);
        $mockEmbedding->expects($this->once())
            ->method('embedProduct')
            ->willReturn(true);

        $this->app->instance(EmbeddingService::class, $mockEmbedding);

        $payload = [
            'id'         => 888101,
            'title'      => 'Nike Air Jordan 1 High',
            'body_html'  => '<p>Classic basketball sneakers</p>',
            'vendor'     => 'Nike',
            'product_type' => 'Shoes',
            'tags'       => 'shoes, sport, basketball',
            'variants'   => [['id' => 1, 'price' => '179.99']],
            'image'      => ['src' => 'https://cdn.shopify.com/jordan1.jpg'],
            'created_at' => '2026-05-01T10:00:00Z',
            'updated_at' => '2026-05-01T10:00:00Z',
        ];

        $headers = $this->createWebhookHeaders($payload);

        $response = $this->call('POST', '/webhooks/products/create', [], [], [], $this->transformHeadersToServerVars($headers), json_encode($payload));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('products', [
            'shopify_product_id' => 888101,
            'title'              => 'Nike Air Jordan 1 High',
            'vendor'             => 'Nike',
            'price'              => 179.99,
        ]);
    }

    public function test_webhook_updates_product_and_handles_data_hash(): void
    {
        // Khởi tạo sản phẩm trước trong DB (đã có vector và hash)
        $product = Product::create([
            'shopify_product_id' => 888102,
            'title'              => 'Adidas Ultraboost',
            'description'        => 'Running shoes',
            'vendor'             => 'Adidas',
            'product_type'       => 'Shoes',
            'tags'               => 'running',
            'price'              => 199.99,
            'data_hash'          => md5('Adidas Ultraboost|Running shoes|Adidas|Shoes|running|199.99'),
            'embedding'          => new \Pgvector\Laravel\Vector(array_fill(0, 768, 0.1)),
        ]);

        $mockEmbedding = $this->createMock(EmbeddingService::class);
        $this->app->instance(EmbeddingService::class, $mockEmbedding);

        // Trường hợp 1: Nội dung không đổi, chỉ đổi ngày cập nhật
        $payloadUnchanged = [
            'id'         => 888102,
            'title'      => 'Adidas Ultraboost',
            'body_html'  => '<p>Running shoes</p>',
            'vendor'     => 'Adidas',
            'product_type' => 'Shoes',
            'tags'       => 'running',
            'variants'   => [['id' => 1, 'price' => '199.99']],
            'updated_at' => '2026-05-02T12:00:00Z',
        ];

        $headers = $this->createWebhookHeaders($payloadUnchanged);
        $response = $this->call('POST', '/webhooks/products/update', [], [], [], $this->transformHeadersToServerVars($headers), json_encode($payloadUnchanged));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
        $response->assertSee('embedding skipped: unchanged data_hash');

        // Trường hợp 2: Tiêu đề thay đổi -> Phải gọi embedProduct
        $mockEmbedding->expects($this->once())
            ->method('embedProduct')
            ->willReturn(true);

        $payloadChanged = $payloadUnchanged;
        $payloadChanged['title'] = 'Adidas Ultraboost 22';

        $headers2 = $this->createWebhookHeaders($payloadChanged);
        $response2 = $this->call('POST', '/webhooks/products/update', [], [], [], $this->transformHeadersToServerVars($headers2), json_encode($payloadChanged));

        $response2->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'shopify_product_id' => 888102,
            'title'              => 'Adidas Ultraboost 22',
        ]);
    }

    public function test_webhook_deletes_product_and_clears_vector(): void
    {
        $product = Product::create([
            'shopify_product_id' => 888103,
            'title'              => 'Puma Suede Classic',
            'price'              => 89.99,
        ]);

        $payload = ['id' => 888103];
        $headers = $this->createWebhookHeaders($payload);

        $response = $this->call('POST', '/webhooks/products/delete', [], [], [], $this->transformHeadersToServerVars($headers), json_encode($payload));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $product->refresh();
        $this->assertNotNull($product->deleted_at);
        $this->assertNull($product->embedding);
    }

    public function test_duplicate_webhook_is_ignored_idempotently(): void
    {
        $webhookId = 'webhook_event_uuid_123456';
        $payload = [
            'id'    => 888104,
            'title' => 'Converse All Star',
            'price' => 55.00,
        ];

        $mockEmbedding = $this->createMock(EmbeddingService::class);
        $this->app->instance(EmbeddingService::class, $mockEmbedding);

        $headers = $this->createWebhookHeaders($payload, $webhookId);

        // Lần gửi thứ nhất: Được xử lý
        $res1 = $this->call('POST', '/webhooks/products/create', [], [], [], $this->transformHeadersToServerVars($headers), json_encode($payload));
        $res1->assertStatus(200);
        $res1->assertJson(['status' => 'success']);

        // Lần gửi thứ hai (cùng Webhook ID): Bỏ qua (Idempotency)
        $res2 = $this->call('POST', '/webhooks/products/create', [], [], [], $this->transformHeadersToServerVars($headers), json_encode($payload));
        $res2->assertStatus(200);
        $res2->assertJson(['status' => 'skipped']);
    }
}
