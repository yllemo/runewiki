<?php
spl_autoload_register(static function ($class) { $path = __DIR__ . '/../core/' . $class . '.php'; if (is_file($path)) require_once $path; });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$raw = <<<'MD'
---
name: Befintligt namn
title: Befintlig titel
tags: [wiki, "tagg, med komma", "false", 2026]
date: 2024-06-01
updated: 2025-01-02
status: Publicerad
draft: false
generated: { by: 'human:alice', at: 2026-06-20T22:53:05Z }
verified:
  - by: human:reviewer
    at: 2026-06-25T09:00:00Z
sources:
  - id: schema
    resource: https://example.com/schema
    title: "Schema: källa"
    last_modified: 2026-06-01T00:00:00Z
custom:
  nested: [one, two]
description: >
  En beskrivning som fortsätter
  på nästa rad.
---
# Artikelrubrik

Text.
MD;
[$meta, $body] = FrontMatter::parse($raw, true);
check($meta['name'] === 'Befintligt namn' && $meta['title'] === 'Befintlig titel', 'Names and title preserved');
check($meta['tags'] === ['wiki', 'tagg, med komma', 'false', '2026'], 'Tags retain strings and quoted commas');
check($meta['date'] === '2024-06-01' && $meta['updated'] === '2025-01-02', 'Old date strings preserved');
check($meta['generated']['at'] === '2026-06-20T22:53:05Z', 'OKF timestamp remains lexical string');
check($meta['sources'][0]['last_modified'] === '2026-06-01T00:00:00Z', 'Nested timestamp preserved');
check(str_contains($meta['description'], 'på nästa rad.'), 'Folded YAML supported');
[$again, $againBody] = FrontMatter::parse(FrontMatter::build($meta, $body), true);
check($again === $meta && trim($againBody) === trim($body), 'Semantic roundtrip preserves all fields');
check(PageLoader::displayTitle(new PageId('example'), $meta, $body) === 'Artikelrubrik', 'Existing H1 title priority preserved');
$stamped = Metadata::stamp($meta, $body, null, 'alice', '2026-09-27T12:00:00Z');
check($stamped['generated'] === ['by' => 'human:alice', 'at' => '2026-09-27T12:00:00Z'], 'Automatic timestamp and actor');
check($stamped['verified'] === $meta['verified'] && $stamped['name'] === $meta['name'] && $stamped['tags'] === $meta['tags'], 'Stamp leaves verification, names and tags alone');
$same = Metadata::stamp($stamped, $body, ['meta' => $stamped, 'body' => $body], 'bob', '2026-09-28T12:00:00Z');
check($same === $stamped, 'No-op save does not change timestamp or actor');
$changed = Metadata::stamp($stamped, $body . '\nNew content', ['meta' => $stamped, 'body' => $body], 'bob', '2026-09-28T12:00:00Z');
check($changed['generated']['by'] === 'human:bob' && $changed['generated']['at'] === '2026-09-28T12:00:00Z', 'Meaningful change updates timestamp');
check(Metadata::stamp([], 'x', null, null)['generated']['by'] === 'process:runewiki', 'Anonymous save does not claim named human provenance');
$settings = Metadata::settings(dirname(__DIR__));
$settings['visible_fields'] = ['name']; $settings['show_custom_fields'] = false; $settings['editor_fields'] = ['tags', 'generated'];
check(Metadata::visible($meta, $settings) === ['name' => 'Befintligt namn'], 'Visibility selection');
check(array_column(Metadata::snippets($settings), 'key') === ['tags', 'generated'], 'Editor selection');
check(!str_contains(Metadata::frontmatterSnippet($settings), 'title:'), 'Scaffold respects editor selection');
check(FrontMatter::parse("---\n---\nbody", true) === [[], 'body'], 'Empty frontmatter');
foreach (["title: [broken", "sources: !php/object 'O:8:evil'", "tags:\n  - {bad: mapping}"] as $bad) {
    $failed = false;
    try { FrontMatter::parse("---\n" . $bad . "\n---\nbody", true); } catch (InvalidArgumentException $e) { $failed = true; }
    check($failed, 'Invalid or unsafe YAML rejected without rewriting');
}
echo "PASS: YAML roundtrip, nested OKF, timestamps, names, tags, visibility and snippets\n";
check(str_contains(FrontMatter::build(['name' => 'Mitt svenska namn', 'tags' => []], 'Text'), "name: Mitt svenska namn\ntags: []\n"), 'First save uses plain name and empty tag list');
check(str_contains(FrontMatter::build(['tags' => ['wiki', 'arkitektur']], 'Text'), "tags:\n  - wiki\n  - arkitektur\n"), 'Nonempty tags remain a YAML sequence');
foreach (['true', 'null', '2026', '2026-10-03', 'Namn: exempel', ' leading', 'Svenskt namn'] as $name) {
    [$roundtrip] = FrontMatter::parse(FrontMatter::build(['name' => $name, 'tags' => []], 'Text'), true);
    check($roundtrip['name'] === $name && $roundtrip['tags'] === [], 'Names keep their string type and exact value');
}
echo "PASS: readable names and tag list formatting\n";
