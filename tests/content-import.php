<?php
require_once __DIR__ . '/../core/ContentImport.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(8));
mkdir($root);
function archiveFile(string $path, array $entries): void {
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entries as $name => $data) $zip->addFromString($name, $data);
    $zip->close();
}
try {
    archiveFile($root . '/export.zip', ['content/start.md' => '# Start', 'content/projekt/api.start.md' => "---\ntags: [test]\n---\nText", 'content/_topbar.md' => '- Meny']);
    $result = ContentImport::run($root . '/export.zip', $root . '/content');
    check($result['imported'] === 3, 'All exported files imported');
    check(file_get_contents($root . '/content/projekt/api.start.md') === "---\ntags: [test]\n---\nText", 'Nested path and exact bytes preserved');
    check(ContentImport::run($root . '/export.zip', $root . '/content')['skipped'] === 3, 'Existing files preserved');
    foreach (['content/../escape.md', 'content/a/../../escape.md', '/content/start.md', 'config/config.php', 'content/a\\b.md'] as $bad) {
        archiveFile($root . '/bad.zip', ['content/new.md' => 'Never written', $bad => 'Bad']);
        $rejected = false;
        try { ContentImport::run($root . '/bad.zip', $root . '/content'); } catch (RuntimeException $e) { $rejected = true; }
        check($rejected && !file_exists($root . '/content/new.md'), 'Invalid archive rejected before writes');
    }
    echo "PASS: ZIP import paths, content, conflict preservation and unsafe archive rejection\n";
} finally {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
