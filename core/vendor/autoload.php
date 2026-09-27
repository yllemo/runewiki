<?php
// Bundled MIT-licensed components; no Composer installation needed at runtime.
require_once __DIR__ . '/symfony/deprecation-contracts/function.php';
require_once __DIR__ . '/symfony/polyfill-ctype/bootstrap.php';
spl_autoload_register(static function (string $class): void {
    foreach (['Symfony\\Component\\Yaml\\' => '/symfony/yaml/', 'Symfony\\Polyfill\\Ctype\\' => '/symfony/polyfill-ctype/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = __DIR__ . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require_once $file;
            return;
        }
    }
}, true, true);
