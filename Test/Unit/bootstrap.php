<?php
/**
 * Bootstrap for the unit tests: finds the Magento installation's Composer autoloader by walking up from the module
 * directory (works under app/code and under vendor/), and maps the module namespace as a fallback.
 */
declare(strict_types=1);

$dir = __DIR__;
$autoload = null;
for ($i = 0; $i < 8; $i++) {
    $dir = dirname($dir);
    if (is_file($dir . '/vendor/autoload.php')) {
        $autoload = $dir . '/vendor/autoload.php';
        break;
    }
}
if ($autoload !== null) {
    require_once $autoload;
}
$moduleRoot = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($moduleRoot): void {
    $prefix = 'CommerceGears\\Blog\\';
    if (str_starts_with($class, $prefix)) {
        $file = $moduleRoot . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
if (!function_exists('__')) {
    $phrase = dirname(__DIR__, 5) . '/lib/internal/Magento/Framework/Phrase/__.php';
    if (is_file($phrase)) {
        require_once $phrase;
    }
}
