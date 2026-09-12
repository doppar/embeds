<?php

namespace Doppar\Embeds\Tests\Support;

use Phaseolies\DI\Container;

class TestContainer extends Container
{
    public function basePath(string $path = ''): string
    {
        $base = sys_get_temp_dir() . '/doppar_embeds_test_app';

        $configDir = $base . '/runtime/config';
        $cacheDir = $base . '/storage/framework/cache';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $databaseConfig = $configDir . '/database.php';

        if (!file_exists($databaseConfig)) {
            file_put_contents($databaseConfig, "<?php\n\nreturn ['default' => 'default', 'connections' => []];\n");
        }

        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }

    public function storagePath(string $path = ''): string
    {
        $base = $this->basePath('storage');

        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }

    public function runningInConsole(): bool
    {
        return true;
    }
}
