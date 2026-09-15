<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Sản phẩm - Shopify Vector Search</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between pb-6 border-b border-gray-200 gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900">Quản lý Sản phẩm</h1>
                    @if($shop)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Store: {{ $shop->shop_domain }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            Chưa có Store kết nối
                        </span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 mt-1">Đồng bộ sản phẩm từ Shopify Admin và quản lý dữ liệu Vector Search.</p>
            </div>
            
            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ route('search.index') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <span> Tìm kiếm</span>
                </a>

                <form action="{{ route('products.embed') }}" method="POST" id="embedForm">
                    @csrf
                    <button type="submit" id="embedBtn" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                        <svg id="embedIcon" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span id="embedText"> Tạo Vector Embedding</span>
                    </button>
                </form>

                <form action="{{ route('products.sync') }}" method="POST" id="syncForm">
                    @csrf
                    @if($shop)
                        <input type="hidden" name="shop" value="{{ $shop->shop_domain }}">
                    @endif
                    <button type="submit" id="syncBtn" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                        <svg id="syncIcon" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span id="syncText">Sync Products từ Shopify</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Thông báo Flash Messages -->
        @if(session('success'))
            <div class="mt-4 p-4 rounded-md bg-green-50 border border-green-200">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mt-4 p-4 rounded-md bg-red-50 border border-red-200">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Bảng thống kê sơ bộ -->
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-100 p-5">
                <dt class="text-sm font-medium text-gray-500 truncate">Tổng số sản phẩm trong DB</dt>
                <dd class="mt-1 text-3xl font-semibold text-indigo-600">{{ $products->total() }}</dd>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-100 p-5">
                <dt class="text-sm font-medium text-gray-500 truncate">Đã có Vector Embedding</dt>
                <dd class="mt-1 text-3xl font-semibold text-emerald-600">
                    {{ \App\Models\Product::whereNotNull('embedding')->count() }}
                </dd>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-100 p-5">
                <dt class="text-sm font-medium text-gray-500 truncate">Lần Sync cuối cùng</dt>
                <dd class="mt-1 text-sm font-medium text-gray-700">
                    @php $latest = \App\Models\Product::max('synced_at'); @endphp
                    {{ $latest ? \Carbon\Carbon::parse($latest)->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') : 'Chưa sync' }}
                </dd>
            </div>
        </div>

        <!-- Tabs chuyển đổi Active vs Deleted -->
        <div class="mt-8 border-b border-gray-200">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <a href="{{ route('products.index', array_merge(request()->query(), ['tab' => 'active', 'page' => 1])) }}" 
                   class="{{ $tab !== 'deleted' ? 'border-indigo-500 text-indigo-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap pb-4 px-1 border-b-2 text-sm flex items-center gap-2 transition">
                    <svg class="w-5 h-5 {{ $tab !== 'deleted' ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Sản phẩm đang hoạt động</span>
                    <span class="{{ $tab !== 'deleted' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }} ml-1.5 py-0.5 px-2.5 rounded-full text-xs font-semibold">
                        {{ $activeCount }}
                    </span>
                </a>

                <a href="{{ route('products.index', array_merge(request()->query(), ['tab' => 'deleted', 'page' => 1])) }}" 
                   class="{{ $tab === 'deleted' ? 'border-red-500 text-red-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap pb-4 px-1 border-b-2 text-sm flex items-center gap-2 transition">
                    <svg class="w-5 h-5 {{ $tab === 'deleted' ? 'text-red-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span>Sản phẩm đã xóa (Soft Deleted)</span>
                    <span class="{{ $tab === 'deleted' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700' }} ml-1.5 py-0.5 px-2.5 rounded-full text-xs font-semibold">
                        {{ $deletedCount }}
                    </span>
                </a>
            </nav>
        </div>

        <!-- Bảng danh sách sản phẩm -->
        <div class="mt-6 bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
            @if($products->isEmpty())
                <div class="text-center py-16 px-4">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    @if($tab === 'deleted')
                        <h3 class="mt-2 text-base font-semibold text-gray-900">Không có sản phẩm nào bị xóa</h3>
                        <p class="mt-1 text-sm text-gray-500">Tất cả sản phẩm hiện tại đều đang ở trạng thái hoạt động.</p>
                    @else
                        <h3 class="mt-2 text-base font-semibold text-gray-900">Chưa có sản phẩm nào trong Database</h3>
                        <p class="mt-1 text-sm text-gray-500">Bấm nút "Sync Products từ Shopify" ở góc trên để bắt đầu lấy dữ liệu về.</p>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sản phẩm</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor / Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Giá</th>
                                @if($tab === 'deleted')
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tags</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái Vector</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-red-600 uppercase tracking-wider">Thời gian xóa</th>
                                @else
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tags</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Embedding</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lần sync cuối</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($products as $product)
                                <tr class="hover:bg-gray-50 transition {{ $tab === 'deleted' ? 'bg-red-50/20' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-12 w-12 rounded-lg bg-gray-100 overflow-hidden border border-gray-200 flex items-center justify-center relative">
                                                @if($product->image_url)
                                                    <img class="h-12 w-12 object-cover {{ $tab === 'deleted' ? 'grayscale opacity-60' : '' }}" src="{{ $product->image_url }}" alt="{{ $product->title }}">
                                                @else
                                                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                @endif
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-semibold {{ $tab === 'deleted' ? 'text-gray-600 line-through' : 'text-gray-900' }} max-w-xs truncate" title="{{ $product->title }}">
                                                    {{ $product->title }}
                                                </div>
                                                <div class="text-xs text-gray-500">ID: {{ $product->shopify_product_id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $product->vendor ?: '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ $product->product_type ?: 'Chưa phân loại' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">
                                            {{ $product->price ? '$' . number_format($product->price, 2) : '—' }}
                                        </div>
                                        <div class="text-xs text-gray-500">{{ count($product->variants ?? []) }} biến thể</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap max-w-xs truncate">
                                        @if($product->tags)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800" title="{{ $product->tags }}">
                                                {{ Str::limit($product->tags, 30) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                    @if($tab === 'deleted')
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                ✗ Đã gỡ bỏ Vector
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-semibold text-red-600">
                                                {{ \Carbon\Carbon::parse($product->deleted_at)->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ \Carbon\Carbon::parse($product->deleted_at)->diffForHumans() }}
                                            </div>
                                        </td>
                                    @else
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($product->embedding)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    ✓ 768 dim
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                    Chưa có
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $product->synced_at ? \Carbon\Carbon::parse($product->synced_at)->diffForHumans() : '—' }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $products->links() }}
                </div>
            @endif
        </div>


    </div>

    <script>
        const syncForm = document.getElementById('syncForm');
        const syncBtn = document.getElementById('syncBtn');
        const syncText = document.getElementById('syncText');
        const syncIcon = document.getElementById('syncIcon');

        syncForm.addEventListener('submit', function() {
            syncBtn.disabled = true;
            syncBtn.classList.add('opacity-75', 'cursor-not-allowed');
            syncText.innerText = 'Đang đồng bộ...';
            syncIcon.classList.add('animate-spin');
        });

        const embedForm = document.getElementById('embedForm');
        const embedBtn = document.getElementById('embedBtn');
        const embedText = document.getElementById('embedText');
        const embedIcon = document.getElementById('embedIcon');

        embedForm.addEventListener('submit', function() {
            embedBtn.disabled = true;
            embedBtn.classList.add('opacity-75', 'cursor-not-allowed');
            embedText.innerText = 'Đang tạo Vector...';
            embedIcon.classList.add('animate-spin');
        });
    </script>
</body>
</html>
