<?php
/**
 * index.php
 *
 * Front controller — ligger i projektets rot (webroot = hela projektet).
 * Registrerar en enkel autoloader för core/ (inget Composer-beroende
 * krävs) och startar Wiki-kärnan.
 *
 * Källkod, innehåll och data skyddas från direktåtkomst via .htaccess;
 * endast index.php själv, media/ och mall-assets (templates/<tema>/assets)
 * nås direkt av webbservern.
 */

$root = __DIR__;

// /chat och /admin har egna front controllers (chat/index.php — AI-chatt
// med skills från /skills; admin/index.php — adminpanel, gate:ar sig själv
// på inloggning). Under Apache routas de dit direkt av .htaccess och når
// aldrig hit, men PHP:s inbyggda server (php -S ... index.php) skickar ALLA
// requests genom den här filen oavsett sökväg, så delegeringen behövs här
// också för att de ska fungera i den lokala utvecklingsservern.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// PHPs inbyggda server körs med index.php som ROUTERSKRIPT (inte bara en
// docroot) — den kör då ALLTID hela den här filen för varje request, även
// för en riktig fil under media/ eller ett temas assets/-mapp, som annars
// (t.ex. under Apache/.htaccess) skulle nå webbservern direkt utan att
// gå via PHP alls. Utan denna `return false` skulle Wiki::run() nedan
// köras även för sådana filer och (för media/) tolka filnamnet som ett
// namespace istället för att returnera filens rådata — precis det som
// gör att uppladdade bilder aldrig visas lokalt. Se även skyddsnätet i
// Wiki::handleMediaList() för fallet där man ändå kör utan denna bypass
// (t.ex. Apache med en webbserver som av någon anledning inte läser
// .htaccess).
if (PHP_SAPI === 'cli-server' && preg_match('#^/(media/|templates/[^/]+/assets/)#', $requestPath) && is_file($root . $requestPath)) {
    return false; // låt PHPs inbyggda server servera filen som den är
}

foreach (['/chat' => 'chat/index.php', '/admin' => 'admin/index.php'] as $prefix => $controller) {
    if ($requestPath === $prefix || str_starts_with($requestPath, $prefix . '/')) {
        require $root . '/' . $controller;
        return;
    }
}

spl_autoload_register(function (string $class) use ($root) {
    $path = $root . '/core/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$config = require $root . '/config/config.php';

$wiki = new Wiki($config, $root);
$wiki->run();
