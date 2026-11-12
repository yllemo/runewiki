<?php
spl_autoload_register(static function ($class) { require_once __DIR__ . '/../core/' . $class . '.php'; });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$parser = new Parser();
$html = $parser->toHtml("# Översikt och mål\n\n## **Installation**\n\n## Installation\n\n## Installation-2\n\n### Installation\n\n```md\n# Ingen rubrik\n```\n\n<ifAuth>\n## Hemligt\n</ifAuth>");
foreach (['översikt-och-mål', 'installation', 'installation-2', 'installation-2-2', 'installation-3'] as $id) {
    check(substr_count($html, 'id="' . $id . '"') === 1, 'Unique heading anchor: ' . $id);
    check(str_contains($html, 'href="#' . rawurlencode($id) . '"'), 'Matching permalink');
}
check(!str_contains($html, 'id="ingen-rubrik"') && !str_contains($html, 'hemligt'), 'No code or hidden-content anchors');
check(str_contains($parser->toHtml('# Installation'), 'id="installation"'), 'Anchor state resets per document');
foreach (['[[guide#installation|Installera]]', '[Installera](/guide#installation)'] as $link) {
    $html = $parser->toHtml($link);
    check(str_contains($html, 'href="/guide#installation"') && !str_contains($html, '/guideinstallation'), 'Page fragment preserved');
}
check(str_contains($parser->toHtml('[[#installation|Hit]]'), 'href="#installation"'), 'Local wiki bookmark');
check(str_contains($parser->toHtml('[Hit](#installation)'), 'href="#installation"'), 'Local Markdown bookmark');
echo "PASS: heading IDs, collisions, Unicode, hidden/code blocks and wiki/Markdown fragments\n";
