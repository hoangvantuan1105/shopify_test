<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\EmbeddingService;
use Illuminate\Http\Request;
use Pgvector\Laravel\Vector;

class SearchController extends Controller
{
    /**
     */
    public function index(Request $request, EmbeddingService $embeddingService)
    {
        $query = trim($request->input('q', ''));
        $products = collect();
        $queryTime = 0;

        if (!empty($query)) {
            $startTime = microtime(true);

            // 1. Chuyển câu truy vấn thành Vector Embedding qua Ollama
            $queryVector = $embeddingService->embed($query);
            $vectorString = (string) new Vector($queryVector);

            // 2. Thực hiện Vector Search: Luôn lấy Top 5 sản phẩm có khoảng cách Cosine gần nhất
            $products = Product::whereNull('deleted_at')
                ->whereNotNull('embedding')
                ->select([
                    'id',
                    'shopify_product_id',
                    'title',
                    'description',
                    'vendor',
                    'product_type',
                    'price',
                    'image_url',
                    'tags',
                ])
                ->selectRaw("round(((1 - (embedding <=> ?::vector)) * 100)::numeric, 1) AS similarity_score", [$vectorString])
                ->orderByRaw("embedding <=> ?::vector ASC", [$vectorString])
                ->limit(5)
                ->get();

            $queryTime = round((microtime(true) - $startTime) * 1000, 1);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'query'         => $query,
                'query_time_ms' => $queryTime,
                'count'         => $products->count(),
                'results'       => $products,
            ]);
        }

        return view('search.index', compact('query', 'products', 'queryTime'));
    }
}
