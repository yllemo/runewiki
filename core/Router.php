<?php
/**
 * core/Router.php
 *
 * Tolkar inkommande request (URL + ?do=-parameter) till en enkel
 * "intent" som Wiki.php sedan agerar på: visa sida, redigera, spara,
 * ladda upp media eller söka.
 *
 * URL-schema (snygga URL:er):
 *   /namespace/page1/start        -> visa sidan "namespace:page1:start"
 *   /namespace/page1/start?do=edit -> redigera (eller skapa om saknas)
 *   POST /namespace/page1/start?do=save -> spara
 *   /namespace/page1/start?do=download -> ladda ner sidans .md-fil
 *   POST /images/<namespace>?do=upload  -> ladda upp fil till namespace
 *   /?do=search&q=sökterm          -> sökresultat
 *   /?do=login                     -> inloggningsformulär (GET/POST)
 *   POST /?do=logout                -> loggar ut
 *
 * /admin/ och /chat/ har egna front controllers (admin/index.php,
 * chat/index.php) och routas INTE genom den här klassen — se .htaccess.
 */

class Router
{
    /** @return array{action:string, id:?string, namespace:string, query:string} */
    public function resolve(string $requestUri, string $method): array
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        parse_str(parse_url($requestUri, PHP_URL_QUERY) ?? '', $queryParams);

        $do = $queryParams['do'] ?? 'view';
        $path = trim($path, '/');

        if ($path === 'images' || str_starts_with($path, 'images/')) {
            $namespace = trim(substr($path, 6), '/');
            $namespace = str_replace('/', ':', $namespace);
            return [
                'action'    => $method === 'POST' ? 'media-upload' : 'media-list',
                'id'        => null,
                'namespace' => $namespace,
                'query'     => '',
            ];
        }

        if ($do === 'search') {
            return [
                'action'    => 'search',
                'id'        => null,
                'namespace' => '',
                'query'     => $queryParams['q'] ?? '',
            ];
        }

        // Inloggning/utloggning är globala, inte kopplade till ett sid-ID —
        // fungerar oavsett sökväg, precis som ?do=search. Utloggning kräver
        // POST (skickas via en knapp med CSRF-token, se header.php).
        if ($do === 'login') {
            return [
                'action'    => 'login',
                'id'        => null,
                'namespace' => '',
                'query'     => '',
            ];
        }
        if ($do === 'logout' && $method === 'POST') {
            return [
                'action'    => 'logout',
                'id'        => null,
                'namespace' => '',
                'query'     => '',
            ];
        }
        $id = $path === '' ? 'start' : str_replace('/', ':', $path);

        $action = match (true) {
            $do === 'save' && $method === 'POST' => 'save',
            $do === 'delete' && $method === 'POST' => 'delete',
            $do === 'edit'  => 'edit',
            $do === 'download' => 'download',
            default         => 'view',
        };

        return [
            'action'    => $action,
            'id'        => $id,
            'namespace' => '',
            'query'     => '',
        ];
    }
}
