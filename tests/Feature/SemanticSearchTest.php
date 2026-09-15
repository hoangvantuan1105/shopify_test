<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\EmbeddingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Pgvector\Laravel\Vector;
use Tests\TestCase;

class SemanticSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_can_view_search_page_without_query(): void
    {
        $response = $this->get('/search');

        $response->assertStatus(200);
        $response->assertSee('Semantic Vector Search');
    }

    public function test_can_search_with_query_and_return_top_results(): void
    {
        // Giả lập mock EmbeddingService
        $mockService = $this->createMock(EmbeddingService::class);
        $dummyVector = array_fill(0, 768, 0.1);
        $mockService->method('embed')->willReturn($dummyVector);
        $this->app->instance(EmbeddingService::class, $mockService);

        $response = $this->get('/search?q=snowboard');

        $response->assertStatus(200);
        $response->assertSee('Kết quả tìm kiếm cho:');
    }

    public function test_search_returns_json_when_requested(): void
    {
        $mockService = $this->createMock(EmbeddingService::class);
        $dummyVector = array_fill(0, 768, 0.05);
        $mockService->method('embed')->willReturn($dummyVector);
        $this->app->instance(EmbeddingService::class, $mockService);

        $response = $this->getJson('/search?q=ski+wax');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'query',
            'query_time_ms',
            'count',
            'results',
        ]);
    }
}
