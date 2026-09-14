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
}