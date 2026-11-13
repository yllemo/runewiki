<?php
spl_autoload_register(static function ($class) { require_once __DIR__ . '/../core/' . $class . '.php'; });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$dir = sys_get_temp_dir() . '/runewiki-topbar-' . bin2hex(random_bytes(6));
mkdir($dir);
try {
    file_put_contents($dir . '/_topbar.md', "- [[start|Start]]\n- Meny 1\n  - [[guide|Undermeny 1]]\n  - Fler länkar\n    - [Extern](https://example.com)\n  - Undermeny 2\n- [[slut]]\n<!--\n- Dold\n-->\n");
    $menu = Helpers::loadTopbarMenu($dir);
    check(count($menu) === 3, 'Top-level siblings');
    check(count($menu[1]['children']) === 3, 'Nested siblings');
    check($menu[1]['children'][0]['target'] === 'guide', 'Wiki link');
    check($menu[1]['children'][1]['children'][0]['target'] === 'https://example.com', 'Third level');
    check($menu[1]['children'][2]['target'] === 'undermeny_2', 'Plain leaf');
    $siteName = 'Wiki'; $currentId = 'guide';
    $assetUrl = fn($path) => '/assets/' . $path;
    ob_start(); include __DIR__ . '/../templates/default/header.php'; $html = ob_get_clean();
    check(substr_count($html, '<details class="gbg-nav-group">') === 2, 'Nested disclosure markup');
    check(str_contains($html, 'href="/guide"') && str_contains($html, 'href="https://example.com"'), 'Rendered destinations');
    echo "PASS nested topbar parser and header rendering\n";
} finally { unlink($dir . '/_topbar.md'); rmdir($dir); }
