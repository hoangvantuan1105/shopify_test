<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyBulkOperationService
{
    protected string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('shopify.api_version', '2024-04');
    }

    /**
     * Gửi GraphQL mutation để bắt đầu một Bulk Operation truy vấn hàng loạt Product.
     * Thích hợp cho cửa hàng có 10.000 - 100.000+ sản phẩm.
     */
    public function startBulkOperation(Shop $shop, ?string $customQuery = null): array
    {
        $graphqlEndpoint = "https://{$shop->shop_domain}/admin/api/{$this->apiVersion}/graphql.json";

        $defaultQuery = <<<'GRAPHQL'
{
  products {
    edges {
      node {
        id
        title
        descriptionHtml
        vendor
        productType
        tags
        createdAt
        updatedAt
        featuredImage {
          url
        }
        variants {
          edges {
            node {
              id
              price
              inventoryQuantity
            }
          }
        }
      }
    }
  }
}
GRAPHQL;

        $targetQuery = $customQuery ?: $defaultQuery;

        $mutation = <<<GRAPHQL
mutation {
  bulkOperationRunQuery(
    query: """
    {$targetQuery}
    """
  ) {
    bulkOperation {
      id
      status
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        Log::info("ShopifyBulkOperationService: Bắt đầu gửi Bulk Operation cho {$shop->shop_domain}");

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
            'Content-Type'           => 'application/json',
        ])
        ->retry(3, 500, function ($exception, $request) {
            return $exception instanceof \Illuminate\Http\Client\ConnectionException;
        })
        ->post($graphqlEndpoint, [
            'query' => $mutation,
        ]);

        if (!$response->successful()) {
            Log::error("ShopifyBulkOperationService: Lỗi HTTP {$response->status()}", [
                'body' => $response->body(),
            ]);
            throw new \Exception("Lỗi khởi chạy Bulk Operation: " . $response->body());
        }

        $data = $response->json();
        $userErrors = $data['data']['bulkOperationRunQuery']['userErrors'] ?? [];

        if (!empty($userErrors)) {
            $errorMsg = collect($userErrors)->pluck('message')->implode(', ');
            Log::error("ShopifyBulkOperationService UserErrors: {$errorMsg}");
            throw new \Exception("Shopify GraphQL Bulk Operation Error: {$errorMsg}");
        }

        $operation = $data['data']['bulkOperationRunQuery']['bulkOperation'] ?? [];
        Log::info("ShopifyBulkOperationService: Đã tạo Bulk Operation thành công", $operation);

        return $operation;
    }

    /**
     * Kiểm tra trạng thái hiện tại của Bulk Operation (CREATED, RUNNING, COMPLETED, FAILED, CANCELED).
     */
    public function checkStatus(Shop $shop): array
    {
        $graphqlEndpoint = "https://{$shop->shop_domain}/admin/api/{$this->apiVersion}/graphql.json";

        $query = <<<'GRAPHQL'
query {
  currentBulkOperation {
    id
    status
    errorCode
    createdAt
    completedAt
    objectCount
    fileSize
    url
    partialDataUrl
  }
}
GRAPHQL;

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
            'Content-Type'           => 'application/json',
        ])
        ->retry(3, 500)
        ->post($graphqlEndpoint, [
            'query' => $query,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Lỗi kiểm tra trạng thái Bulk Operation: " . $response->body());
        }

        $result = $response->json('data.currentBulkOperation');

        Log::info("ShopifyBulkOperationService: Trạng thái hiện tại", (array)$result);

        return (array)$result;
    }

    /**
     * Hủy Bulk Operation đang chạy (nếu có).
     */
    public function cancelBulkOperation(Shop $shop, ?string $operationId = null): array
    {
        $graphqlEndpoint = "https://{$shop->shop_domain}/admin/api/{$this->apiVersion}/graphql.json";

        $mutation = <<<'GRAPHQL'
mutation {
  bulkOperationCancel {
    bulkOperation {
      id
      status
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
            'Content-Type'           => 'application/json',
        ])->post($graphqlEndpoint, ['query' => $mutation]);

        return $response->json('data.bulkOperationCancel') ?? [];
    }

    /**
     * Đọc và parse file JSONL theo từng dòng (Streaming) từ URL Shopify trả về.
     * Tránh tốn RAM (Out Of Memory) ngay cả với 100.000+ sản phẩm.
     *
     * @param string $jsonlUrl URL file kết quả JSONL từ Shopify CDN
     * @param callable $onRecord Hàm callback xử lý từng bản ghi: function(array $record): void
     * @return int Tổng số bản ghi đã xử lý
     */
    public function streamAndProcessJsonl(string $jsonlUrl, callable $onRecord): int
    {
        $handle = fopen($jsonlUrl, 'rb');
        if ($handle === false) {
            throw new \Exception("Không thể mở stream từ URL JSONL: {$jsonlUrl}");
        }

        $processedCount = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) continue;

                $record = json_decode($line, true);
                if (is_array($record)) {
                    $onRecord($record);
                    $processedCount++;
                }
            }
        } finally {
            fclose($handle);
        }

        Log::info("ShopifyBulkOperationService: Đã stream và xử lý thành công {$processedCount} bản ghi JSONL.");

        return $processedCount;
    }
}
