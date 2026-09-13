<?php
/**
 * core/Wiki.php
 *
 * Motorns kärna. Bygger upp alla komponenter (PageLoader, Parser,
 * TemplateEngine, PluginManager, Cache, Search, Auth, History, Media),
 * låter Router tolka requesten och dispatchar till rätt handler.
 *
 * Anropas enbart från index.php i projektroten.
 */

class Wiki
{
    private array $config;
    private string $root;
    private PageLoader $pages;
    private Media $media;
    private Parser $parser;
    private TemplateEngine $templates;
    private PluginManager $plugins;
    private Cache $cache;
    private Search $search;
    private Auth $auth;
    private History $history;
    private NamespaceResolver $namespaces;
    private ?array $topbarMenu;
    private array $defaultMenu;
    private array $strings;

    public function __construct(array $config, string $rootDir)
    {
        $this->root   = rtrim($rootDir, '/');
        $this->config = $config;

        $contentDir   = $this->root . '/content';
        $mediaDir     = $this->root . '/' . ($config['media_dir'] ?? 'images');
        $templatesDir = $this->root . '/templates';
        $pluginsDir   = $this->root . '/plugins';
        $dataDir      = $this->root . '/data';

        $mediaConfig     = Helpers::loadConfig($this->root . '/config/media.php');
        $namespaceConfig = Helpers::loadConfig($this->root . '/config/namespaces.php');
        $interwikiConfig = Helpers::loadConfig($this->root . '/config/interwiki.php');
        $pluginConfig    = Helpers::loadConfig($this->root . '/config/plugins.php');

        // Menyprioritet (se menuFor()):
        //   1. content/_topbar.md — om den finns styr den ALLTID toppmenyn,
        //      site-wide, oavsett vad config/namespaces.php säger.
        //   2. config/namespaces.php — ett namespace kan ha en egen 'menu'-
        //      nyckel (se settingsFor()) som används när _topbar.md saknas.
        //   3. config/menu.php — global fallback-meny.
        $this->topbarMenu  = Helpers::loadTopbarMenu($contentDir);
        $this->defaultMenu = Helpers::loadConfig($this->root . '/config/menu.php');

        // Alla texter i temats header/footer (tagline, sidfotstexter, knapp-
        // etiketter m.m.) kan skrivas över i config/strings.php — se
        // Helpers::resolveStrings() / Helpers::defaultStrings().
        $this->strings = Helpers::resolveStrings($this->root, $config['site_name'] ?? 'RuneWiki');

        $this->plugins = new PluginManager();
        $this->plugins->loadEnabled($pluginConfig, $pluginsDir);

        $this->pages      = new PageLoader($contentDir);
        $this->media      = new Media($mediaDir, $mediaConfig);
        $this->parser     = new Parser($interwikiConfig, $this->pages, $this->plugins);
        $this->templates  = new TemplateEngine($templatesDir, $config['theme'] ?? 'default');
        $this->cache      = new Cache($dataDir . '/cache', (bool) ($config['cache_enabled'] ?? true));
        $this->search     = new Search($contentDir);
        $this->auth       = new Auth($dataDir . '/users/users.php', (bool) ($config['auth_enabled'] ?? false));
        $this->history    = new History($dataDir . '/history', (bool) ($config['history_enabled'] ?? false));
        $this->namespaces = new NamespaceResolver($namespaceConfig);
    }

    public function run(): void
    {
        $router  = new Router();
        $intent  = $router->resolve($_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET');

        echo match ($intent['action']) {
            'view'         => $this->handleView($intent['id']),
            'edit'         => $this->handleEdit($intent['id']),
            'save'         => $this->handleSave($intent['id']),
            'delete'       => $this->handleDelete($intent['id']),
            'search'       => $this->handleSearch($intent['query']),
            'media-upload' => $this->handleMediaUpload($intent['namespace']),
            'media-list'   => $this->handleMediaList($intent['namespace']),
            'download'     => $this->handleDownload($intent['id']),
            'login'        => $this->handleLogin($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'logout'       => $this->handleLogout(),
            default        => $this->handleView('start'),
        };
    }

    /**
     * Skickar en icke-inloggad besökare till inloggningsformuläret, med
     * $returnTo ihågkommet så de hamnar tillbaka där de försökte redigera
     * efter lyckad inloggning. Anropas i toppen av varje redigerande
     * handler (edit/save/delete/media-upload) — se Auth::canEdit().
     */
    private function requireLogin(string $returnTo): string
    {
        header('Location: /?do=login&redirect_to=' . rawurlencode($returnTo));
        return '';
    }

    /**
     * Löser vilken toppmeny som gäller för sidans namespace.
     *
     * content/_topbar.md (om den finns) styr ALLTID menyn, site-wide —
     * den ska kunna lita på att synas överallt oavsett per-namespace-
     * inställningar. Saknas den kan config/namespaces.php ge namespacet
     * en egen meny (nyckeln 'menu', samma format som config/menu.php);
     * annars faller den tillbaka på config/menu.php.
     */
    private function menuFor(string $namespace): array
    {
        if ($this->topbarMenu !== null) {
            return $this->topbarMenu;
        }

        $settings = $this->namespaces->settingsFor($namespace);
        return $settings['menu'] ?? $this->defaultMenu;
    }

    private function baseData(PageId $id, array $extra = []): array
    {
        $sidebarRaw = $this->pages->sidebar($id);
        return array_merge([
            'siteName'  => $this->config['site_name'] ?? 'RuneWiki',
            'lang'      => $this->config['language'] ?? 'sv',
            'menu'      => $this->menuFor($id->namespace()),
            'strings'   => $this->strings,
            'headerLogo'     => $this->config['header_logo'] ?? 'img/logo.svg',
            'headerLogoDark' => $this->config['header_logo_dark'] ?? '',
            'footerLogo'     => $this->config['footer_logo'] ?? 'img/logo.svg',
            'assetUrl'  => fn ($p) => $this->templates->assetUrl($p),
            'sidebarHtml' => $sidebarRaw ? $this->parser->toHtml($sidebarRaw) : '',
            'currentId' => $id->id(),
            'pageTree'  => Helpers::buildPageTree($this->pages),
            // Styr login/logout-UI:t i header.php — se Auth::canEdit().
            'authEnabled' => $this->auth->isEnabled(),
            'currentUser' => $this->auth->currentUser(),
        ], $extra);
    }

    /** Plockar ut texten från första # rubrik i Markdown-brödtexten. */
    private function extractTitleFromBody(string $body): ?string
    {
        if (preg_match('/^#\s+(.+?)(?:\s+#+\s*)?$/m', trim($body), $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function handleView(string $rawId): string
    {
        $id = new PageId($rawId);

        if (!$this->pages->exists($id)) {
            return $this->templates->render('layout', $this->baseData($id, [
                'view'    => 'missing',
                'page'    => ['title' => $id->title()],
                'pageId'  => $id,
                'bodyHtml' => $this->templates->render('missing', [
                    'pageId' => $id,
                ]),
            ]));
        }

        $filePath = $id->toFilePath($this->root . '/content');
        $cacheKey = 'page:' . $id->id() . ':' . filemtime($filePath);
        $bodyHtml = $this->cache->get($cacheKey);

        $page = $this->pages->load($id);
        if ($bodyHtml === null) {
            $bodyHtml = $this->parser->toHtml($page['body']);
            $this->cache->set($cacheKey, $bodyHtml);
        }

        // Hooks som alltid körs, även vid cache-träff
        $ctx = $this->plugins->trigger('page_view', [
            'id'        => $id->id(),
            'html'      => $bodyHtml,
            'page_meta' => $page['meta'],
            'file_path' => $filePath,
            'page'      => $page,
        ]);
        $bodyHtml = $ctx['html'] ?? $bodyHtml;

        $renderedPage = $this->templates->render('page', [
            'page'     => $page['meta'],
            'pageId'   => $id,
            'bodyHtml' => $bodyHtml,
            'strings'  => $this->strings,
        ]);

        return $this->templates->render('layout', $this->baseData($id, [
            'view'     => 'page',
            'page'     => array_merge(['title' => $id->title()], $page['meta']),
            'pageId'   => $id,
            'bodyHtml' => $renderedPage,
        ]));
    }

    /**
     * ?do=download — laddar ner sidans råa .md-fil (frontmatter + brödtext,
     * exakt som den ligger på disk). Läsning är alltid öppet, precis som
     * handleView(), så det här kräver ingen inloggning.
     */
    private function handleDownload(string $rawId): string
    {
        $id   = new PageId($rawId);
        $page = $this->pages->load($id);
        if ($page === null) {
            http_response_code(404);
            return 'Sidan hittades inte.';
        }

        $filename = str_replace(':', '_', $id->id()) . '.md';
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($page['raw']));
        return $page['raw'];
    }

    private function handleEdit(string $rawId): string
    {
        if (!$this->auth->canEdit()) {
            return $this->requireLogin($_SERVER['REQUEST_URI'] ?? '/');
        }

        $id = new PageId($rawId);
        $existing = $this->pages->load($id);

        $editForm = $this->templates->render('edit', [
            'pageId'    => $id,
            'body'      => $existing['raw'] ?? '',
            'isNew'     => $existing === null,
            'allPages'  => $this->pages->listAll(),
            // Platta ut [namespace => [id, ...]] till en enda lista — matar
            // {{-autokompletteringen och klistra-in-bild-uppladdningen i
            // editorn (se edit.php).
            'allMedia'  => array_merge(...array_values($this->media->listAllNamespaces())),
        ]);

        return $this->templates->render('layout', $this->baseData($id, [
            'view'     => 'edit',
            'page'     => ['title' => ($existing === null ? 'Skapa: ' : 'Redigera: ') . $id->title()],
            'pageId'   => $id,
            'bodyHtml' => $editForm,
        ]));
    }

    private function handleSave(string $rawId): string
    {
        if (!$this->auth->canEdit()) {
            return $this->requireLogin($_SERVER['REQUEST_URI'] ?? '/');
        }

        if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            $id = new PageId($rawId);
            return $this->templates->render('layout', $this->baseData($id, [
                'view'     => 'error',
                'page'     => ['title' => 'Åtkomst nekad'],
                'pageId'   => $id,
                'bodyHtml' => '<p>Ogiltig förfrågan — CSRF-token saknas eller stämmer inte.</p>',
            ]));
        }

        $id         = new PageId($rawId);
        $rawContent = $_POST['body'] ?? '';

        // Sparar man en helt tom fil (inget innehåll alls kvar, varken
        // frontmatter eller brödtext) tas sidan bort istället för att
        // spara en tom .md-fil — samma resultat som att radera sidan.
        if (trim($rawContent) === '') {
            if ($this->pages->exists($id)) {
                $existing = $this->pages->load($id);
                if ($existing) {
                    $this->history->snapshot($id, $existing['raw']);
                }
                $this->pages->delete($id);
                $this->cache->clear();
            }
            header('Location: /');
            return '';
        }

        // Storleksgräns: förhindra extremt stora sparningar
        if (strlen($rawContent) > 524288) { // 512 KB
            return $this->templates->render('layout', $this->baseData($id, [
                'view'     => 'error',
                'page'     => ['title' => 'Fel'],
                'pageId'   => $id,
                'bodyHtml' => '<p>Innehållet är för stort (max 512 KB).</p>',
            ]));
        }

        // Editorn skickar hela råfilen — separera frontmatter från brödtext
        [$postedMeta, $postedBody] = FrontMatter::parse($rawContent);

        // Filer vars filnamn börjar med "_" (_sidebar.md, _topbar.md) är
        // styrfiler, inte vanliga innehållssidor (se PageLoader::listAll()
        // som utesluter dem av samma anledning) — de har ingen egen titel
        // och tolkas dessutom rått (utan FrontMatter::parse) på sina
        // användningsställen (Helpers::loadTopbarMenu(), sidebarHtml i
        // Wiki::baseData()), så en auto-injicerad "title:"-frontmatter
        // skulle bara bli synligt skräp (t.ex. en <hr> + rubrik högst upp
        // i sidopanelen) istället för att faktiskt användas någonstans.
        $isSystemFile = str_starts_with(basename($id->toFilePath($this->root . '/content')), '_');

        // Titel: frontmatter-fält → första #-rubrik → sid-ID
        $title = $postedMeta['title']
            ?? $this->extractTitleFromBody($postedBody)
            ?? $id->title();

        $existing = $this->pages->load($id);
        if ($existing) {
            $this->history->snapshot($id, $existing['raw']);
        }

        $ctx        = $this->plugins->trigger('before_save', [
            'id'    => $id->id(),
            'title' => $title,
            'body'  => $postedBody,
            'meta'  => $postedMeta,
        ]);
        $title      = $ctx['title'] ?? $title;
        $postedBody = $ctx['body']  ?? $postedBody;

        // Styrfiler sparas exakt som skrivet — ingen auto-injicerad titel
        // (se kommentaren vid $isSystemFile ovan). Skrev man ändå en egen
        // "title:"-rad för hand behålls den, den skrivs bara inte över.
        $meta = $isSystemFile ? $postedMeta : array_merge($postedMeta, ['title' => $title]);
        $this->pages->save($id, $meta, $postedBody);

        // Rensa hela HTML-cachen: andra sidor kan länka till den här sidan
        // (röd länk → befintlig länk) eller vice versa. Cachen är annars
        // per sida och nyckeln baseras bara på den egna filens mtime, så
        // en sparning här skulle annars inte uppdatera röda länkar på
        // sidor som redan var cachade innan den här sidan skapades.
        $this->cache->clear();

        $this->plugins->trigger('after_save', [
            'id'    => $id->id(),
            'title' => $title,
            'body'  => $postedBody,
        ]);

        header('Location: ' . $id->url());
        return '';
    }

    private function handleDelete(string $rawId): string
    {
        if (!$this->auth->canEdit()) {
            return $this->requireLogin($_SERVER['REQUEST_URI'] ?? '/');
        }

        if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            $id = new PageId($rawId);
            return $this->templates->render('layout', $this->baseData($id, [
                'view'     => 'error',
                'page'     => ['title' => 'Åtkomst nekad'],
                'pageId'   => $id,
                'bodyHtml' => '<p>Ogiltig förfrågan — CSRF-token saknas eller stämmer inte.</p>',
            ]));
        }

        $id = new PageId($rawId);
        $this->pages->delete($id);
        $this->cache->clear(); // samma resonemang som i handleSave()
        header('Location: /');
        return '';
    }

    private function handleSearch(string $term): string
    {
        $id          = new PageId('start');
        $isTagSearch = str_starts_with($term, 'tag:');
        $tagName     = $isTagSearch ? trim(substr($term, 4)) : null;

        $results = $isTagSearch
            ? $this->search->queryByTag($tagName ?? '')
            : $this->search->query($term);

        // Erbjud "skapa sida" bara vid vanlig textsökning, inte tagg-sökning
        // — och bara om ingen sida med exakt det ID:t redan finns (annars
        // visas länken felaktigt även när söktermen råkar matcha en
        // befintlig sida, t.ex. sökning på "start").
        $createPageId  = null;
        $createPageUrl = null;
        if (!$isTagSearch) {
            $cleanTerm = trim($term, ': ');
            if ($cleanTerm !== '' && preg_match('/^[\pL\pN_\-.:]+$/u', $cleanTerm)) {
                $candidateId = new PageId($cleanTerm);
                if (!$this->pages->exists($candidateId)) {
                    $createPageId  = $candidateId->id();
                    $createPageUrl = $candidateId->editUrl();
                }
            }
        }

        $resultsHtml = $this->templates->render('search', [
            'term'          => $term,
            'tagName'       => $tagName,
            'results'       => $results,
            'createPageId'  => $createPageId,
            'createPageUrl' => $createPageUrl,
        ]);

        return $this->templates->render('layout', $this->baseData($id, [
            'view'     => 'search',
            'page'     => ['title' => 'Sökresultat: ' . $term],
            'pageId'   => $id,
            'bodyHtml' => $resultsHtml,
        ]));
    }

    /**
     * /?do=login — visar formuläret (GET) eller loggar in (POST). $redirect_to
     * (satt av requireLogin() eller av login-länken i header.php/media.php)
     * styr var man hamnar efter lyckad inloggning; saneras mot open redirect.
     */
    private function handleLogin(string $method): string
    {
        $id = new PageId('start');

        $redirectTo = (string) ($_REQUEST['redirect_to'] ?? '/');
        if (!str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//')) {
            $redirectTo = '/';
        }

        $error = null;
        if ($method === 'POST') {
            if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? null)) {
                $error = $this->strings['login_error_csrf'];
            } elseif ($this->auth->login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                header('Location: ' . $redirectTo);
                return '';
            } else {
                $error = $this->strings['login_error_credentials'];
            }
        }

        $loginHtml = $this->templates->render('login', [
            'redirectTo' => $redirectTo,
            'error'      => $error,
            'strings'    => $this->strings,
        ]);

        return $this->templates->render('layout', $this->baseData($id, [
            'view'     => 'login',
            'page'     => ['title' => $this->strings['login_title']],
            'pageId'   => $id,
            'bodyHtml' => $loginHtml,
        ]));
    }

    /** POST /?do=logout — CSRF-skyddad (skickas via en liten formulärknapp i header.php). */
    private function handleLogout(): string
    {
        if (Helpers::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->auth->logout();
        }
        header('Location: /');
        return '';
    }

    /**
     * $_POST['ajax'] === '1' (satt av editorns klistra-in-bild-funktion,
     * se edit.php) gör att svaret blir JSON istället för en redirect —
     * annars identiskt med det vanliga formuläret på /images.
     */
    private function handleMediaUpload(string $namespace): string
    {
        $isAjax = ($_POST['ajax'] ?? '') === '1';

        if (!$this->auth->canEdit()) {
            if ($isAjax) {
                return $this->jsonResponse(['ok' => false, 'error' => 'Inte inloggad.'], 401);
            }
            return $this->handleMediaList($namespace, 'Fel: Inte inloggad.');
        }

        if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? null)) {
            if ($isAjax) {
                return $this->jsonResponse(['ok' => false, 'error' => 'Ogiltig förfrågan (CSRF-token).'], 403);
            }
            return $this->handleMediaList($namespace, 'Fel: Ogiltig förfrågan (CSRF-token).');
        }

        try {
            $mediaId = $this->media->upload($_FILES['upload'] ?? [], $namespace);
            $message = 'Uppladdad: ' . $mediaId->id();
        } catch (\Throwable $e) {
            $message = 'Fel: ' . $e->getMessage();
        }

        if ($isAjax) {
            return isset($mediaId)
                ? $this->jsonResponse(['ok' => true, 'id' => $mediaId->id(), 'url' => $mediaId->url()])
                : $this->jsonResponse(['ok' => false, 'error' => $message], 422);
        }

        // Visa resultatet direkt tillsammans med det uppdaterade galleriet.
        return $this->handleMediaList($namespace, $message);
    }

    /** @param array<string,mixed> $data */
    private function jsonResponse(array $data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * /images (utan namespace i URL:en) visar en global översikt över ALLA
     * namespaces med media på en gång; /images/<namespace> visar (och
     * laddar upp till) bara det namespacet. Uppladdning kräver inloggning
     * när auth_enabled = true (canUpload skickas till temat, som döljer
     * formuläret annars) — läsning/bläddring är alltid öppet.
     */
    private function handleMediaList(string $namespace, ?string $uploadMessage = null): string
    {
        // /images/<namespace>/<fil> (eller /images/<fil> i roten) pekar
        // egentligen på en SPECIFIK, redan uppladdad fil — inte en
        // namespace-listning. Router.php kan inte skilja de två åt (den
        // gör bara om "/" till ":" och skickar hit resten), så det är
        // egentligen webbserverns jobb att fånga riktiga filer under
        // images/ innan de ens når PHP (.htaccess gör det för Apache).
        // Men PHPs inbyggda utvecklingsserver (`php -S ... index.php`,
        // se README) — eller en webbserver där .htaccess/mod_rewrite av
        // någon anledning inte läses — skickar ALLTID hit sådana
        // förfrågningar, och skulle utan detta alltid visa en (troligen
        // tom) namespace-lista istället för att returnera bildens rådata,
        // vilket gör att uppladdade bilder aldrig syns. Skyddsnät: om
        // "namespace" faktiskt matchar en existerande fil, strömma den
        // filen direkt istället för att rendera galleriet.
        try {
            $maybeFile = new MediaId($namespace);
            if ($this->media->exists($maybeFile)) {
                $this->serveMediaFile($maybeFile);
                return '';
            }
        } catch (\Throwable) {
            // Tomt/ogiltigt medie-ID (t.ex. /images-roten) — fortsätt som listning.
        }

        // Berikar varje ID med MediaId, om det är en bild (miniatyr kontra
        // filikon i /images) och filstorlek — så temat slipper bygga om det.
        $enrich = function (string $fileId): array {
            $mediaId = new MediaId($fileId);
            return [
                'id'      => $fileId,
                'mediaId' => $mediaId,
                'isImage' => $mediaId->isImage(),
                'size'    => $this->media->filesize($mediaId),
            ];
        };

        if ($namespace === '') {
            $groupedFiles = array_map(fn (array $ids) => array_map($enrich, $ids), $this->media->listAllNamespaces());
            $title = 'Mediahanterare — alla namespaces';
        } else {
            $groupedFiles = [$namespace => array_map($enrich, $this->media->listNamespace($namespace))];
            $title = 'Mediahanterare: ' . $namespace;
        }

        $id = new PageId('start');

        $listHtml = $this->templates->render('media', [
            'namespace'     => $namespace,
            'groupedFiles'  => $groupedFiles,
            'canUpload'     => $this->auth->canEdit(),
            'strings'       => $this->strings,
            'uploadMessage' => $uploadMessage,
        ]);

        return $this->templates->render('layout', $this->baseData($id, [
            'view'     => 'media',
            'page'     => ['title' => $title],
            'pageId'   => $id,
            'bodyHtml' => $listHtml,
        ]));
    }

    /**
     * Strömmar en mediefils rådata direkt (rätt Content-Type, ingen
     * mall/layout runt) — se anropet/kommentaren i handleMediaList().
     */
    private function serveMediaFile(MediaId $id): void
    {
        $path = $this->media->absolutePath($id);
        $mime = (is_file($path) ? @mime_content_type($path) : false) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
    }
}
