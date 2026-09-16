<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ShopifyAuthController extends Controller
{
    /**
     * Bắt đầu luồng OAuth: redirect merchant sang trang cấp quyền Shopify.
     */
    public function auth(Request $request): RedirectResponse
    {
        $shop = $request->query('shop');

        if (!$shop) {
            abort(400, 'Thiếu tham số "shop" (ví dụ: ?shop=your-store.myshopify.com).');
        }

        // Chuẩn hóa tên miền shop
        $shop = strtolower(trim($shop));
        if (!str_contains($shop, '.')) {
            $shop .= '.myshopify.com';
        }

       
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-_]*\.myshopify\.com$/i', $shop)) {
            abort(400, 'Tên miền shop không hợp lệ. Vui lòng cung cấp định dạng *.myshopify.com.');
        }

        $clientId = config('shopify.client_id');
        if (empty($clientId)) {
            abort(500, 'Chưa cấu hình SHOPIFY_API_KEY / SHOPIFY_CLIENT_ID trong .env');
        }

        $scope = config('shopify.scopes', 'read_products');
        $redirectUri = config('shopify.redirect_uri') ?: (rtrim(config('shopify.app_url', url('/')), '/') . '/auth/callback');

   
        $state = Str::random(40);


        session([
            'shopify_oauth_state' => $state,
            'shopify_oauth_shop'  => $shop,
        ]);
        Cache::put("shopify_oauth_state:{$state}", $shop, now()->addMinutes(15));


        $authorizationUrl = "https://{$shop}/admin/oauth/authorize?" . http_build_query([
            'client_id'    => $clientId,
            'scope'        => $scope,
            'redirect_uri' => $redirectUri,
            'state'        => $state,
        ]);

        return redirect()->away($authorizationUrl);
    }

    /**

     */
    public function callback(Request $request): JsonResponse
    {
        $shop  = $request->query('shop');
        $code  = $request->query('code');
        $hmac  = $request->query('hmac');
        $state = $request->query('state');


        if (!$shop || !$code || !$hmac || !$state) {
            abort(400, 'Thiếu tham số bắt buộc từ Shopify callback (shop, code, hmac, state).');
        }


        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-_]*\.myshopify\.com$/i', $shop)) {
            abort(400, 'Tên miền shop không hợp lệ.');
        }


        $sessionState = session('shopify_oauth_state');
        $cachedShop   = Cache::get("shopify_oauth_state:{$state}");

        if ($state !== $sessionState && $cachedShop !== $shop) {
            abort(403, 'Xác thực state (CSRF) thất bại hoặc phiên làm việc đã hết hạn.');
        }


        session()->forget('shopify_oauth_state');
        Cache::forget("shopify_oauth_state:{$state}");


        $params = $request->query();
        unset($params['hmac'], $params['signature']);
        ksort($params);
        $queryString = http_build_query($params);

        $clientSecret = config('shopify.client_secret');
        if (empty($clientSecret)) {
            abort(500, 'Chưa cấu hình SHOPIFY_API_SECRET / SHOPIFY_CLIENT_SECRET trong .env');
        }

        $calculatedHmac = hash_hmac('sha256', $queryString, $clientSecret);
        if (!hash_equals($calculatedHmac, $hmac)) {
            abort(403, 'Chữ ký HMAC không hợp lệ!');
        }


        $response = Http::asJson()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id'     => config('shopify.client_id'),
            'client_secret' => $clientSecret,
            'code'          => $code,
        ]);

        if (!$response->successful()) {
            abort(500, 'Không thể lấy Access Token từ Shopify: ' . $response->body());
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;
        $scope = $data['scope'] ?? null;

        if (!$accessToken) {
            abort(500, 'Không nhận được access_token từ Shopify.');
        }

        // 6. Lưu access_token vào database
        $shopModel = Shop::updateOrCreate(
            ['shop_domain' => $shop],
            [
                'access_token' => $accessToken,
                'scope'        => $scope,
            ]
        );

        return response()->json([
            'status'       => 'success',
            'message'      => "Cài đặt ứng dụng thành công cho store {$shop}!",
            'shop'         => $shop,
            'scope'        => $scope,
            'access_token' => substr($accessToken, 0, 8) . '...' . substr($accessToken, -4),
        ]);
    }
}
