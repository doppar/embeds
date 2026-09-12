<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Vector Index Driver
    |--------------------------------------------------------------------------
    |
    | Determines how #[Embeds] vectors are stored and searched. The
    | available options are:
    |
    | - "brute_force" → Works everywhere, SQLite included. Ranks candidates
    |                    in PHP at search time.
    |
    | - "pgvector"    → Requires PostgreSQL with the pgvector extension
    |                    installed. Uses a real HNSW index — search happens
    |                    inside the database, not in PHP.
    |
    | - "redis"       → Requires Redis Stack (the RediSearch module) and
    |                    predis/predis. Uses a real HNSW index the same way
    |                    "pgvector" does, backed by Redis instead.
    |
    */

    'driver' => env('EMBEDS_DRIVER', 'brute_force'),

    /*
    |--------------------------------------------------------------------------
    | Vector Dimensions
    |--------------------------------------------------------------------------
    |
    | The size of the vectors produced by your embedding model. This must
    | match the model configured for #[Embeds] — 384 is correct for the
    | default local model (Xenova/all-MiniLM-L6-v2). Only used by drivers
    | that need a fixed-width native index, such as "pgvector" and "redis".
    |
    */

    'dimensions' => env('EMBEDS_DIMENSIONS', 384),

    /*
    |--------------------------------------------------------------------------
    | Driver Connections
    |--------------------------------------------------------------------------
    |
    | The connection each driver should use. Leave "pgvector" null to use
    | the application's default database connection. "redis" takes a host
    | and port instead, since it doesn't go through Doppar's database
    | connection system.
    |
    */

    'connections' => [
        'pgvector' => env('EMBEDS_PGVECTOR_CONNECTION', null),

        'redis' => [
            'host' => env('EMBEDS_REDIS_HOST', '127.0.0.1'),
            'port' => env('EMBEDS_REDIS_PORT', 6379),
        ],
    ],

];
