<?php
spl_autoload_register(static function ($class) { require_once __DIR__ . '/../core/' . $class . '.php'; });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function renderLayout($value, string $view = 'page'): string {
    $page = ['title' => 'Example', 'page_layout' => $value];
    $pageId = new PageId('example'); $currentId = 'example';
    $siteName = 'Wiki'; $menu = []; $pageTree = []; $sidebarHtml = '<p>SIDEBAR_MARKER</p>';
    $assetUrl = fn($path) => '/assets/' . $path;
    $bodyHtml = '<p>ARTICLE_MARKER</p>';
    ob_start(); include __DIR__ . '/../templates/default/layout.php'; return ob_get_clean();
}
if (($argv[1] ?? '') === 'render') { echo renderLayout(json_decode($argv[2], true), $argv[3]); exit; }
function isolatedLayout($value, string $view = 'page'): string {
    $command = [PHP_BINARY, '-d', 'extension_dir=' . ini_get('extension_dir'), '-d', 'extension=mbstring', __FILE__, 'render', json_encode($value), $view];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $html = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($process) === 0 && $error === '', 'Template rendering: ' . $error);
    return $html;
}
foreach (['default', 'wide', 'fullscreen', 'unexpected', ['wide'], false] as $value) {
    $html = isolatedLayout($value);
    $expected = in_array($value, ['wide', 'fullscreen'], true) ? $value : 'default';
    check(str_contains($html, 'gbg-layout-' . $expected), 'Validated layout');
    check(str_contains($html, 'SIDEBAR_MARKER') === ($expected === 'default'), 'Sidebar visibility');
    check(str_contains($html, 'class="gbg-topbar"') === ($expected !== 'fullscreen'), 'Header visibility');
    check(str_contains($html, 'ARTICLE_MARKER'), 'Content preserved');
}
$html = isolatedLayout('fullscreen', 'edit');
check(str_contains($html, 'gbg-layout-default') && str_contains($html, 'SIDEBAR_MARKER'), 'Editor keeps navigation');
$page = ['page_layout' => 'fullscreen']; $bodyHtml = '<h1>Only content</h1>';
ob_start(); include __DIR__ . '/../templates/default/page.php'; $html = ob_get_clean();
check(str_contains($html, 'Only content') && !str_contains($html, 'gbg-page-tools') && !str_contains($html, 'article-metadata'), 'Content-only article');
echo "PASS page layouts, invalid values, sidebar, editor and content-only rendering\n";
