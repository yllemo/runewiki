<?php
// Run with ext-zip. Keep the OpenShift memory limit throughout fixture creation and import.
ini_set('memory_limit', '128M');
require_once __DIR__ . '/../core/ContentImport.php';
$root = sys_get_temp_dir() . '/wiki-import-memory-' . bin2hex(random_bytes(8));
mkdir($root);
try {
    $source = fopen($root . '/source.bin', 'wb');
    $chunk = str_repeat('0123456789abcdef', 4096);
    for ($i = 0; $i < 512; $i++) fwrite($source, $chunk);
    fclose($source);
    unset($chunk);
    $expected = hash_file('sha256', $root . '/source.bin');
    $zip = new ZipArchive();
    $zip->open($root . '/export.zip', ZipArchive::CREATE);
    for ($i = 0; $i < 5; $i++) $zip->addFile($root . '/source.bin', 'content/files/' . $i . '.bin');
    $zip->addFromString('content/empty.md', '');
    $zip->close();
    // Leave less memory free than one 32 MiB entry, as in an already-running app.
    $ballast = str_repeat('x', 100 * 1024 * 1024);
    $baseline = memory_get_usage(true);
    foreach (['single', 'paged'] as $mode) {
        $offset = 0;
        do {
            $result = ContentImport::run($root . '/export.zip', $root . '/' . $mode, $offset, $mode === 'paged' ? 1 : PHP_INT_MAX);
            $offset = $result['next'];
        } while (!$result['done']);
        for ($i = 0; $i < 5; $i++) {
            if (hash_file('sha256', $root . '/' . $mode . '/files/' . $i . '.bin') !== $expected) throw new RuntimeException('Imported bytes differ');
        }
        if (filesize($root . '/' . $mode . '/empty.md') !== 0) throw new RuntimeException('Empty file failed');
    }
    $extra = memory_get_peak_usage(true) - $baseline;
    if ($extra > 8 * 1024 * 1024) throw new RuntimeException('Import buffers exceed 8 MiB');
    printf("PASS: single and paged imports of 160 MiB, 100 MiB app memory, 128 MiB limit; extra peak %.2f MiB\n", $extra / 1048576);
} finally {
    unset($ballast);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
