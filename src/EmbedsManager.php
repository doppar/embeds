<?php

namespace Doppar\Embeds;

use Doppar\Embeds\Contracts\VectorIndexDriver;
use Doppar\Embeds\Drivers\BruteForceDriver;
use Doppar\Embeds\Drivers\PgVectorDriver;
use Doppar\Embeds\Drivers\RedisDriver;

class EmbedsManager
{
    /**
     * Resolve the vector index driver configured for the application.
     *
     * @return VectorIndexDriver
     */
    public static function driver(): VectorIndexDriver
    {
        $driver = config('embeds.driver', 'brute_force');

        return match ($driver) {
            'pgvector' => app(PgVectorDriver::class),
            'redis' => app(RedisDriver::class),
            'brute_force' => app(BruteForceDriver::class),
            default => throw new \RuntimeException(
                "Unknown embeds driver [{$driver}]. Expected \"brute_force\", \"pgvector\", or \"redis\"."
            ),
        };
    }
}
