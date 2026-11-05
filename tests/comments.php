<?php
require_once __DIR__ . '/../core/Comments.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$items = Comments::add([], ['text' => '<script>alert(1)</script>', 'name' => 'Guest', 'quote' => 'Public'], null, 'Public', 'Public');
$id = $items[0]['id'];
$items = Comments::add($items, ['text' => 'Private discussion', 'quote' => 'Secret'], 'alice', 'Public Secret', 'Public');
check(count(Comments::visible($items, false)) === 1, 'Private text comments hidden from guests');
$items = Comments::add($items, ['text' => 'Reply', 'parent' => $id], null, 'Public', 'Public');
check($items[2]['quote'] === 'Public' && $items[2]['parent'] === $id, 'Reply inherits anchor');
$items = Comments::moderate($items, $id, 'resolve');
check($items[0]['resolved'], 'Resolve thread');
$items = Comments::moderate($items, $id, 'delete');
check(count($items) === 1, 'Delete removes thread and replies');
$root = sys_get_temp_dir() . '/wiki-comments-' . bin2hex(random_bytes(8));
try {
    $store = new Comments($root);
    $store->change('project:start', fn ($old) => $items);
    check($store->read('project:start') === $items && $store->read('other') === [], 'Comments persist per page');
    echo "PASS: comment threads, privacy, moderation and persistence\n";
} finally { foreach (glob($root . '/*') ?: [] as $file) unlink($file); if(is_dir($root))rmdir($root); }
