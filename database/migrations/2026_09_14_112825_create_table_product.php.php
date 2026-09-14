<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_product_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->text('tags')->nullable();
            $table->jsonb('variants')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('image_url')->nullable();
            $table->timestampTz('shopify_created_at')->nullable();
            $table->timestampTz('shopify_updated_at')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('data_hash')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE products ADD COLUMN embedding vector(1536)');
        DB::statement('CREATE INDEX products_embedding_idx ON products USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
         Schema::dropIfExists('products');
    }
};
