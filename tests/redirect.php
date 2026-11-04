<?php
require_once __DIR__ . '/../core/PluginInterface.php';
require_once __DIR__ . '/../core/PluginManager.php';
require_once __DIR__ . '/../plugins/redirect/plugin.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
foreach (['https://example.com/path?q=1#part', '/new-page', '/project/page'] as $url) {
    check(RedirectPlugin::target($url, '/old-page') === $url, 'Accept valid URL');
}
foreach (['', '//evil.example', '/\\evil.example', "https://example.com\r\nX-Test: bad", '/%0d%0aevil', 'javascript:alert(1)', 'data:text/html,test', 'https://user:pass@example.com', '/old-page?x=1', ['invalid']] as $url) {
    check(RedirectPlugin::target($url, '/old-page') === null, 'Reject unsafe or self redirect');
}
foreach ([true, false] as $enabled) {
    $manager = new PluginManager();
    $manager->loadEnabled(['redirect' => ['enabled' => $enabled]], dirname(__DIR__) . '/plugins');
    $result = $manager->trigger('page_redirect', ['url' => '/old-page', 'page' => ['meta' => ['redirect' => '/new-page']]]);
    check(isset($result['redirect_url']) === $enabled, 'Plugin activation controls redirect');
}
echo "PASS: redirect URLs, unsafe schemes, header injection and activation\n";
