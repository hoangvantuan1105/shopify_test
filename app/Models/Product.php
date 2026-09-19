<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;
use Pgvector\Laravel\HasNeighbors;

class Product extends Model
{
    use HasNeighbors;

    protected $fillable = [
        'shopify_product_id', 'title', 'description', 'vendor',
        'product_type', 'tags', 'variants', 'price', 'image_url',
        'shopify_created_at', 'shopify_updated_at', 'synced_at',
        'deleted_at', 'data_hash', 'embedding',
    ];

    protected $casts = [
        'variants' => 'array',
        'embedding' => Vector::class,
        'shopify_created_at' => 'datetime',
        'shopify_updated_at' => 'datetime',
        'synced_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Tạo chuỗi văn bản đại diện ngữ nghĩa từ các thuộc tính truyền vào.
     */
    public static function buildRepresentationText(
        ?string $title,
        ?string $description = null,
        ?string $vendor = null,
        ?string $productType = null,
        ?string $tags = null,
        ?float $price = null
    ): string {
        $parts = [];
        $parts[] = "Title: " . ($title ?: 'N/A');

        if ($description) {
            $parts[] = "Description: " . $description;
        }

        if ($vendor) {
            $parts[] = "Vendor: " . $vendor;
        }

        if ($productType) {
            $parts[] = "Category: " . $productType;
        }

        if ($tags) {
            $parts[] = "Tags: " . $tags;
        }

        if ($price !== null) {
            $parts[] = "Price: $" . number_format($price, 2);
        }

        return implode(". ", $parts);
    }

    /**
     * Tính mã băm MD5 đại diện dữ liệu chuẩn cho sản phẩm.
     */
    public static function calculateDataHash(
        ?string $title,
        ?string $description = null,
        ?string $vendor = null,
        ?string $productType = null,
        ?string $tags = null,
        ?float $price = null
    ): string {
        return md5(self::buildRepresentationText($title, $description, $vendor, $productType, $tags, $price));
    }

    /**
     * Tạo chuỗi văn bản đại diện ngữ nghĩa từ instance model hiện tại.
     */
    public function generateRepresentationText(): string
    {
        return self::buildRepresentationText(
            $this->title,
            $this->description,
            $this->vendor,
            $this->product_type,
            $this->tags,
            $this->price !== null ? (float) $this->price : null
        );
    }

    /**
     * Tính mã băm MD5 từ dữ liệu hiện tại của instance model.
     */
    public function computeDataHash(): string
    {
        return md5($this->generateRepresentationText());
    }
}