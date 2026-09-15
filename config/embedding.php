<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Embedding Provider ('ollama' or 'openai')
    |--------------------------------------------------------------------------
    */
    'default' => env('EMBEDDING_PROVIDER', 'ollama'),

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
        'model'    => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model'   => env('OPENAI_EMBED_MODEL', 'text-embedding-3-small'),
    ],
];
