<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shop;
use App\Services\ShopifyProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Hiển thị danh sách sản phẩm trong database của App.
     */
    public function index(Request $request)
    {
        $shopDomain = $request->query('shop', session('shopify_oauth_shop'));
        $shop = $shopDomain ? Shop::where('shop_domain', $shopDomain)->first() : Shop::latest()->first();

        $tab = $request->query('tab', 'active');
        $activeCount = Product::whereNull('deleted_at')->count();
        $deletedCount = Product::whereNotNull('deleted_at')->count();

        if ($tab === 'deleted') {
            $products = Product::whereNotNull('deleted_at')
                ->orderBy('deleted_at', 'desc')
                ->paginate(15)
                ->withQueryString();
        } else {
            $products = Product::whereNull('deleted_at')
                ->orderBy('shopify_created_at', 'desc')
                ->paginate(15)
                ->withQueryString();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'shop'          => $shop?->shop_domain,
                'tab'           => $tab,
                'active_count'  => $activeCount,
                'deleted_count' => $deletedCount,
                'total'         => $products->total(),
                'products'      => $products->items(),
            ]);
        }

        return view('products.index', compact('products', 'shop', 'tab', 'activeCount', 'deletedCount'));
    }

    /**
     * Kích hoạt đồng bộ sản phẩm từ Shopify Admin API về Database.
     */
    public function sync(Request $request, ShopifyProductService $service)
    {
        $shopDomain = $request->input('shop', session('shopify_oauth_shop'));
        $shop = $shopDomain ? Shop::where('shop_domain', $shopDomain)->first() : Shop::latest()->first();

        if (!$shop) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Chưa có shop nào được kết nối.'], 400);
            }
            return redirect()->route('products.index')->with('error', 'Chưa có store nào được kết nối. Vui lòng kết nối store trước qua /auth');
        }

        // Nếu yêu cầu chạy ngầm qua Background Queue
        if ($request->boolean('async')) {
            \App\Jobs\SyncProductsJob::dispatch($shop);
            $msg = "Đã đưa tiến trình đồng bộ vào Background Queue Job. Hệ thống sẽ xử lý ngầm!";

            if ($request->wantsJson()) {
                return response()->json([
                    'status'  => 'queued',
                    'message' => $msg,
                ]);
            }

            return redirect()->route('products.index', ['shop' => $shop->shop_domain])->with('success', $msg);
        }

        try {
            $result = $service->syncProducts($shop);

            $msg = "Đồng bộ thành công {$result['total_synced']} sản phẩm! (Thêm mới: {$result['total_created']}, Cập nhật: {$result['total_updated']})";

            if ($request->wantsJson()) {
                return response()->json([
                    'status'  => 'success',
                    'message' => $msg,
                    'data'    => $result,
                ]);
            }

            return redirect()->route('products.index', ['shop' => $shop->shop_domain])->with('success', $msg);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            return redirect()->route('products.index', ['shop' => $shop->shop_domain])->with('error', 'Lỗi khi đồng bộ: ' . $e->getMessage());
        }
    }

    /**
     * Kích hoạt tạo vector embedding cho toàn bộ sản phẩm trong DB.
     */
    public function embedAll(Request $request, \App\Services\EmbeddingService $service)
    {
        try {
            $force = $request->boolean('force', false);
            $result = $service->embedAllProducts($force);

            $msg = "Đã vector hóa xong {$result['total']} sản phẩm (Mới: {$result['processed']}, Bỏ qua không đổi: {$result['skipped']})!";

            if ($request->wantsJson()) {
                return response()->json([
                    'status'  => 'success',
                    'message' => $msg,
                    'data'    => $result,
                ]);
            }

            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Lỗi khi tạo vector: ' . $e->getMessage());
        }
    }
}

