<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyWebhook
{
    /**
     * Xác thực chữ ký HMAC và tính lũy đẳng (idempotency) của Shopify Webhook.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        $secret = config('shopify.client_secret');

        if (empty($hmacHeader) || empty($secret)) {
            Log::warning('Webhook bị từ chối: thiếu chữ ký X-Shopify-Hmac-Sha256 hoặc chưa cấu hình App Secret.');
            return response()->json(['error' => 'Unauthorized: Missing HMAC signature or secret'], 401);
        }

        // Shopify tính HMAC bằng raw content của body request và base64 encode
        $rawContent = $request->getContent();
        $calculatedHmac = base64_encode(hash_hmac('sha256', $rawContent, $secret, true));

        if (!hash_equals($calculatedHmac, $hmacHeader)) {
            Log::warning('Webhook bị từ chối: Chữ ký HMAC không khớp.');
            return response()->json(['error' => 'Unauthorized: Invalid HMAC signature'], 401);
        }

        // Xử lý Idempotency: Kiểm tra xem Webhook ID này đã từng được xử lý chưa
        $webhookId = $request->header('X-Shopify-Webhook-Id');
        if (!empty($webhookId)) {
            $cacheKey = "shopify_webhook:{$webhookId}";
            // Nếu cache key đã tồn tại thì bỏ qua xử lý nhưng trả về 200 để Shopify không gửi lại
            if (!Cache::add($cacheKey, 1, now()->addHours(24))) {
                Log::info("Webhook {$webhookId} đã được xử lý trước đó. Bỏ qua để đảm bảo tính lũy đẳng (idempotency).");
                return response()->json([
                    'status'  => 'skipped',
                    'message' => 'Duplicate webhook ignored',
                ], 200);
            }
        }

        return $next($request);
    }
}
