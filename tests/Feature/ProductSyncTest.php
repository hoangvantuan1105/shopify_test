<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('shops')) {
            Schema::create('shops', function (Blueprint $table) {
                $table->id();
                $table->string('shop_domain')->unique();
                $table->text('access_token');
                $table->string('scope')->nullable();
                $table->timestamps();
            });
        }

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

    public function test_can_view_products_page(): void
    {
        $response = $this->get('/products');

        $response->assertStatus(200);
        $response->assertSee('Quản lý Sản phẩm');
    }

    public function test_sync_products_with_pagination(): void
    {
        Product::whereIn('shopify_product_id', [101, 102])->delete();

        $shop = Shop::updateOrCreate(
            ['shop_domain'  => 'sync-test.myshopify.com'],
            [
                'access_token' => 'shpua_test_token_123',
                'scope'        => 'read_products',
            ]
        );

        $page1Products = [
            [
                'id'         => 101,
                'title'      => 'Snowboard Alpha',
                'body_html'  => '<p>Cool board</p>',
                'vendor'     => 'Vendor A',
                'product_type' => 'Snowboard',
                'tags'       => 'winter, sport',
                'variants'   => [['id' => 1, 'price' => '299.99']],
                'image'      => ['src' => 'https://cdn.shopify.com/board-alpha.jpg'],
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-02T00:00:00Z',
            ],
        ];

        $page2Products = [
            [
                'id'         => 102,
                'title'      => 'Snowboard Beta',
                'body_html'  => '<p>Another board</p>',
                'vendor'     => 'Vendor B',
                'product_type' => 'Snowboard',
                'tags'       => 'winter',
                'variants'   => [['id' => 2, 'price' => '399.99']],
                'image'      => ['src' => 'https://cdn.shopify.com/board-beta.jpg'],
                'created_at' => '2026-01-03T00:00:00Z',
                'updated_at' => '2026-01-04T00:00:00Z',
            ],
        ];

        Http::fake([
            'https://sync-test.myshopify.com/admin/api/2024-04/products.json?limit=250' => Http::response(
                ['products' => $page1Products],
                200,
                ['Link' => '<https://sync-test.myshopify.com/admin/api/2024-04/products.json?limit=250&page_info=next_page_token>; rel="next"']
            ),
            'https://sync-test.myshopify.com/admin/api/2024-04/products.json?limit=250&page_info=next_page_token' => Http::response(
                ['products' => $page2Products],
                200
            ),
        ]);

        $response = $this->postJson('/products/sync', [
            'shop' => 'sync-test.myshopify.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data'   => [
                'total_synced'  => 2,
                'total_created' => 2,
            ],
        ]);

        $this->assertDatabaseHas('products', [
            'shopify_product_id' => 101,
            'title'              => 'Snowboard Alpha',
            'vendor'             => 'Vendor A',
            'price'              => 299.99,
        ]);

        $this->assertDatabaseHas('products', [
            'shopify_product_id' => 102,
            'title'              => 'Snowboard Beta',
            'vendor'             => 'Vendor B',
            'price'              => 399.99,
        ]);
    }
}
