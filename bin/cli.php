#!/usr/bin/env php
<?php
/**
 * bin/cli.php — RuneWiki underhållsverktyg
 *
 * Körs som: php bin/cli.php <kommando>
 *
 * Kommandon:
 *   clear-cache          Tömmer render-cache (data/cache/)
 *   list-pages           Listar alla sid-ID:n i content/
 *   create-user <namn>   Skapar/uppdaterar en inloggningsanvändare
 *   delete-user <namn>   Tar bort en inloggningsanvändare
 *   list-users           Listar alla inloggningsanvändare
 *   help                 Visar denna hjälptext
 */

if (PHP_SAPI !== 'cli') {
    exit("Får bara köras från kommandoraden.\n");
}

$root = dirname(__DIR__);

spl_autoload_register(function (string $class) use ($root) {
    $path = $root . '/core/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$config  = require $root . '/config/config.php';
$command = $argv[1] ?? 'help';

match ($command) {
    'clear-cache'  => cmdClearCache($root, $config),
    'list-pages'   => cmdListPages($root),
    'create-user'  => cmdCreateUser($root, $argv[2] ?? ''),
    'delete-user'  => cmdDeleteUser($root, $argv[2] ?? ''),
    'list-users'   => cmdListUsers($root),
    'help'         => cmdHelp(),
    default        => (function () use ($command) {
        echo "Okänt kommando: {$command}\n";
        cmdHelp();
        exit(1);
    })(),
};

function cmdClearCache(string $root, array $config): void
{
    if (empty($config['cache_enabled'])) {
        echo "Cache är inaktiverad i config — inget att tömma.\n";
        return;
    }
    $cache = new Cache($root . '/data/cache', true);
    $cache->clear();
    echo "Cache tömd.\n";
}

function cmdListPages(string $root): void
{
    $loader = new PageLoader($root . '/content');
    $pages  = $loader->listAll();
    if (empty($pages)) {
        echo "Inga sidor hittades i content/.\n";
        return;
    }
    foreach ($pages as $id) {
        echo $id . "\n";
    }
    echo "\n" . count($pages) . " sida(or) totalt.\n";
}

/** Auth-instans mot data/users/users.php — samma fil/format som core/Wiki.php använder. */
function authFor(string $root): Auth
{
    return new Auth($root . '/data/users/users.php', true);
}

/** Läser ett lösenord från terminalen utan att eka det (Linux/macOS via stty; syns på Windows). */
function readPasswordHidden(string $prompt): string
{
    echo $prompt;
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    if (!$isWindows) {
        system('stty -echo');
    }
    $password = trim((string) fgets(STDIN));
    if (!$isWindows) {
        system('stty echo');
        echo "\n";
    }
    return $password;
}

function cmdCreateUser(string $root, string $username): void
{
    $username = trim($username);
    if ($username === '') {
        fwrite(STDERR, "Användning: php bin/cli.php create-user <användarnamn>\n");
        exit(1);
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        fwrite(STDERR, "Ogiltigt användarnamn — tillåtna tecken: a-z A-Z 0-9 _ . -\n");
        exit(1);
    }

    $auth  = authFor($root);
    $isNew = !$auth->userExists($username);

    $password = readPasswordHidden("Lösenord för \"{$username}\" (minst 8 tecken): ");
    if (mb_strlen($password) < 8) {
        fwrite(STDERR, "Lösenordet måste vara minst 8 tecken. Ingen ändring gjord.\n");
        exit(1);
    }
    $confirm = readPasswordHidden('Bekräfta lösenord: ');
    if ($password !== $confirm) {
        fwrite(STDERR, "Lösenorden matchade inte. Ingen ändring gjord.\n");
        exit(1);
    }

    if (!$auth->setPassword($username, $password)) {
        fwrite(STDERR, "Kunde inte skriva till data/users/users.php (rättigheter?).\n");
        exit(1);
    }

    echo ($isNew ? "Skapade" : "Uppdaterade lösenordet för") . " användaren \"{$username}\" (sparat som hash).\n";
    if (empty($GLOBALS['config']['auth_enabled'])) {
        echo "OBS: 'auth_enabled' är false i config/config.php — sätt den till true för att kräva inloggning vid redigering.\n";
    }
}

function cmdDeleteUser(string $root, string $username): void
{
    $username = trim($username);
    if ($username === '') {
        fwrite(STDERR, "Användning: php bin/cli.php delete-user <användarnamn>\n");
        exit(1);
    }

    if (!authFor($root)->deleteUser($username)) {
        echo "Ingen användare med namnet \"{$username}\" hittades.\n";
        return;
    }
    echo "Tog bort användaren \"{$username}\".\n";
}

function cmdListUsers(string $root): void
{
    $auth  = authFor($root);
    $users = $auth->listUsernames();
    if (empty($users)) {
        echo "Inga användare skapade ännu. Kör: php bin/cli.php create-user <användarnamn>\n";
        return;
    }
    foreach ($users as $username) {
        $flag = $auth->hasPlaintextPassword($username) ? '  (KLARTEXT — kör create-user för att hasha)' : '';
        echo $username . $flag . "\n";
    }
    echo "\n" . count($users) . " användare totalt.\n";
}

function cmdHelp(): void
{
    echo <<<HELP
RuneWiki CLI — underhållsverktyg

Användning:
  php bin/cli.php <kommando>

Kommandon:
  clear-cache          Tömmer render-cache i data/cache/
  list-pages           Listar alla sid-ID:n i content/ (ett per rad)
  create-user <namn>   Skapar/uppdaterar en inloggningsanvändare (lösenord
                        frågas interaktivt) i data/users/users.php
  delete-user <namn>   Tar bort en inloggningsanvändare
  list-users           Listar alla inloggningsanvändare
  help                 Visar denna hjälptext

Sätt 'auth_enabled' => true i config/config.php för att kräva inloggning
vid redigering/spara/radera/media-uppladdning — läsning är alltid öppet.

HELP;
}
