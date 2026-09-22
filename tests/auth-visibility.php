<?php
// Run with: php tests/auth-visibility.php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/PageId.php';
require_once __DIR__ . '/../core/Parser.php';
require_once __DIR__ . '/../core/PluginInterface.php';
require_once __DIR__ . '/../plugins/byrakrazy/plugin.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$loggedIn = false;
$parser = new Parser(isAuthenticated: function () use (&$loggedIn): bool { return $loggedIn; });
$markdown = "Public before\n<ifAuth>\n**Members only**\n<ifAuth>\nNested private\n</ifAuth>\nStill private\n</ifAuth>\nPublic after";
$html = $parser->toHtml($markdown);
check(str_contains($html, 'Public before') && str_contains($html, 'Public after'), 'Public content must remain.');
check(!str_contains($html, 'Members') && !str_contains($html, 'private'), 'Anonymous output must omit nested private content.');
$loggedIn = true;
$html = $parser->toHtml($markdown);
check(str_contains($html, '<strong>Members only</strong>') && str_contains($html, 'Nested private') && str_contains($html, 'Still private'), 'Authenticated content must render Markdown.');
check(!str_contains($html, 'ifAuth'), 'Control markers must not render.');
$loggedIn = false;
check(!str_contains($parser->toHtml("Public\r\n<IFAUTH>\r\nSecret"), 'Secret'), 'Unclosed blocks and CRLF must fail closed.');
check(!str_contains((new Parser())->toHtml("<ifAuth>\nSecret\n</ifAuth>"), 'Secret'), 'Missing auth context must default to anonymous.');
check(str_contains($parser->toHtml("```markdown\n<ifAuth>\nExample\n</ifAuth>\n```"), '&lt;ifAuth&gt;'), 'Fenced examples must remain literal.');
check(!str_contains($parser->toHtml("<ifAuth>\n```text\nSecret\n```\n</ifAuth>"), 'Secret'), 'Code inside auth blocks must be hidden.');

$_SESSION['csrf_token'] = 'test-token';
$plugin = new ByrakrazyPlugin();
$forms = "Public\n<form>\naction pagemod . add\ntextbox \"First\"\n</form>\n<form>\naction pagemod . add\ntextbox \"Second\"\n</form>";
foreach ([true, false, true] as $loggedIn) {
    $ctx = $plugin->prepare(['id' => 'start', 'markdown' => $forms, 'authenticated' => $loggedIn]);
    $html = $plugin->render(['id' => 'start', 'html' => $parser->toHtml($ctx['markdown'])])['html'];
    check(str_contains($html, 'Public'), 'Public text must survive form preparation.');
    check(substr_count($html, '<form ') === ($loggedIn ? 2 : 0), 'Forms must follow current login state without stale output.');
    check(!str_contains($html, 'BYRAKRAZYFORM'), 'Form placeholders must not leak.');
    if ($loggedIn) check(str_contains($html, 'name="byrakrazy_form" value="1"'), 'Form indexes must remain stable.');
    else check(!str_contains($html, 'textbox') && !str_contains($html, 'csrf_token'), 'Anonymous output must omit form definitions and CSRF fields.');
}
echo "Auth visibility checks passed.\n";
