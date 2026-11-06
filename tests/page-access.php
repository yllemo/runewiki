<?php
spl_autoload_register(static function ($class) { require_once __DIR__ . '/../core/' . $class . '.php'; });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$root = sys_get_temp_dir() . '/wiki-page-access-' . bin2hex(random_bytes(8));
mkdir($root);
try {
    foreach ([
        '# Public' => true,
        "---\nrequire_login: false\n---\nPublic" => true,
        "---\nrequire_login: true\n---\nSecret" => false,
        "---\nrequire_login: 'false'\n---\nSecret" => false,
        "---\nrequire_login: null\n---\nSecret" => false,
        "---\nrequire_login: [true]\n---\nSecret" => false,
        "---\nrequire_login: true\nrequire_login: false\n---\nSecret" => false,
        "---\nrequire_login: true\ninvalid: [\n---\nSecret" => false,
        "---\nrequire_login: true\nSecret" => false,
        "\xEF\xBB\xBF---\r\nrequire_login: true\r\n---\r\nSecret" => false,
    ] as $raw => $allowed) {
        file_put_contents($root . '/test.md', $raw);
        check(PageAccess::canReadFile($root . '/test.md', false) === $allowed, 'File policy matches expected guest access');
        check(PageAccess::canReadRaw($raw, false) === $allowed, 'Raw policy matches file policy');
        check(PageAccess::canReadFile($root . '/test.md', true), 'Members can read/repair frontmatter');
    }
    file_put_contents($root . '/test.md', "---\nrequire_login: true\ntags: [private-tag]\n---\n# Secret title\nSecret body");
    $pages = new PageLoader($root, fn ($id) => PageAccess::canReadFile($id->toFilePath($root), false));
    $id = new PageId('test');
    check(!$pages->exists($id) && $pages->load($id) === null && $pages->listAll() === [], 'Loader hides protected pages');
    $parser = new Parser([], $pages, null, null, fn () => false);
    foreach (['[[test]]', '[Vald länktext](/test)'] as $link) {
        $html = $parser->toHtml($link);
        check(str_contains($html, 'wikilink-locked') && !str_contains($html, 'wikilink-new'), 'Existing protected link is locked, not missing');
        check(str_contains($html, 'Inloggning krävs') && str_contains($html, 'href="/test"'), 'Locked link explains login and targets article');
        check(!str_contains($html, 'Secret'), 'Locked link does not expose protected title/body');
    }
    check(str_contains($parser->toHtml('[[missing]]'), 'wikilink-new'), 'Missing links remain marked missing');
    $memberParser = new Parser([], new PageLoader($root), null, null, fn () => true);
    check(!str_contains($memberParser->toHtml('[[test]]'), 'wikilink-locked') && str_contains($memberParser->toHtml('[[test]]'), 'Secret title'), 'Members see normal page title');
    $search = new Search($root, $pages);
    check($search->query('test') === [] && $search->query('Secret') === [] && $search->queryByTag('private-tag') === [] && $search->queryByNamespace('') === [], 'All search modes hide protected pages');
    $denied = false;
    try { $pages->saveRaw($id, '# Public replacement'); } catch (RuntimeException) { $denied = true; }
    check($denied && !$pages->delete($id), 'Cannot replace or delete protected page through guest loader');
    echo "PASS: page login policy, malformed YAML, loader, search and write protection\n";
} finally { unlink($root . '/test.md'); rmdir($root); }
