<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semantic Search - Tìm kiếm Sản phẩm Thông minh</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <!-- Navigation Header -->
        <div class="flex items-center justify-between pb-6 border-b border-gray-200 mb-8">
            <div>
                <a href="{{ route('products.index') }}" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 transition mb-2">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Quay lại Quản lý Sản phẩm
                </a>
                <h1 class="text-3xl font-bold text-gray-900">Semantic Vector Search</h1>
                <p class="text-sm text-gray-500 mt-1">Tìm kiếm sản phẩm theo ngữ nghĩa bằng Vector Embedding (Ollama nomic-embed-text + PostgreSQL pgvector).</p>
            </div>
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Model: nomic-embed-text (768 dim)
                </span>
            </div>
        </div>

        <!-- Search Box -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
            <form action="{{ route('search.index') }}" method="GET" class="space-y-4">
                <div class="relative flex items-center">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" name="q" value="{{ $query }}" 
                        placeholder="Nhập bất kỳ câu hỏi hoặc từ khóa nào (ví dụ: 'liquid snowboard', 'wax', 'ván trượt tuyết cao cấp')..." 
                        class="block w-full pl-12 pr-32 py-4 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-base"
                        autofocus>
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center">
                        <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                            Tìm kiếm
                        </button>
                    </div>
                </div>

                <!-- Từ khóa gợi ý nhanh -->
                <div class="flex items-center gap-2 pt-2 flex-wrap text-xs">
                    <span class="text-gray-400 font-medium">Gợi ý thử nghiệm:</span>
                    <a href="{{ route('search.index', ['q' => 'liquid snowboard']) }}" class="px-2.5 py-1 bg-gray-100 hover:bg-indigo-50 hover:text-indigo-600 rounded-md text-gray-600 transition">liquid snowboard</a>
                    <a href="{{ route('search.index', ['q' => 'wax']) }}" class="px-2.5 py-1 bg-gray-100 hover:bg-indigo-50 hover:text-indigo-600 rounded-md text-gray-600 transition">wax</a>
                    <a href="{{ route('search.index', ['q' => 'ván trượt tuyết cao cấp']) }}" class="px-2.5 py-1 bg-gray-100 hover:bg-indigo-50 hover:text-indigo-600 rounded-md text-gray-600 transition">ván trượt tuyết cao cấp</a>
                    <a href="{{ route('search.index', ['q' => 'the oxygen snowboard']) }}" class="px-2.5 py-1 bg-gray-100 hover:bg-indigo-50 hover:text-indigo-600 rounded-md text-gray-600 transition">the oxygen snowboard</a>
                </div>
            </form>
        </div>

        <!-- Kết quả tìm kiếm -->
        @if(!empty($query))
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">
                    Kết quả tìm kiếm cho: <span class="text-indigo-600">"{{ $query }}"</span>
                </h2>
                <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                    ⚡ Top {{ $products->count() }} sản phẩm phù hợp nhất (Thời gian: {{ $queryTime }} ms)
                </span>
            </div>

            @if($products->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                    <div class="mx-auto w-12 h-12 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-gray-900">Không tìm thấy sản phẩm</h3>
                    <p class="mt-1 text-sm text-gray-500 max-w-md mx-auto">
                        Hãy đảm bảo bạn đã đồng bộ sản phẩm từ Shopify và kích hoạt tính năng "Tạo Vector Embedding".
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($products as $index => $item)
                        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <!-- Rank Badge -->
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm
                                    {{ $index === 0 ? 'bg-amber-100 text-amber-800 ring-2 ring-amber-300' : ($index === 1 ? 'bg-slate-100 text-slate-700' : 'bg-gray-100 text-gray-600') }}">
                                    #{{ $index + 1 }}
                                </div>

                                <!-- Product Image -->
                                <div class="w-16 h-16 rounded-lg bg-gray-100 overflow-hidden border border-gray-200 flex-shrink-0 flex items-center justify-center">
                                    @if($item->image_url)
                                        <img src="{{ $item->image_url }}" alt="{{ $item->title }}" class="w-full h-full object-cover">
                                    @else
                                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    @endif
                                </div>

                                <!-- Product Info -->
                                <div>
                                    <h3 class="text-base font-bold text-gray-900 hover:text-indigo-600 transition">
                                        {{ $item->title }}
                                    </h3>
                                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 flex-wrap">
                                        @if($item->vendor)
                                            <span class="font-medium text-gray-700">Vendor: {{ $item->vendor }}</span>
                                            <span>•</span>
                                        @endif
                                        @if($item->product_type)
                                            <span>Loại: {{ $item->product_type }}</span>
                                            <span>•</span>
                                        @endif
                                        <span>ID: {{ $item->shopify_product_id }}</span>
                                    </div>
                                    @if($item->description)
                                        <p class="mt-1 text-xs text-gray-500 line-clamp-1 max-w-xl">{{ $item->description }}</p>
                                    @endif
                                </div>
                            </div>

                            <!-- Price & Similarity Score Badge -->
                            <div class="flex sm:flex-col items-end justify-between w-full sm:w-auto gap-2 flex-shrink-0">
                                <div class="text-right">
                                    <span class="text-xs text-gray-400 block">Giá bán</span>
                                    <span class="text-lg font-bold text-gray-900">
                                        {{ $item->price !== null ? '$' . number_format($item->price, 2) : '—' }}
                                    </span>
                                </div>

                                <div class="text-right">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                                        {{ $item->similarity_score >= 60 ? 'bg-emerald-100 text-emerald-800' : ($item->similarity_score >= 45 ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700') }}">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ $item->similarity_score }}% phù hợp
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <!-- Placeholder khi chưa tìm kiếm -->
            <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
                <div class="mx-auto w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900">Tìm kiếm theo Ngữ nghĩa (Semantic Search)</h3>
                <p class="mt-1 text-sm text-gray-500 max-w-md mx-auto">
                    Nhập câu hỏi hoặc từ khóa bất kỳ. Hệ thống sẽ tự động vector hóa và trả về Top 5 sản phẩm có độ tương đồng Cosine cao nhất.
                </p>
            </div>
        @endif

    </div>
</body>
</html>
