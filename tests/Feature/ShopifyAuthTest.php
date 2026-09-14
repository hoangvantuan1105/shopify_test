<?php

namespace Tests\Feature;

use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopifyAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('shopify.client_id', 'test_client_id_123');
        Config::set('shopify.client_secret', 'test_secret_456');
        Config::set('shopify.scopes', 'read_products,write_products');
        Config::set('shopify.app_url', 'https://test-app.ngrok-free.dev');
        Config::set('shopify.redirect_uri', 'https://test-app.ngrok-free.dev/auth/callback');

        if (!Schema::hasTable('shops')) {
            Schema::create('shops', function (Blueprint $table) {
                $table->id();
                $table->string('shop_domain')->unique();
                $table->text('access_token');
                $table->string('scope')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_auth_route_fails_without_shop_param(): void
    {
        $response = $this->get('/auth');

        $response->assertStatus(400);
    }

    public function test_auth_route_fails_with_invalid_shop_param(): void
    {
        $response = $this->get('/auth?shop=invalid_domain@com');

        $response->assertStatus(400);
    }

    public function test_auth_route_redirects_to_shopify_with_valid_parameters(): void
    {
        $response = $this->get('/auth?shop=quickstart-store.myshopify.com');

        $response->assertRedirect();

        $targetUrl = $response->headers->get('Location');
        $this->assertNotNull($targetUrl);

        $parsed = parse_url($targetUrl);
        $this->assertEquals('quickstart-store.myshopify.com', $parsed['host']);
        $this->assertEquals('/admin/oauth/authorize', $parsed['path']);

        parse_str($parsed['query'], $queryParams);

        $this->assertEquals('test_client_id_123', $queryParams['client_id']);
        $this->assertEquals('read_products,write_products', $queryParams['scope']);
        $this->assertEquals('https://test-app.ngrok-free.dev/auth/callback', $queryParams['redirect_uri']);
        $this->assertNotEmpty($queryParams['state']);

        // Check state stored in session & cache
        $response->assertSessionHas('shopify_oauth_state', $queryParams['state']);
        $response->assertSessionHas('shopify_oauth_shop', 'quickstart-store.myshopify.com');
        $this->assertEquals('quickstart-store.myshopify.com', Cache::get("shopify_oauth_state:{$queryParams['state']}"));
    }

    public function test_auth_route_normalizes_short_shop_name(): void
    {
        $response = $this->get('/auth?shop=my-store');

        $response->assertRedirect();

        $targetUrl = $response->headers->get('Location');
        $parsed = parse_url($targetUrl);

        $this->assertEquals('my-store.myshopify.com', $parsed['host']);
    }

    public function test_auth_route_accepts_shop_with_underscore(): void
    {
        $response = $this->get('/auth?shop=laravel_test.myshopify.com');

        $response->assertRedirect();

        $targetUrl = $response->headers->get('Location');
        $parsed = parse_url($targetUrl);

        $this->assertEquals('laravel_test.myshopify.com', $parsed['host']);
    }

    public function test_callback_fails_with_missing_params(): void
    {
        $response = $this->get('/auth/callback?shop=test.myshopify.com');

        $response->assertStatus(400);
    }

    public function test_callback_fails_with_invalid_state(): void
    {
        $response = $this->get('/auth/callback?' . http_build_query([
            'shop' => 'test.myshopify.com',
            'code' => 'fake_code',
            'hmac' => 'fake_hmac',
            'state' => 'wrong_state',
            'timestamp' => '1234567890',
        ]));

        $response->assertStatus(403);
    }

    public function test_callback_fails_with_invalid_hmac(): void
    {
        $state = 'valid_state_123';
        Cache::put("shopify_oauth_state:{$state}", 'test.myshopify.com', now()->addMinutes(10));

        $response = $this->get('/auth/callback?' . http_build_query([
            'shop' => 'test.myshopify.com',
            'code' => 'fake_code',
            'hmac' => 'invalid_calculated_hmac',
            'state' => $state,
            'timestamp' => '1234567890',
        ]));

        $response->assertStatus(403);
    }

    public function test_callback_succeeds_and_saves_access_token(): void
    {
        $shop = 'test-store.myshopify.com';
        $state = 'valid_secret_state';
        $code = 'valid_auth_code';
        $timestamp = '1789389999';

        Cache::put("shopify_oauth_state:{$state}", $shop, now()->addMinutes(10));

        $params = [
            'code' => $code,
            'shop' => $shop,
            'state' => $state,
            'timestamp' => $timestamp,
        ];
        ksort($params);
        $hmac = hash_hmac('sha256', http_build_query($params), 'test_secret_456');

        Http::fake([
            "https://{$shop}/admin/oauth/access_token" => Http::response([
                'access_token' => 'shpat_test_access_token_12345678',
                'scope' => 'read_products',
            ], 200),
        ]);

        $callbackParams = array_merge($params, ['hmac' => $hmac]);
        $response = $this->get('/auth/callback?' . http_build_query($callbackParams));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'shop' => $shop,
        ]);

        $this->assertDatabaseHas('shops', [
            'shop_domain' => $shop,
            'access_token' => 'shpat_test_access_token_12345678',
            'scope' => 'read_products',
        ]);
    }
}
