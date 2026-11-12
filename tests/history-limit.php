<?php
spl_autoload_register(static function ($class) { require_once __DIR__ . '/../core/' . $class . '.php'; });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$root = sys_get_temp_dir() . '/wiki-history-' . bin2hex(random_bytes(8));
mkdir($root);
try {
    $id = new PageId('example');
    foreach ([null => 10, 3 => 3, 0 => 10, -1 => 10] as $limit => $expected) {
        $dir = $root . '/' . ($limit === '' ? 'default' : $limit);
        $history = $limit === '' ? new History($dir, true) : new History($dir, true, (int) $limit);
        for ($i = 0; $i < 15; $i++) $history->snapshot($id, 'Version ' . $i);
        $revisions = $history->revisions($id);
        check(count($revisions) === $expected, 'Retention count');
        check($history->read($id, end($revisions)) === 'Version 14', 'Newest revision retained');
        check($history->read($id, $revisions[0]) === 'Version ' . (15 - $expected), 'Oldest excess revisions removed');
    }
    $history = new History($root . '/forced', false, 2);
    $history->snapshot($id, 'Ignored');
    check($history->revisions($id) === [], 'Disabled history remains disabled');
    for ($i = 0; $i < 5; $i++) $history->snapshot($id, 'Forced ' . $i, true);
    check(count($history->revisions($id)) === 2, 'Forced snapshots honor retention');
    $legacyDir = $root . '/legacy/' . hash('sha256', $id->id()); mkdir($legacyDir, 0700, true);
    $legacy = '20200101-000000-0123456789abcdef.md'; file_put_contents($legacyDir . '/' . $legacy, 'Legacy');
    $history = new History($root . '/legacy', true, 1);
    check($history->read($id, $legacy) === 'Legacy', 'Old filenames remain readable');
    $history->snapshot($id, 'Latest');
    check(!is_file($legacyDir . '/' . $legacy), 'Old format included in pruning');
    $parser = new Parser();
    $html = $parser->toHtml('# Heading', false);
    check(str_contains($html, 'id="heading"') && !str_contains($html, 'wiki-heading-anchor'), 'Reader keeps bookmark but omits icon');
    check(str_contains($parser->toHtml('# Heading'), 'wiki-heading-anchor'), 'Normal article keeps icon');
    echo "PASS: history default/configured limits, latest retention, legacy files and reader anchors\n";
} finally {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
