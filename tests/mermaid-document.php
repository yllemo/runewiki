<?php
require __DIR__ . '/../core/MermaidDocument.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$raw = "---\ntitle: Keep\n---\nBefore\n```mermaid\nflowchart LR\nA-->B\n```\nAfter\n";
check(MermaidDocument::replace($raw, "flowchart LR\nA-->B", "flowchart LR\nA-->C") === str_replace('A-->B', 'A-->C', $raw), 'Only block replaced');
check(str_contains(MermaidDocument::replace(str_replace("\n", "\r\n", $raw), "flowchart LR\nA-->B", "flowchart LR\nA-->C"), "A-->C\r\n```"), 'CRLF preserved');
foreach ([[$raw.$raw, "flowchart LR\nA-->B", 'flowchart LR'], [$raw, 'missing', 'flowchart LR'], [$raw, "flowchart LR\nA-->B", "```\n# injected"], ["````text\n".$raw."````\n", "flowchart LR\nA-->B", 'flowchart LR']] as $args) {
    try { MermaidDocument::replace(...$args); throw new RuntimeException('Invalid replacement accepted'); }
    catch (InvalidArgumentException $expected) {}
}
echo "PASS Mermaid block replacement, duplicate/stale rejection, fence injection and nested code\n";
