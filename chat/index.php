<?php
/**
 * chat/index.php
 *
 * Klientdriven AI-chatt, portad från html/chat.html med exakt samma
 * funktioner (filuppladdning/.zip/mapp, streaming-anrop mot OpenAI-
 * kompatibel/LM Studio/Ollama, inställningar i webbläsarens IndexedDB,
 * Markdown/Mermaid-rendering, filförhandsvisning, export till Markdown
 * m.m.) — men med två viktiga skillnader mot originalet:
 *
 *  1. Sidan återanvänder AKTIVT temas riktiga header.php/footer.php (via
 *     samma TemplateEngine som Wiki i roten använder) — samma sitenamn,
 *     logotyp, toppmeny och assets/css/style.css som resten av wikin.
 *     Byt tema i config/config.php och chatten följer automatiskt med.
 *     Ljust/mörkt läge sköts av temats egen assets/js/theme.js (samma
 *     localStorage-nyckel som resten av wikin), inte av chattens egen JS.
 *
 *  2. De färdiga skill-korten läses från riktiga mappar under
 *     /skills/<skill>/SKILL.md istället för att vara inbäddade i HTML:en.
 *     En skill kan i sin YAML-frontmatter deklarera `include` för att
 *     automatiskt bunta in sidor från /content som extra kontextfiler:
 *
 *       include: [start, projekt:api, playground:*]
 *
 *     — enstaka sid-ID:n tas med som de är, "namespace:*" expanderas till
 *     alla sidor i det namespacet. Se skills/wiki-assistent/SKILL.md.
 *
 * Utöver skills kan man manuellt bläddra/söka wikins innehåll direkt i
 * chattrutan med snabbkommandon (/files, /search eller /sok, /tag,
 * /namespace eller /folder) — valda sidor läggs i kontexten precis som
 * en skills filer. /namespace (/folder) listar namespaces (mapparna
 * under /content) och kan lägga till alla sidor i ett namespace på en
 * gång. /context (eller /kontext) listar de Markdown-filer som ligger i
 * kontexten just nu och låter en ta bort dem igen.
 *
 * Servern lagrar och ser ALDRIG några LLM-inställningar eller API-nycklar
 * — de sparas enbart i klientens IndexedDB och anropas direkt från
 * webbläsaren, precis som i html/chat.html. Denna fil exponerar bara
 * skrivskyddade JSON-endpoints (list-skills, get-skill, list-content,
 * search-content, get-content, list-tags) som klient-JS:en anropar.
 */

$root       = dirname(__DIR__);
$skillsDir  = $root . '/skills';
$contentDir = $root . '/content';

spl_autoload_register(function (string $class) use ($root) {
    $path = $root . '/core/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

/** Saniterar ett skill-slug (mappnamn): bara a-z 0-9 _ - tillåts. Skyddar mot path traversal. */
function chatSanitizeSlug(string $slug): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $slug) ?? '';
}

/** Första icke-tomma textraden i brödtexten (utan en ev. #-rubrik) — fallback när frontmatter saknar description. */
function chatFirstLine(string $body): string
{
    $body = preg_replace('/^#.*$/m', '', $body) ?? $body;
    foreach (preg_split('/\r?\n/', trim($body)) as $line) {
        $line = trim($line);
        if ($line !== '') {
            return $line;
        }
    }
    return '';
}

/**
 * Löser ett `include`-värde mot faktiska sid-ID:n i /content.
 *   "start"       → ["start"]              (enskild sida)
 *   "namespace:*" → alla sidor i namespacet (jokertecken)
 */
function chatResolveInclude(string $target, PageLoader $pages): array
{
    $target = trim($target);
    if ($target === '') {
        return [];
    }
    if (str_ends_with($target, ':*')) {
        $ns = substr($target, 0, -2);
        $matches = [];
        foreach ($pages->listAll() as $id) {
            if ($ns === '' || $id === $ns || str_starts_with($id, $ns . ':')) {
                $matches[] = $id;
            }
        }
        return $matches;
    }
    return [$target];
}

/** Listar alla skills (mappar med en SKILL.md) för sidopanelens "Färdiga skills". */
function chatListSkills(string $skillsDir): array
{
    $out = [];
    foreach (glob($skillsDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $slug = basename($dir);
        $file = $dir . '/SKILL.md';
        if (!is_file($file)) {
            continue;
        }
        [$meta, $body] = FrontMatter::parse((string) file_get_contents($file));
        $desc = $meta['description'] ?? chatFirstLine($body);
        $out[] = [
            'slug' => $slug,
            'name' => (string) ($meta['name'] ?? $slug),
            'desc' => mb_substr(trim((string) $desc), 0, 160),
            'icon' => (string) ($meta['icon'] ?? '🧩'),
        ];
    }
    usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
    return $out;
}

/** Läser en enskild skill: SKILL.md + ev. övriga textfiler i mappen + `include`-sidor från /content. */
function chatGetSkill(string $skillsDir, string $contentDir, string $rawSlug): array
{
    $slug = chatSanitizeSlug($rawSlug);
    if ($slug === '') {
        return ['error' => 'Ogiltigt skill-namn.'];
    }
    $dir       = $skillsDir . '/' . $slug;
    $skillFile = $dir . '/SKILL.md';
    if (!is_file($skillFile)) {
        return ['error' => 'Skillen hittades inte.'];
    }

    $raw = (string) file_get_contents($skillFile);
    [$meta, $body] = FrontMatter::parse($raw);

    $files = [['path' => 'SKILL.md', 'content' => $raw]];

    // Övriga filer i skill-mappen (referenser, exempel m.m.) — motsvarar
    // "hela skill-mappen" i html/chat.html, fast inläst av servern.
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    $textExt = ['md', 'markdown', 'txt', 'json', 'yaml', 'yml'];
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $rel = ltrim(str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($dir))), '/');
        if ($rel === 'SKILL.md') {
            continue; // redan tillagd ovan
        }
        if (!in_array(strtolower($fileInfo->getExtension()), $textExt, true)) {
            continue; // hoppa binärfiler i skill-mappen
        }
        $files[] = ['path' => $rel, 'content' => (string) file_get_contents($fileInfo->getPathname())];
    }

    // include: [sid-id, namespace:*, ...] — bunta in sidor från /content
    $include = (array) ($meta['include'] ?? []);
    if ($include) {
        $pages = new PageLoader($contentDir);
        $seen  = [];
        foreach ($include as $entry) {
            foreach (chatResolveInclude((string) $entry, $pages) as $rawId) {
                if (isset($seen[$rawId])) {
                    continue;
                }
                $seen[$rawId] = true;
                try {
                    $pageId = new PageId($rawId);
                } catch (\Throwable) {
                    continue;
                }
                $loaded = $pages->load($pageId);
                if ($loaded === null) {
                    continue;
                }
                $files[] = [
                    'path'    => $pageId->toFilePath('content'),
                    'content' => $loaded['raw'],
                ];
            }
        }
    }

    return [
        'slug'  => $slug,
        'name'  => (string) ($meta['name'] ?? $slug),
        'desc'  => (string) ($meta['description'] ?? chatFirstLine($body)),
        'files' => $files,
    ];
}

/**
 * Listar alla sidor i /content för chattens /files-kommando.
 *
 * $sort: 'alpha' (default, "-a") sorterar A–Ö på sid-ID, 'date' ("-d")
 * sorterar på senast ändrad fil (nyast först).
 * $filter: fritext som måste finnas i FILNAMNET (inte innehållet) —
 * matchar sista delen av sid-ID:t (t.ex. "syntax" i "hjalp:syntax").
 *
 * @return array<int, array{id:string, title:string, url:string, mtime:int}>
 */
function chatListContent(string $contentDir, string $sort = 'alpha', string $filter = ''): array
{
    $pages  = new PageLoader($contentDir);
    $filter = trim($filter);
    $out    = [];
    foreach ($pages->listAll() as $id) {
        $pageId   = new PageId($id);
        $filePath = $pageId->toFilePath($contentDir);
        $filename = basename($filePath, '.md');
        if ($filter !== '' && mb_stripos($filename, $filter) === false) {
            continue;
        }
        $out[] = [
            'id'    => $id,
            'title' => $pageId->title(),
            'url'   => $pageId->url(),
            'mtime' => is_file($filePath) ? filemtime($filePath) : 0,
        ];
    }
    if ($sort === 'date') {
        usort($out, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
    } else {
        usort($out, fn ($a, $b) => strcasecmp($a['id'], $b['id']));
    }
    return $out;
}

/**
 * Fulltextsöker i /content för chattens /search (/sok)-kommando.
 * Återanvänder samma Search-klass som wikins egen ?do=search, så
 * resultaten blir identiska. "tag:nyckelord" söker exakt tagg-matchning,
 * "ns:namespace" listar alla sidor i ett namespace (mapp) — används av
 * /namespace (/folder)-kommandot.
 */
function chatSearchContent(string $contentDir, string $term): array
{
    $search = new Search($contentDir);
    $term   = trim($term);
    if (str_starts_with($term, 'tag:')) {
        return $search->queryByTag(substr($term, 4));
    }
    if (str_starts_with($term, 'ns:')) {
        return $search->queryByNamespace(substr($term, 3));
    }
    return $search->query($term);
}

/**
 * Listar alla namespaces (mappar under /content) med antal sidor i varje,
 * för chattens /namespace (/folder)-kommando. Rotnivåns sidor (utan mapp,
 * dvs. direkt i /content) grupperas som "_root" — samma konvention som
 * Helpers::buildPageTree() använder för sidfotens sidindex.
 * @return array<int, array{ns:string, label:string, count:int}>
 */
function chatListNamespaces(string $contentDir): array
{
    $pages  = new PageLoader($contentDir);
    $counts = [];
    foreach ($pages->listAll() as $id) {
        $ns  = (new PageId($id))->namespace();
        $key = $ns === '' ? '_root' : $ns;
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
    $out = [];
    foreach ($counts as $ns => $count) {
        $out[] = [
            'ns'    => $ns,
            'label' => $ns === '_root' ? 'Rot (sidor utan mapp)' : $ns,
            'count' => $count,
        ];
    }
    usort($out, function ($a, $b) {
        if ($a['ns'] === '_root') return -1;
        if ($b['ns'] === '_root') return 1;
        return strcasecmp($a['ns'], $b['ns']);
    });
    return $out;
}

/**
 * Listar alla unika taggar (frontmatter "tags") i /content med antal
 * sidor per tagg, för chattens /tag-kommando.
 * @return array<int, array{tag:string, count:int}>
 */
function chatListTags(string $contentDir): array
{
    $pages  = new PageLoader($contentDir);
    $counts = [];
    foreach ($pages->listAll() as $id) {
        $page = $pages->load(new PageId($id));
        if ($page === null) {
            continue;
        }
        foreach ((array) ($page['meta']['tags'] ?? []) as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }
            $counts[$tag] = ($counts[$tag] ?? 0) + 1;
        }
    }
    $out = [];
    foreach ($counts as $tag => $count) {
        $out[] = ['tag' => $tag, 'count' => $count];
    }
    usort($out, fn ($a, $b) => strcasecmp($a['tag'], $b['tag']));
    return $out;
}

/** Hämtar en enskild sidas råinnehåll (för att lägga till i chattens kontext). */
function chatGetContent(string $contentDir, string $rawId): array
{
    if (trim($rawId) === '') {
        return ['error' => 'Inget sid-ID angivet.'];
    }
    try {
        $pageId = new PageId($rawId);
    } catch (\Throwable) {
        return ['error' => 'Ogiltigt sid-ID.'];
    }
    $loaded = (new PageLoader($contentDir))->load($pageId);
    if ($loaded === null) {
        return ['error' => 'Sidan hittades inte.'];
    }
    return [
        'id'      => $pageId->id(),
        'path'    => $pageId->toFilePath('content'),
        'content' => $loaded['raw'],
    ];
}

$action = $_GET['action'] ?? '';

if ($action === 'list-skills') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(chatListSkills($skillsDir), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get-skill') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(chatGetSkill($skillsDir, $contentDir, (string) ($_GET['skill'] ?? '')), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'list-content') {
    header('Content-Type: application/json; charset=utf-8');
    $sort   = ($_GET['sort'] ?? '') === 'date' ? 'date' : 'alpha';
    $filter = (string) ($_GET['filter'] ?? '');
    echo json_encode(chatListContent($contentDir, $sort, $filter), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'search-content') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(chatSearchContent($contentDir, (string) ($_GET['q'] ?? '')), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get-content') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(chatGetContent($contentDir, (string) ($_GET['id'] ?? '')), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'list-tags') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(chatListTags($contentDir), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'list-namespaces') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(chatListNamespaces($contentDir), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── HTML-sidan (inte en ?action=-endpoint): bootstrapa exakt det som
// behövs för att återanvända AKTIVT temas riktiga header.php/footer.php,
// så /chat alltid är synkad med samma tema, sitenamn och toppmeny som
// resten av wikin.
$config    = require $root . '/config/config.php';
$siteName  = $config['site_name'] ?? 'RuneWiki';
$lang      = $config['language'] ?? 'sv';
$templates = new TemplateEngine($root . '/templates', $config['theme'] ?? 'default');
$assetUrl  = fn (string $p) => $templates->assetUrl($p);
$pages     = new PageLoader($contentDir);
// Samma menyprioritet som core/Wiki.php (menuFor()): _topbar.md styr ALLTID
// om den finns, annars en ev. 'menu'-override för rot-namespacet i
// config/namespaces.php, annars global config/menu.php.
$namespaces = new NamespaceResolver(Helpers::loadConfig($root . '/config/namespaces.php'));
$menu       = Helpers::loadTopbarMenu($contentDir)
    ?? ($namespaces->settingsFor('')['menu'] ?? Helpers::loadConfig($root . '/config/menu.php'));
// Samma texter (tagline, sidfotstexter m.m.) som resten av wikin —
// skrivs över i config/strings.php, se Helpers::resolveStrings().
$strings = Helpers::resolveStrings($root, $siteName);
// Samma inloggningsstatus som resten av wikin, så login/logout-knappen i
// headern stämmer även på /chat (auth styr bara redigering, inte /chat
// själv — chatten kräver ingen inloggning).
$auth = new Auth($root . '/data/users/users.php', (bool) ($config['auth_enabled'] ?? false));

// view='chat' (inte 'page'/'missing') gör att header.php automatiskt döljer
// "Redigera"-länken och brödsmulorna — de hör bara hemma på riktiga sidvyer.
$headerHtml = $templates->render('header', [
    'siteName'    => $siteName,
    'lang'        => $lang,
    'menu'        => $menu,
    'strings'     => $strings,
    'assetUrl'    => $assetUrl,
    'currentId'   => '',
    'view'        => 'chat',
    'authEnabled' => $auth->isEnabled(),
    'currentUser' => $auth->currentUser(),
    'headerLogo'     => $config['header_logo'] ?? 'img/logo.svg',
    'headerLogoDark' => $config['header_logo_dark'] ?? '',
]);
$footerHtml = $templates->render('footer', [
    'siteName'   => $siteName,
    'strings'    => $strings,
    'assetUrl'   => $assetUrl,
    'pageTree'   => Helpers::buildPageTree($pages),
    'footerLogo' => $config['footer_logo'] ?? 'img/logo.svg',
]);
?>
<!DOCTYPE html>
<html lang="<?= Helpers::e($lang) ?>" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI-chatt — <?= Helpers::e($siteName) ?></title>
<link rel="icon" href="<?= Helpers::e($assetUrl($config['favicon'] ?? 'img/favicon.svg')) ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.1.6/purify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@latest/dist/mermaid.min.js"></script>
<link rel="stylesheet" href="<?= Helpers::e($assetUrl('css/style.css')) ?>">
<style>
/**
 * Rent strukturell CSS för själva chatt-appen (ingen färg/typsnitt här —
 * det kommer helt från det aktiva temats assets/css/style.css, som även
 * innehåller alla ".chat-app"-komponentstilar). Wiki-sidor scrollar
 * normalt; chatten är en fullhöjds-app-vy, därav overflow:hidden här.
 *
 * body måste vara en flex-container (kolumn) för att .chat-app (som redan
 * har flex:1 + min-height:0 i temats style.css) faktiskt ska begränsas
 * till utrymmet mellan temats header och footer — annars växer body med
 * chatt-innehållet och HELA sidan scrollar, istället för att bara
 * meddelandelistan (.chat-scroll) scrollar internt.
 */
html, body { height: 100%; }
@supports (height: 100dvh) { html, body { height: 100dvh; } }
body {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
/* Temats header/nav/footer ska behålla sin naturliga höjd — bara
   .chat-app (mellan dem) ska krympa/växa för att fylla resten. */
body > header, body > nav, body > footer, body > .gbg-index-band {
  flex-shrink: 0;
}
/* Sidfoten kan döljas helt (footerToggleBtn) för att ge chatten mer
   höjd — .chat-app fyller då automatiskt ut det frigjorda utrymmet
   eftersom den redan har flex:1 i temats style.css. */
body.footer-collapsed > footer, body.footer-collapsed > .gbg-index-band {
  display: none;
}
</style>
</head>
<body>
<?= $headerHtml ?>

<div class="chat-app">
  <div class="chat-toolbar">
    <button class="icon-btn menu-btn" id="menuBtn" title="Visa filer" aria-label="Visa filer">☰</button>
    <div class="chat-toolbar-title">AI-chatt</div>
    <div class="spacer"></div>
    <button class="icon-btn" id="downloadBtn" title="Ladda ner chatt som Markdown" aria-label="Ladda ner chatt som Markdown">⬇</button>
    <button class="icon-btn" id="footerToggleBtn" title="Dölj sidfot (mer plats åt chatten)" aria-label="Dölj sidfot" aria-pressed="false">⌄</button>
    <button class="icon-btn" id="settingsBtn" title="Inställningar" aria-label="Inställningar">⚙</button>
  </div>

<div class="layout">
  <aside id="sidebar">
    <div class="aside-scroll">
      <div class="skill-picker-summary" id="skillPickerSummary">
        <span class="ico" aria-hidden="true">🧩</span>
        <span class="selected-skill">
          <span class="label">Vald skill</span>
          <span class="name" id="selectedSkillName">Skill</span>
        </span>
        <button class="btn btn-secondary" id="changeSkillBtn" type="button" aria-expanded="false">Byt skill</button>
      </div>

      <div class="skill-picker-controls" id="skillPickerControls">
        <div class="dropzone" id="dropzone">
          <div class="big">📄</div>
          <p><strong>Släpp eller välj fil</strong></p>
          <p>SKILL.md · .zip · hela skill-mappen</p>
          <div class="file-actions">
            <button class="btn btn-secondary" id="pickFile">Fil</button>
            <button class="btn btn-secondary" id="pickFolder">Mapp</button>
            <button class="btn btn-secondary" id="pickZip">.zip</button>
          </div>
          <input type="file" id="inFile" accept=".md,.markdown,.txt,.json,.yaml,.yml" hidden>
          <input type="file" id="inFolder" webkitdirectory directory multiple hidden>
          <input type="file" id="inZip" accept=".zip,.skill" hidden>
        </div>

        <div id="builtinSection">
          <div class="section-title">Färdiga skills</div>
          <div class="builtin-list" id="builtinList"></div>
        </div>
      </div>

      <div id="skillMeta" style="display:none">
        <div class="section-title">Laddad skill</div>
        <div class="skill-meta">
          <h3 id="skillName">—</h3>
          <p id="skillDesc">—</p>
        </div>
      </div>

      <div id="fileSection" style="display:none">
        <div class="section-title">Filer i kontext</div>
        <ul class="file-list" id="fileList"></ul>
        <div class="ctx-bar">
          <span><span class="num" id="ctxFiles">0</span> filer valda</span>
          <span>~<span class="num" id="ctxTokens">0</span> tokens</span>
        </div>
        <button class="btn btn-ghost" id="clearBtn" style="margin-top:.6rem; width:100%; border:1px solid var(--border-color)">Rensa allt</button>
      </div>
    </div>
  </aside>

  <main>
    <div class="chat-scroll" id="chatScroll">
      <div class="empty-state" id="emptyState">
        <div class="ico">💬</div>
        <h2>Chatta med din skill</h2>
        <p>Ladda en SKILL.md, ett .zip-arkiv eller en hel skill-mapp. Alla frågor besvaras enbart utifrån det laddade innehållet.</p>
        <p><code>/files</code> (<code>/f</code>) listar wikins sidor (<code>-a</code> alfabetiskt, <code>-d</code> senast ändrade, <code>/files text</code> filtrerar på filnamn), <code>/search</code> (<code>/s</code>, eller <code>/sok</code>) söker bland dem, <code>/tag</code> (<code>/t</code>) listar taggar, <code>/namespace</code> (<code>/ns</code>, eller <code>/folder</code>) listar namespaces och kan lägga till alla sidor i ett namespace, <code>/context</code> (<code>/c</code>, eller <code>/kontext</code>) listar och tar bort Markdown-filer i kontexten — lägg till valda sidor i kontexten direkt i chatten. Långa listor visas 50 åt gången.</p>
        <div class="hint">⚙ Ställ in LLM-anslutning först (OpenAI, LM Studio eller Ollama)</div>
      </div>
    </div>
    <div class="composer">
      <div class="row">
        <textarea id="input" rows="1" placeholder="Fråga, eller /files · /search <sökterm> · /namespace · /context…"></textarea>
        <button class="send-btn" id="sendBtn" title="Skicka" aria-label="Skicka">➤</button>
      </div>
      <div class="meta">
        <span id="modelMeta">Ingen modell vald</span>
        <span id="ctxMeta"></span>
      </div>
    </div>
  </main>
</div>
</div>

<?= $footerHtml ?>

<!-- Settings modal -->
<div class="overlay" id="overlay">
  <div class="modal">
    <div class="modal-head">
      <h2>LLM-inställningar</h2>
      <div class="spacer"></div>
      <button class="btn btn-ghost" id="closeModal" style="font-size:1.3rem">✕</button>
    </div>
    <div class="modal-body">
      <div class="preset-tabs">
        <button data-prov="openai">OpenAI-kompatibel</button>
        <button data-prov="lmstudio">LM Studio</button>
        <button data-prov="ollama">Ollama</button>
      </div>

      <div class="note" id="provNote"></div>

      <div class="field">
        <label for="baseUrl">Bas-URL</label>
        <input type="text" id="baseUrl" placeholder="https://api.openai.com/v1">
      </div>
      <div class="field" id="keyField">
        <label for="apiKey">API-nyckel</label>
        <input type="password" id="apiKey" placeholder="sk-…" autocomplete="off">
        <div class="help">Sparas lokalt i webbläsarens IndexedDB. Lämna tom för lokala servrar utan nyckel.</div>
      </div>
      <div class="field">
        <label for="model">Modell</label>
        <div class="model-row">
          <select id="modelSelect"><option value="">— skriv eller hämta —</option></select>
          <button class="btn btn-secondary" id="fetchModels" style="white-space:nowrap">Hämta</button>
        </div>
        <input type="text" id="model" placeholder="t.ex. gpt-4o-mini / llama3.1 / qwen2.5" style="margin-top:.5rem">
      </div>
      <div class="field">
        <label for="temp">Temperatur: <span id="tempVal">0.3</span></label>
        <input type="range" id="temp" min="0" max="1" step="0.05" value="0.3">
      </div>
      <div class="field">
        <label for="sysPrompt">Systemprompt (<code>{context}</code> ersätts med allt innehåll i kontexten — skill, tillagda wikisidor och uppladdade filer)</label>
        <textarea id="sysPrompt"></textarea>
      </div>
      <button class="btn btn-secondary" id="testBtn" style="width:100%">Testa anslutning</button>
      <div class="test-result" id="testResult"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-danger" id="clearSettingsBtn" style="margin-right:auto" title="Ta bort sparade LLM-inställningar ur webbläsaren">Rensa inställningar</button>
      <button class="btn btn-secondary" id="cancelBtn">Avbryt</button>
      <button class="btn btn-primary" id="saveBtn">Spara</button>
    </div>
  </div>
</div>

<!-- File preview modal -->
<div class="overlay" id="fileOverlay">
  <div class="modal wide">
    <div class="modal-head fv-head">
      <span class="path" id="fvPath">fil</span>
      <span class="sz" id="fvSize"></span>
      <div class="spacer"></div>
      <button class="btn btn-ghost" id="fvClose" style="font-size:1.3rem">✕</button>
    </div>
    <div class="fv-toolbar" id="fvToolbar">
      <div class="seg" id="fvSeg" style="display:none">
        <button id="fvRendered">Renderad</button>
        <button id="fvRaw">Källkod</button>
      </div>
      <div class="spacer"></div>
      <button class="btn btn-secondary" id="fvCopy" style="font-size:.8rem; padding:.35rem .8rem">Kopiera</button>
    </div>
    <div class="fv-body" id="fvBody"></div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script src="<?= Helpers::e($assetUrl('js/theme.js')) ?>"<?= Helpers::linkSettingsAttributes($config) ?>></script>
<script>
/* ---------- IndexedDB ---------- */
const DB_NAME='skillchat-db', STORE='kv';
function idbOpen(){return new Promise((res,rej)=>{const r=indexedDB.open(DB_NAME,1);r.onupgradeneeded=()=>{if(!r.result.objectStoreNames.contains(STORE))r.result.createObjectStore(STORE)};r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error)})}
async function idbSet(k,v){const db=await idbOpen();return new Promise((res,rej)=>{const tx=db.transaction(STORE,'readwrite');tx.objectStore(STORE).put(v,k);tx.oncomplete=()=>res();tx.onerror=()=>rej(tx.error)})}
async function idbGet(k){const db=await idbOpen();return new Promise((res,rej)=>{const tx=db.transaction(STORE,'readonly');const rq=tx.objectStore(STORE).get(k);rq.onsuccess=()=>res(rq.result);rq.onerror=()=>rej(rq.error)})}
async function idbDelete(k){const db=await idbOpen();return new Promise((res,rej)=>{const tx=db.transaction(STORE,'readwrite');tx.objectStore(STORE).delete(k);tx.oncomplete=()=>res();tx.onerror=()=>rej(tx.error)})}

/* ---------- Config & defaults ---------- */
const DEFAULT_SYS = 'Du är en hjälpsam assistent som svarar på frågor ENBART utifrån innehållet i kontexten nedan — den kan bestå av en laddad skill, wikisidor tillagda via /files, /search eller /tag, och/eller manuellt uppladdade filer. Om svaret inte finns i materialet, säg det tydligt och gissa inte. Hänvisa gärna till vilken fil informationen kommer ifrån. Svara på svenska om inte användaren skriver på ett annat språk.\n\n=== KONTEXT ===\n{context}';
const PRESETS = {
  openai:   {label:'OpenAI-kompatibel', baseUrl:'https://api.openai.com/v1', needsKey:true,  api:'chat', note:'Fungerar med OpenAI och alla OpenAI-kompatibla tjänster. Bas-URL ska sluta på <code>/v1</code>.'},
  lmstudio: {label:'LM Studio', baseUrl:'http://localhost:1234/v1', needsKey:false, api:'chat', note:'Starta LM Studios lokala server och slå på <strong>CORS</strong> i serverinställningarna. Standard-URL: <code>http://localhost:1234/v1</code>.'},
  ollama:   {label:'Ollama', baseUrl:'http://localhost:11434', needsKey:false, api:'ollama', note:'Kräver att Ollama tillåter denna sida. Starta med <code>OLLAMA_ORIGINS=*</code> (eller specifik origin). Bas-URL utan <code>/v1</code>: <code>http://localhost:11434</code>.'}
};
let config = {provider:'openai', baseUrl:PRESETS.openai.baseUrl, apiKey:'', model:'', temperature:0.3, sysPrompt:DEFAULT_SYS};
let editProvider = 'openai';

/* ---------- State ---------- */
let files = [];          // {path, content, isText, size, include}
let skillMeta = {name:'', desc:''};
let history = [];        // {role, content}
let streaming = false;
let abortCtrl = null;

const $ = id => document.getElementById(id);
let toastTimer=null;
function toast(msg){const t=$('toast'); t.textContent=msg; t.classList.add('show'); clearTimeout(toastTimer); toastTimer=setTimeout(()=>t.classList.remove('show'),2400);}

/* ---------- Theme ----------
 * Ljust/mörkt läge hanteras INTE här längre — det sköts av det aktiva
 * temats egen assets/js/theme.js (samma skriptfil som header.php/layout.php
 * använder, inkluderad längst ner i body). Den läser/skriver samma
 * localStorage-nyckel ("theme") som resten av wikin, så chatten alltid
 * öppnas i samma läge som man senast valde på en vanlig wikisida — och
 * temats egen toggle-knapp i headern styr båda.
 */

/* ---------- File handling ---------- */
const TEXT_EXT = ['md','markdown','txt','json','yaml','yml','csv','tsv','js','ts','jsx','tsx','py','rb','go','rs','java','c','cpp','h','html','htm','css','xml','sh','bash','sql','toml','ini','cfg','conf','env','log','tex','r','php','pl','lua','svg'];
const IMAGE_EXT = ['png','jpg','jpeg','gif','webp','bmp','ico','avif'];
function extOf(name){return (name.split('.').pop()||'').toLowerCase();}
function isTextFile(name){return TEXT_EXT.includes(extOf(name));}
function isImageFile(name){return IMAGE_EXT.includes(extOf(name));}
function mimeFor(name){const e=extOf(name); return e==='svg'?'image/svg+xml':e==='jpg'?'image/jpeg':e==='ico'?'image/x-icon':'image/'+e;}
function fileToDataURL(file){return new Promise((res,rej)=>{const r=new FileReader();r.onload=()=>res(r.result);r.onerror=()=>rej(r.error);r.readAsDataURL(file);});}
function addImage(path, dataUrl, size){files.push({path:path.replace(/^\.?\//,''), content:'', isText:false, isImage:true, dataUrl, size, include:false});}
function estTokens(str){return Math.ceil(str.length/4);}
function fmtSize(n){return n<1024?n+' B':n<1048576?(n/1024).toFixed(1)+' KB':(n/1048576).toFixed(1)+' MB';}
function fmtNum(n){return n.toLocaleString('sv-SE');}

function parseFrontmatter(md){
  const m = md.match(/^---\s*\n([\s\S]*?)\n---/);
  if(!m) return {};
  const out={}; m[1].split('\n').forEach(line=>{
    const i=line.indexOf(':'); if(i>0){const k=line.slice(0,i).trim(); let v=line.slice(i+1).trim().replace(/^["']|["']$/g,''); out[k]=v;}
  });
  return out;
}

function addFile(path, content, isText){
  // strip leading folder names down to relative-ish path
  const clean = path.replace(/^\.?\//,'');
  files.push({path:clean, content:content||'', isText, size:isText?new Blob([content]).size:(content?content.length:0), include:isText});
}

function afterLoad(){
  // sort: SKILL.md first, then .md, then rest
  files.sort((a,b)=>{
    const aS=/skill\.md$/i.test(a.path)?0:/\.md$/i.test(a.path)?1:2;
    const bS=/skill\.md$/i.test(b.path)?0:/\.md$/i.test(b.path)?1:2;
    return aS-bS || a.path.localeCompare(b.path);
  });
  // detect meta from a SKILL.md
  const skill = files.find(f=>/skill\.md$/i.test(f.path)) || files.find(f=>/\.md$/i.test(f.path));
  if(skill){
    const fm = parseFrontmatter(skill.content);
    skillMeta.name = fm.name || skill.path.split('/').pop();
    skillMeta.desc = fm.description || (skill.content.replace(/^---[\s\S]*?---/,'').trim().split('\n').find(l=>l.trim()) || '').slice(0,160);
  }
  renderFiles();
}

function renderFiles(){
  if(files.length===0){
    $('skillMeta').style.display='none';
    $('fileSection').style.display='none';
    $('sidebar').classList.remove('has-skill','picker-open');
    $('changeSkillBtn').setAttribute('aria-expanded','false');
    return;
  }
  $('sidebar').classList.add('has-skill');
  $('sidebar').classList.remove('picker-open');
  $('changeSkillBtn').setAttribute('aria-expanded','false');
  $('selectedSkillName').textContent = skillMeta.name || 'Skill';
  $('skillMeta').style.display='block';
  $('skillName').textContent = skillMeta.name || 'Skill';
  $('skillDesc').textContent = skillMeta.desc || '';
  $('fileSection').style.display='block';
  const ul=$('fileList'); ul.innerHTML='';
  files.forEach((f,i)=>{
    const li=document.createElement('li');
    li.className='file-item'+(f.isText?'':' binary');
    li.innerHTML=`<input type="checkbox" ${f.include?'checked':''} ${f.isText?'':'disabled'} data-i="${i}">
      <span class="fname" data-open="${i}" title="Öppna ${f.path}">${f.path}</span>
      <span class="fsize">${fmtSize(f.size)}</span>`;
    ul.appendChild(li);
  });
  ul.querySelectorAll('input').forEach(c=>c.onchange=e=>{files[+e.target.dataset.i].include=e.target.checked; updateCtx();});
  ul.querySelectorAll('.fname').forEach(el=>el.onclick=()=>openFilePreview(+el.dataset.open));
  updateCtx();
  if(window.innerWidth<=820) $('sidebar').classList.remove('open');
}

function buildContext(){
  return files.filter(f=>f.isText&&f.include)
    .map(f=>`=== FIL: ${f.path} ===\n${f.content}`).join('\n\n');
}
function updateCtx(){
  const sel = files.filter(f=>f.isText&&f.include);
  const ctx = buildContext();
  $('ctxFiles').textContent = sel.length;
  $('ctxTokens').textContent = fmtNum(estTokens(ctx));
  $('ctxMeta').textContent = sel.length? `${sel.length} fil(er) · ~${fmtNum(estTokens(ctx))} tokens i kontext` : '';
}

async function loadSingleFile(file){
  resetFiles();
  if(isImageFile(file.name)){ addImage(file.name, await fileToDataURL(file), file.size); }
  else { addFile(file.name, await file.text(), isTextFile(file.name)); }
  afterLoad();
}
async function loadFolder(fileList){
  resetFiles();
  for(const file of fileList){
    const rel = file.webkitRelativePath || file.name;
    if(/(^|\/)(\.|__MACOSX|node_modules)/.test(rel)) continue;
    if(isImageFile(rel)){ addImage(rel, await fileToDataURL(file), file.size); }
    else if(isTextFile(rel)){ addFile(rel, await file.text(), true); }
    else { addFile(rel, '', false); files[files.length-1].size=file.size; }
  }
  afterLoad();
}
async function loadZip(file){
  resetFiles();
  const zip = await JSZip.loadAsync(file);
  const entries = Object.values(zip.files).filter(e=>!e.dir && !/(^|\/)(\.|__MACOSX)/.test(e.name));
  for(const e of entries){
    if(isImageFile(e.name)){ const b64=await e.async('base64'); const blob=await e.async('blob'); addImage(e.name, 'data:'+mimeFor(e.name)+';base64,'+b64, blob.size); }
    else if(isTextFile(e.name)){ addFile(e.name, await e.async('string'), true); }
    else { const blob=await e.async('blob'); addFile(e.name,'',false); files[files.length-1].size=blob.size; }
  }
  afterLoad();
}
function resetFiles(){files=[]; skillMeta={name:'',desc:''};}

/* file inputs */
$('pickFile').onclick=()=>$('inFile').click();
$('pickFolder').onclick=()=>$('inFolder').click();
$('pickZip').onclick=()=>$('inZip').click();
$('inFile').onchange=e=>{if(e.target.files[0])loadSingleFile(e.target.files[0]).catch(err=>alert('Fel: '+err.message));};
$('inFolder').onchange=e=>{if(e.target.files.length)loadFolder(e.target.files).catch(err=>alert('Fel: '+err.message));};
$('inZip').onchange=e=>{if(e.target.files[0])loadZip(e.target.files[0]).catch(err=>alert('Kunde inte läsa arkivet: '+err.message));};
$('clearBtn').onclick=()=>{resetFiles(); renderFiles();};
$('changeSkillBtn').onclick=()=>{
  const open=$('sidebar').classList.toggle('picker-open');
  $('changeSkillBtn').setAttribute('aria-expanded', String(open));
};

/* ---------- Skills (läses från /skills/<skill>/SKILL.md via servern) ---------- */
let SKILLS=[];
async function renderBuiltins(){
  const wrap=$('builtinList'); if(!wrap)return;
  wrap.innerHTML='<div class="bdesc" style="padding:.3rem 0">Laddar skills…</div>';
  try{
    const res=await fetch('?action=list-skills');
    if(!res.ok) throw new Error('HTTP '+res.status);
    SKILLS=await res.json();
  }catch(err){
    wrap.innerHTML='<div class="bdesc" style="padding:.3rem 0">Kunde inte hämta skills från servern.</div>';
    return;
  }
  wrap.innerHTML='';
  if(SKILLS.length===0){
    wrap.innerHTML='<div class="bdesc" style="padding:.3rem 0">Inga skills hittades i /skills.</div>';
    return;
  }
  SKILLS.forEach(s=>{
    const el=document.createElement('button');
    el.className='builtin-item'; el.type='button';
    el.innerHTML=`<span class="ico">${s.icon||'🧩'}</span><span class="txt"><span class="bname">${escapeHtml(s.name)}</span><span class="bdesc">${escapeHtml(s.desc||'')}</span></span>`;
    el.onclick=()=>loadSkill(s.slug, s.name);
    wrap.appendChild(el);
  });
}
async function loadSkill(slug, label){
  try{
    const res=await fetch('?action=get-skill&skill='+encodeURIComponent(slug));
    if(!res.ok) throw new Error('HTTP '+res.status);
    const data=await res.json();
    if(data.error) throw new Error(data.error);
    resetFiles();
    (data.files||[]).forEach(f=>addFile(f.path, f.content, true));
    afterLoad();
    toast('Laddade: '+(label||data.name||slug));
    if(window.innerWidth<=820) $('sidebar').classList.remove('open');
  }catch(err){
    alert('Kunde inte läsa skillen från /skills: '+err.message);
  }
}

/**
 * Deep-link: /chat?doc=<sid-id> — kommer från "AI-chatt"/"Chat"-länken på
 * en enskild wikisida (header.php i respektive tema) och laddar just den
 * sidan i kontexten direkt, så man kan chatta med det specifika dokumentet
 * utan att gå via /files eller /search manuellt.
 */
async function loadDocFromQuery(id){
  try{
    const res=await fetch('?action=get-content&id='+encodeURIComponent(id));
    if(!res.ok) throw new Error('HTTP '+res.status);
    const data=await res.json();
    if(data.error) throw new Error(data.error);
    resetFiles();
    addFile(data.path, data.content, true);
    afterLoad();
    addMsg('system', '<p>💬 Redo att chatta om <code>'+escapeHtml(data.id)+'</code> — frågor besvaras utifrån den här sidans innehåll.</p>');
    toast('Chattar om: '+data.path);
  }catch(err){
    toast('Kunde inte ladda sidan i kontexten: '+err.message);
  }
}

/* drag & drop */
const dz=$('dropzone');
['dragenter','dragover'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.add('drag');}));
['dragleave','drop'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.remove('drag');}));
dz.addEventListener('drop', async e=>{
  const items = e.dataTransfer.files;
  if(!items.length) return;
  const f = items[0];
  try{
    if(/\.zip$|\.skill$/i.test(f.name)) await loadZip(f);
    else if(items.length>1) await loadFolder(items);
    else await loadSingleFile(f);
  }catch(err){alert('Fel vid inläsning: '+err.message);}
});

/* ---------- Settings modal ---------- */
function applyProviderUI(prov){
  editProvider=prov;
  const p=PRESETS[prov];
  document.querySelectorAll('.preset-tabs button').forEach(b=>b.classList.toggle('active', b.dataset.prov===prov));
  $('provNote').innerHTML=p.note;
  $('keyField').style.display = p.needsKey?'block':'block'; // always show, but optional for local
  if(!$('baseUrl').value || Object.values(PRESETS).some(x=>x.baseUrl===$('baseUrl').value)){
    $('baseUrl').value=p.baseUrl;
  }
}
document.querySelectorAll('.preset-tabs button').forEach(b=>{
  b.onclick=()=>{ $('baseUrl').value=PRESETS[b.dataset.prov].baseUrl; applyProviderUI(b.dataset.prov); $('modelSelect').innerHTML='<option value="">— skriv eller hämta —</option>'; };
});
$('temp').oninput=e=>$('tempVal').textContent=e.target.value;
$('modelSelect').onchange=e=>{if(e.target.value)$('model').value=e.target.value;};

function openSettings(){
  $('baseUrl').value=config.baseUrl;
  $('apiKey').value=config.apiKey;
  $('model').value=config.model;
  $('temp').value=config.temperature; $('tempVal').textContent=config.temperature;
  $('sysPrompt').value=config.sysPrompt;
  applyProviderUI(config.provider);
  $('testResult').className='test-result';
  $('overlay').classList.add('open');
}
function closeSettings(){$('overlay').classList.remove('open');}
$('settingsBtn').onclick=openSettings;
$('closeModal').onclick=closeSettings;
$('cancelBtn').onclick=closeSettings;
$('overlay').onclick=e=>{if(e.target===$('overlay'))closeSettings();};

$('clearSettingsBtn').onclick=async ()=>{
  if(!confirm('Rensa sparade LLM-inställningar (bas-URL, API-nyckel, modell, temperatur, systemprompt) ur webbläsaren? Går inte att ångra.'))return;
  try{
    await idbDelete('config');
    config={provider:'openai', baseUrl:PRESETS.openai.baseUrl, apiKey:'', model:'', temperature:0.3, sysPrompt:DEFAULT_SYS};
    openSettings(); // fyll modalen med standardvärdena igen
    updateStatus();
    toast('Inställningar rensade');
  }catch(err){
    alert('Kunde inte rensa inställningar: '+err.message);
  }
};

$('saveBtn').onclick=async ()=>{
  config={
    provider:editProvider,
    baseUrl:$('baseUrl').value.trim(),
    apiKey:$('apiKey').value.trim(),
    model:$('model').value.trim(),
    temperature:parseFloat($('temp').value),
    sysPrompt:$('sysPrompt').value || DEFAULT_SYS
  };
  try{
    await idbSet('config', config);
    const back = await idbGet('config');         // verifiera att det faktiskt skrevs
    if(!back || back.model!==config.model || back.baseUrl!==config.baseUrl) throw new Error('verifiering misslyckades');
    updateStatus();
    closeSettings();
    toast('Inställningar sparade i webbläsaren (IndexedDB)');
  }catch(err){
    alert('Kunde inte spara inställningar i IndexedDB: '+err.message+'\n(Privat läge / blockerad lagring kan hindra detta.)');
  }
};

$('fetchModels').onclick=async ()=>{
  const base=$('baseUrl').value.trim().replace(/\/$/,'');
  const key=$('apiKey').value.trim();
  const sel=$('modelSelect');
  sel.innerHTML='<option>Hämtar…</option>';
  try{
    let names=[];
    if(editProvider==='ollama'){
      const r=await fetch(base+'/api/tags'); const j=await r.json();
      names=(j.models||[]).map(m=>m.name);
    }else{
      const h={}; if(key)h['Authorization']='Bearer '+key;
      const r=await fetch(base+'/models',{headers:h}); const j=await r.json();
      names=(j.data||[]).map(m=>m.id);
    }
    sel.innerHTML='<option value="">— välj modell —</option>'+names.map(n=>`<option value="${n}">${n}</option>`).join('');
    if(names.length===0)sel.innerHTML='<option value="">Inga modeller hittades</option>';
  }catch(err){
    sel.innerHTML='<option value="">Kunde inte hämta (CORS?)</option>';
  }
};

$('testBtn').onclick=async ()=>{
  const tr=$('testResult'); tr.className='test-result show'; tr.textContent='Testar…';
  const tmp={provider:editProvider, baseUrl:$('baseUrl').value.trim(), apiKey:$('apiKey').value.trim(), model:$('model').value.trim(), temperature:0};
  if(!tmp.model){tr.className='test-result show err'; tr.textContent='Ange en modell först.'; return;}
  try{
    let got='';
    await callLLM([{role:'user',content:'Svara kort med ordet OK.'}], t=>got+=t, null, tmp);
    tr.className='test-result show ok';
    tr.textContent='✓ Anslutning fungerar. Svar: '+(got.trim().slice(0,40)||'(tomt)');
  }catch(err){
    tr.className='test-result show err';
    tr.textContent='✗ '+err.message;
  }
};

function updateStatus(){
  // Statusbadgen i headern är borta sedan headern nu kommer från det
  // aktiva temat — modellstatusen visas istället i komposerns metarad.
  $('modelMeta').textContent = config.model
    ? PRESETS[config.provider].label+' / '+config.model
    : 'Ingen modell vald – öppna ⚙ för att ställa in';
}

/* ---------- LLM call (streaming) ---------- */
async function readStream(res, onLine){
  const reader=res.body.getReader(); const dec=new TextDecoder(); let buf='';
  while(true){
    const {value,done}=await reader.read(); if(done)break;
    buf+=dec.decode(value,{stream:true});
    let idx;
    while((idx=buf.indexOf('\n'))>=0){ const line=buf.slice(0,idx); buf=buf.slice(idx+1); if(line.trim())onLine(line.trim()); }
  }
  if(buf.trim())onLine(buf.trim());
}

async function callLLM(messages, onToken, signal, cfgOverride){
  const cfg=cfgOverride||config;
  const base=cfg.baseUrl.replace(/\/$/,'');
  if(PRESETS[cfg.provider].api==='ollama'){
    const res=await fetch(base+'/api/chat',{
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({model:cfg.model, messages, stream:true, options:{temperature:cfg.temperature}}),
      signal
    });
    if(!res.ok) throw new Error('HTTP '+res.status+': '+(await res.text()).slice(0,160));
    await readStream(res, line=>{
      try{const j=JSON.parse(line); if(j.message&&j.message.content)onToken(j.message.content); if(j.error)throw new Error(j.error);}catch(e){if(e.message&&!/JSON/.test(e.message))throw e;}
    });
  }else{
    const headers={'Content-Type':'application/json'};
    if(cfg.apiKey)headers['Authorization']='Bearer '+cfg.apiKey;
    const res=await fetch(base+'/chat/completions',{
      method:'POST', headers,
      body:JSON.stringify({model:cfg.model, messages, stream:true, temperature:cfg.temperature}),
      signal
    });
    if(!res.ok) throw new Error('HTTP '+res.status+': '+(await res.text()).slice(0,160));
    await readStream(res, line=>{
      if(!line.startsWith('data:'))return;
      const data=line.slice(5).trim();
      if(data==='[DONE]')return;
      try{const j=JSON.parse(data); const d=j.choices&&j.choices[0]&&j.choices[0].delta; if(d&&d.content)onToken(d.content);}catch(e){}
    });
  }
}

/* ---------- Chat UI ---------- */
function renderMd(text){return DOMPurify.sanitize(marked.parse(text||''));}

/* ---------- Code blocks & Mermaid rendering ---------- */
let mmdCounter=0;
function makeCopyBtn(getText){
  const b=document.createElement('button'); b.className='cb-copy'; b.type='button'; b.textContent='Kopiera';
  b.onclick=async ()=>{ try{ await navigator.clipboard.writeText(getText()); b.textContent='Kopierat ✓'; b.classList.add('copied'); setTimeout(()=>{b.textContent='Kopiera'; b.classList.remove('copied');},1500);}catch(e){ b.textContent='Fel'; } };
  return b;
}
async function renderMermaid(el, source){
  if(!window.mermaid){ el.innerHTML='<div class="mmd-err">Mermaid kunde inte laddas (kontrollera nätverk/CDN).</div>'; return; }
  const id='mmd-'+(mmdCounter++);
  try{
    const {svg}=await window.mermaid.render(id, source);
    el.innerHTML=svg;
  }catch(err){
    const orphan=document.getElementById(id); if(orphan)orphan.remove();
    el.innerHTML='<div class="mmd-err">⚠️ Diagramfel: '+escapeHtml((err&&err.message)||String(err))+'</div>';
  }
}
function buildCodeBlock(pre, lang, source){
  const wrap=document.createElement('div'); wrap.className='cb-block';
  const bar=document.createElement('div'); bar.className='cb-bar';
  const l=document.createElement('span'); l.className='cb-lang'; l.textContent=lang||'kod';
  const sp=document.createElement('span'); sp.className='spacer';
  bar.append(l, sp, makeCopyBtn(()=>source));
  const npre=document.createElement('pre'); npre.className='cb-pre';
  const ncode=document.createElement('code'); if(lang)ncode.className='language-'+lang; ncode.textContent=source;
  npre.appendChild(ncode);
  wrap.append(bar, npre);
  pre.replaceWith(wrap);
}
function buildMermaidBlock(pre, source){
  const wrap=document.createElement('div'); wrap.className='mmd-block';
  const bar=document.createElement('div'); bar.className='cb-bar';
  const l=document.createElement('span'); l.className='cb-lang'; l.textContent='mermaid';
  const seg=document.createElement('div'); seg.className='cb-seg';
  const bD=document.createElement('button'); bD.type='button'; bD.textContent='Diagram'; bD.className='active';
  const bC=document.createElement('button'); bC.type='button'; bC.textContent='Kod';
  seg.append(bD, bC);
  const sp=document.createElement('span'); sp.className='spacer';
  bar.append(l, seg, sp, makeCopyBtn(()=>source));
  const dia=document.createElement('div'); dia.className='mmd-diagram';
  const npre=document.createElement('pre'); npre.className='cb-pre'; npre.style.display='none';
  const ncode=document.createElement('code'); ncode.className='language-mermaid'; ncode.textContent=source; npre.appendChild(ncode);
  bD.onclick=()=>{dia.style.display=''; npre.style.display='none'; bD.classList.add('active'); bC.classList.remove('active');};
  bC.onclick=()=>{dia.style.display='none'; npre.style.display=''; bC.classList.add('active'); bD.classList.remove('active');};
  wrap.append(bar, dia, npre);
  pre.replaceWith(wrap);
  renderMermaid(dia, source);
}

/** Ritar om alla redan renderade Mermaid-diagram i chatten när ljust/mörkt läge togglas (utan omladdning). */
async function rerenderAllMermaid(theme){
  if(!window.mermaid) return;
  const blocks=document.querySelectorAll('.mmd-block');
  if(!blocks.length) return;
  window.mermaid.initialize({startOnLoad:false, securityLevel:'loose', theme: theme==='dark' ? 'dark' : 'base', flowchart:{htmlLabels:true, useMaxWidth:true}});
  for(const block of blocks){
    const dia=block.querySelector('.mmd-diagram');
    const code=block.querySelector('code.language-mermaid');
    if(dia && code) await renderMermaid(dia, code.textContent);
  }
}
// Dispatchas av temats assets/js/theme.js när man togglar ljust/mörkt läge.
window.addEventListener('gbg-theme-change', e=>rerenderAllMermaid(e.detail && e.detail.theme));

function enhanceCodeBlocks(container, opts){
  opts=opts||{}; const renderDiagrams=opts.renderDiagrams!==false;
  container.querySelectorAll('pre > code').forEach(code=>{
    const pre=code.parentElement;
    if(!pre || pre.closest('.cb-block, .mmd-block')) return;
    const m=(code.className||'').match(/language-([\w-]+)/);
    const lang=m?m[1]:'';
    const source=code.textContent;
    if(lang==='mermaid' && renderDiagrams){ buildMermaidBlock(pre, source); }
    else { buildCodeBlock(pre, lang, source); }
  });
}
function scrollBottom(){const s=$('chatScroll'); s.scrollTop=s.scrollHeight;}

/**
 * role: 'user' (ren text) | 'assistant' (Markdown, renderas) |
 * 'system' (färdig, redan säker HTML — används av /files och /search,
 * som INTE ska in i den vanliga LLM-konversationen, se send()).
 */
function addMsg(role, text){
  $('emptyState').style.display='none';
  const wrap=document.createElement('div');
  wrap.className='msg '+role;
  const avatarText = role==='user' ? 'Du' : role==='system' ? '⌘' : 'AI';
  const nameText = role==='user' ? 'Du' : role==='system' ? 'Kommando' : (skillMeta.name||'Skill-assistent');
  wrap.innerHTML=`<div class="avatar">${avatarText}</div>
    <div class="body"><div class="name">${nameText}</div>
    <div class="bubble"></div></div>`;
  const bubble=wrap.querySelector('.bubble');
  if(role==='user'){bubble.textContent=text;}
  else if(role==='system'){bubble.innerHTML=text;}
  else{bubble.innerHTML=renderMd(text);}
  $('chatScroll').appendChild(wrap);
  scrollBottom();
  return bubble;
}

/* ---------- /files och /search (/sok) — bläddra & sök i wikins /content ----------
 * Lägger till valda sidor i "files" precis som en skill — de dyker upp i
 * "Filer i kontext" i sidopanelen och skickas med i nästa fråga till LLM:en.
 */

/** Hämtar en sidas innehåll och lägger den i kontexten. `btn` (valfri) får visuell status. */
async function addContentItemById(id, btn){
  if(btn){ btn.disabled=true; btn.textContent='Laddar…'; }
  try{
    const res=await fetch('?action=get-content&id='+encodeURIComponent(id));
    if(!res.ok) throw new Error('HTTP '+res.status);
    const data=await res.json();
    if(data.error) throw new Error(data.error);
    addFile(data.path, data.content, true);
    afterLoad();
    if(btn) btn.textContent='✓ Tillagd';
    toast('Tillagd i kontext: '+data.path);
    return true;
  }catch(err){
    if(btn){ btn.disabled=false; btn.textContent='+ Lägg till'; }
    alert('Kunde inte lägga till sidan: '+err.message);
    return false;
  }
}

function bindContentAddButtons(container){
  container.querySelectorAll('.content-add-btn').forEach(btn=>{
    btn.onclick=()=>addContentItemById(btn.dataset.id, btn);
  });
}

/** Antal rader som visas åt gången i chattens listkommandon, se renderPagedList(). */
const CHAT_PAGE_SIZE = 50;

/**
 * Renderar `items` som ett system-meddelande i sidor om CHAT_PAGE_SIZE åt
 * gången, med en "Visa fler"-knapp som avslöjar nästa omgång — istället
 * för att dumpa hela listan (kan vara hundratals sidor/taggar) på en gång.
 *
 * headerHtml : HTML ovanför listan (antal-text, ev. "Lägg till alla"-knapp).
 * renderItem : (item) => HTML för en rad.
 * bindFn     : (valfri) (msgEl) => binder knappar i de rader som just
 *              visats — körs om (från scratch, men idempotent — sätter
 *              bara om .onclick) varje gång "Visa fler" klickas.
 * opts.listTag/listClass : 'ul'/'content-result-list' som standard, byt
 *              t.ex. till 'div'/'tag-cloud' för tagg-/namespace-moln.
 * Returnerar meddelandets .msg-element (för att t.ex. binda en
 * "Lägg till alla"-knapp i headern separat, se bindAddAllButton()).
 */
function renderPagedList(headerHtml, items, renderItem, bindFn, opts){
  opts = opts || {};
  const listTag   = opts.listTag   || 'ul';
  const listClass = opts.listClass || 'content-result-list';

  const bubble = addMsg('system', headerHtml + '<' + listTag + ' class="' + listClass + '"></' + listTag + '>');
  const msgEl  = bubble.closest('.msg');
  const list   = bubble.querySelector('.' + listClass.split(' ')[0]);
  let shown = 0, moreBtn = null;

  function showNext(){
    const slice = items.slice(shown, shown + CHAT_PAGE_SIZE);
    list.insertAdjacentHTML('beforeend', slice.map(renderItem).join(''));
    shown += slice.length;
    if(bindFn) bindFn(msgEl);
    if(shown < items.length){
      if(!moreBtn){
        moreBtn = document.createElement('button');
        moreBtn.type = 'button';
        moreBtn.className = 'btn btn-secondary content-more-btn';
        moreBtn.style.marginTop = '.6rem';
        moreBtn.onclick = showNext;
        bubble.appendChild(moreBtn);
      }
      moreBtn.textContent = 'Visa fler (' + (items.length - shown) + ' kvar av ' + items.length + ')';
    }else if(moreBtn){
      moreBtn.remove();
      moreBtn = null;
    }
  }
  showNext();
  return msgEl;
}

/** Tolkar /files-argument: "-a" alfabetiskt, "-d" senast ändrad, övrig text = filnamnsfilter. */
function parseFilesArg(arg){
  const tokens = (arg || '').split(/\s+/).filter(Boolean);
  let sort = 'alpha';
  const rest = [];
  for(const t of tokens){
    if(t === '-a') sort = 'alpha';
    else if(t === '-d') sort = 'date';
    else rest.push(t);
  }
  return { sort, filter: rest.join(' ') };
}

async function cmdListFiles(arg){
  const { sort, filter } = parseFilesArg(arg);
  try{
    const qs = new URLSearchParams({ sort });
    if(filter) qs.set('filter', filter);
    const res=await fetch('?action=list-content&'+qs.toString());
    if(!res.ok) throw new Error('HTTP '+res.status);
    const items=await res.json();
    if(!items.length){
      addMsg('system', filter
        ? '<p>Inga sidor med <code>'+escapeHtml(filter)+'</code> i filnamnet.</p>'
        : '<p>Inga sidor hittades i /content.</p>');
      return;
    }
    const header = '<p>'+items.length+' sida(or) i wikin'
      + (filter ? ' med <code>'+escapeHtml(filter)+'</code> i filnamnet' : '')
      + ', sorterat '+(sort==='date' ? 'på senast ändrad' : 'alfabetiskt')+':</p>';
    renderPagedList(header, items, p=>`<li><button type="button" class="content-add-btn" data-id="${escapeHtml(p.id)}">+ Lägg till</button> <a href="${escapeHtml(p.url)}" target="_blank" rel="noopener">${escapeHtml(p.title)}</a> <code>${escapeHtml(p.id)}</code></li>`, bindContentAddButtons);
  }catch(err){
    addMsg('system', '<p>⚠️ Kunde inte hämta sidlistan: '+escapeHtml(err.message)+'</p>');
  }
}

async function cmdSearchContent(query){
  try{
    const res=await fetch('?action=search-content&q='+encodeURIComponent(query));
    if(!res.ok) throw new Error('HTTP '+res.status);
    const items=await res.json();
    if(!items.length){ addMsg('system', '<p>Inga träffar för <code>'+escapeHtml(query)+'</code>.</p>'); return; }
    const header = '<p>'+items.length+' träff(ar) för <code>'+escapeHtml(query)+'</code>:</p>';
    renderPagedList(header, items, p=>`<li><button type="button" class="content-add-btn" data-id="${escapeHtml(p.id)}">+ Lägg till</button> <a href="${escapeHtml(p.url)}" target="_blank" rel="noopener">${escapeHtml(p.title)}</a><span class="content-excerpt">${escapeHtml(p.excerpt||'')}</span></li>`, bindContentAddButtons);
  }catch(err){
    addMsg('system', '<p>⚠️ Sökningen misslyckades: '+escapeHtml(err.message)+'</p>');
  }
}

/**
 * Binder "Lägg till alla"-knappen — lägger till ALLA `items` (inte bara de
 * som råkar vara synliga just nu efter paginering), och återanvänder en
 * redan synlig radknapp för visuell status när en sådan finns.
 */
function bindAddAllButton(container, items){
  const allBtn = container.querySelector('.content-add-all-btn');
  if(!allBtn) return;
  allBtn.onclick = async () => {
    allBtn.disabled = true; allBtn.textContent = 'Lägger till…';
    for(const item of items){
      const btn = container.querySelector('.content-add-btn[data-id="'+CSS.escape(item.id)+'"]');
      if(btn && btn.disabled) continue; // redan tillagd
      await addContentItemById(item.id, btn);
    }
    allBtn.textContent = '✓ Alla tillagda ('+items.length+')';
  };
}

async function cmdListTags(){
  try{
    const res=await fetch('?action=list-tags');
    if(!res.ok) throw new Error('HTTP '+res.status);
    const tags=await res.json();
    if(!tags.length){ addMsg('system', '<p>Inga taggar hittades i wikin.</p>'); return; }
    const header = '<p>'+tags.length+' tagg(ar) i wikin — klicka för att se sidorna:</p>';
    renderPagedList(header, tags, t=>`<button type="button" class="tag tag-pick-btn" data-tag="${escapeHtml(t.tag)}">${escapeHtml(t.tag)} <span class="tag-count">${t.count}</span></button>`,
      wrap=>wrap.querySelectorAll('.tag-pick-btn').forEach(btn=>{
        btn.onclick=()=>{
          addMsg('user', '/tag '+btn.dataset.tag);
          cmdTagContent(btn.dataset.tag);
        };
      }),
      { listTag: 'div', listClass: 'tag-cloud' });
  }catch(err){
    addMsg('system', '<p>⚠️ Kunde inte hämta taggar: '+escapeHtml(err.message)+'</p>');
  }
}

async function cmdTagContent(tag){
  try{
    const res=await fetch('?action=search-content&q='+encodeURIComponent('tag:'+tag));
    if(!res.ok) throw new Error('HTTP '+res.status);
    const items=await res.json();
    if(!items.length){ addMsg('system', '<p>Inga sidor taggade <code>'+escapeHtml(tag)+'</code>.</p>'); return; }
    const header = '<p>'+items.length+' sida(or) taggade <code>'+escapeHtml(tag)+'</code>:</p>'
      + '<button type="button" class="btn btn-secondary content-add-all-btn" style="margin-bottom:.6rem">+ Lägg till alla ('+items.length+')</button>';
    const msgEl = renderPagedList(header, items, p=>`<li><button type="button" class="content-add-btn" data-id="${escapeHtml(p.id)}">+ Lägg till</button> <a href="${escapeHtml(p.url)}" target="_blank" rel="noopener">${escapeHtml(p.title)}</a><span class="content-excerpt">${escapeHtml(p.excerpt||'')}</span></li>`, bindContentAddButtons);
    bindAddAllButton(msgEl, items);
  }catch(err){
    addMsg('system', '<p>⚠️ Kunde inte hämta sidor för taggen: '+escapeHtml(err.message)+'</p>');
  }
}

async function cmdListNamespaces(){
  try{
    const res=await fetch('?action=list-namespaces');
    if(!res.ok) throw new Error('HTTP '+res.status);
    const items=await res.json();
    if(!items.length){ addMsg('system', '<p>Inga namespaces (mappar) hittades i wikin.</p>'); return; }
    const header = '<p>'+items.length+' namespace(r) i wikin — klicka för att se sidorna:</p>';
    renderPagedList(header, items, n=>`<button type="button" class="tag tag-pick-btn" data-ns="${escapeHtml(n.ns)}">${escapeHtml(n.label)} <span class="tag-count">${n.count}</span></button>`,
      wrap=>wrap.querySelectorAll('.tag-pick-btn').forEach(btn=>{
        btn.onclick=()=>{
          addMsg('user', '/namespace '+(btn.dataset.ns||'_root'));
          cmdNamespaceContent(btn.dataset.ns);
        };
      }),
      { listTag: 'div', listClass: 'tag-cloud' });
  }catch(err){
    addMsg('system', '<p>⚠️ Kunde inte hämta namespaces: '+escapeHtml(err.message)+'</p>');
  }
}

async function cmdNamespaceContent(rawNs){
  const ns    = /^(|_root|rot|root)$/i.test(rawNs.trim()) ? '_root' : rawNs.trim();
  const label = ns==='_root' ? 'Rot (sidor utan mapp)' : ns;
  try{
    const res=await fetch('?action=search-content&q='+encodeURIComponent('ns:'+ns));
    if(!res.ok) throw new Error('HTTP '+res.status);
    const items=await res.json();
    if(!items.length){ addMsg('system', '<p>Inga sidor i namespacet <code>'+escapeHtml(label)+'</code>.</p>'); return; }
    const header = '<p>'+items.length+' sida(or) i <code>'+escapeHtml(label)+'</code>:</p>'
      + '<button type="button" class="btn btn-secondary content-add-all-btn" style="margin-bottom:.6rem">+ Lägg till alla ('+items.length+')</button>';
    const msgEl = renderPagedList(header, items, p=>`<li><button type="button" class="content-add-btn" data-id="${escapeHtml(p.id)}">+ Lägg till</button> <a href="${escapeHtml(p.url)}" target="_blank" rel="noopener">${escapeHtml(p.title)}</a><span class="content-excerpt">${escapeHtml(p.excerpt||'')}</span></li>`, bindContentAddButtons);
    bindAddAllButton(msgEl, items);
  }catch(err){
    addMsg('system', '<p>⚠️ Kunde inte hämta sidor för namespacet: '+escapeHtml(err.message)+'</p>');
  }
}

/**
 * Tar bort en fil ur kontexten (files-arrayen) via dess path. Slår upp
 * på path (inte index) varje gång — så knappar i gamla /context-svar
 * fortsätter fungera även om andra filer lagts till/tagits bort emellan.
 */
function removeFileByPath(path){
  const i = files.findIndex(f=>f.path===path);
  if(i<0) return false;
  files.splice(i,1);
  renderFiles();
  return true;
}

function bindContentRemoveButtons(container){
  container.querySelectorAll('.content-remove-btn').forEach(btn=>{
    btn.onclick=()=>{
      const path=btn.dataset.path;
      if(removeFileByPath(path)){
        btn.textContent='✓ Borttagen';
        btn.disabled=true;
        btn.closest('li').classList.add('removed');
        toast('Borttagen ur kontext: '+path);
      }else{
        btn.disabled=true;
        btn.textContent='Redan borttagen';
      }
    };
  });
}

/** /context (/kontext) — listar alla .md-filer som just nu ligger i kontexten och låter en ta bort dem. */
async function cmdListContext(){
  const mdFiles = files.filter(f=>/\.md$/i.test(f.path));
  if(!mdFiles.length){
    addMsg('system', '<p>Inga Markdown-filer (.md) i kontexten just nu. Använd <code>/files</code>, <code>/search</code> eller <code>/tag</code> för att lägga till wikisidor, eller ladda en skill.</p>');
    return;
  }
  const header = '<p>'+mdFiles.length+' Markdown-fil(er) i kontexten:</p>';
  renderPagedList(header, mdFiles, f=>`<li><button type="button" class="content-remove-btn" data-path="${escapeHtml(f.path)}">✕ Ta bort</button> <span class="fname">${escapeHtml(f.path)}</span><span class="content-excerpt">${f.include?'med i nästa fråga':'avbockad'} · ${fmtSize(f.size)}</span></li>`, bindContentRemoveButtons);
}

async function send(){
  if(streaming){ if(abortCtrl)abortCtrl.abort(); return; }
  const input=$('input'); const text=input.value.trim();
  if(!text)return;

  // Snabbkommandon: /files (/f) listar wikins sidor, /search (/s, eller
  // /sok) söker bland dem, /tag (/t) listar taggar (eller visar sidor för
  // en given tagg), /namespace (/ns, eller /folder) listar
  // namespaces/mappar (eller visar/lägger till alla sidor i ett givet
  // namespace), /context (/c, eller /kontext) listar/tar bort .md-filer i
  // kontexten. Korta alias normaliseras till de fulla namnen innan
  // grenarna nedan (som förblir oförändrade).
  // Körs lokalt mot servern — ingen LLM inblandad, och hamnar INTE i
  // "history" (skickas alltså inte med till modellen).
  const cmd = text.match(/^\/(files|f|search|s|sok|tag|t|namespace|ns|folder|context|c|kontext)\b\s*(.*)$/i);
  if(cmd){
    addMsg('user', text);
    input.value=''; autoGrow();
    const cmdAliases = { f:'files', s:'search', t:'tag', ns:'namespace', c:'context' };
    const rawName = cmd[1].toLowerCase();
    const name = cmdAliases[rawName] || rawName;
    const arg  = cmd[2].trim();
    if(name==='files'){
      await cmdListFiles(arg);
    }else if(name==='tag'){
      if(!arg) await cmdListTags();
      else await cmdTagContent(arg);
    }else if(name==='namespace'||name==='folder'){
      if(!arg) await cmdListNamespaces();
      else await cmdNamespaceContent(arg);
    }else if(name==='context'||name==='kontext'){
      await cmdListContext();
    }else{
      if(!arg) addMsg('system', '<p>Ange en sökterm, t.ex. <code>/search wiki</code>.</p>');
      else await cmdSearchContent(arg);
    }
    return;
  }

  if(!config.model){openSettings(); return;}
  if(files.filter(f=>f.isText&&f.include).length===0){
    if(!confirm('Ingen kontext är vald (varken skill eller sidor). Vill du fråga ändå?'))return;
  }

  addMsg('user', text);
  history.push({role:'user', content:text});
  input.value=''; autoGrow();

  const ctx=buildContext();
  const sys=config.sysPrompt.replace('{context}', ctx);
  const messages=[{role:'system', content:sys}, ...history];

  const bubble=addMsg('assistant','');
  bubble.classList.add('cursor-blink');
  let acc='';
  streaming=true; abortCtrl=new AbortController();
  setSendState(true);

  // Strypning: renderMd() kör om Markdown-parsning + DOMPurify-sanering på
  // HELA det ackumulerade svaret varje gång den anropas. Utan strypning
  // anropas den en gång per streamad token, vilket blir O(n²) totalt arbete
  // och kan frysa fliken vid långa/snabba svar (t.ex. lokala modeller utan
  // nätverksfördröjning). Rendera därför max en gång per skärmuppdatering.
  let rafId=null;
  function scheduleRender(){
    if(rafId!==null)return;
    rafId=requestAnimationFrame(()=>{
      rafId=null;
      bubble.innerHTML=renderMd(acc);
      scrollBottom();
    });
  }

  try{
    await callLLM(messages, tok=>{ acc+=tok; scheduleRender(); }, abortCtrl.signal);
    if(!acc.trim())acc='*(tomt svar)*';
    history.push({role:'assistant', content:acc});
  }catch(err){
    if(err.name==='AbortError'){ acc+= (acc?'\n\n':'')+'*(avbrutet)*'; if(acc)history.push({role:'assistant',content:acc}); }
    else{ acc='⚠️ **Fel:** '+err.message+'\n\nKontrollera bas-URL, modell och att servern tillåter anrop (CORS).'; }
    bubble.innerHTML=renderMd(acc);
  }finally{
    if(rafId!==null){ cancelAnimationFrame(rafId); rafId=null; } // undvik en extra, redundant render nästa frame
    bubble.classList.remove('cursor-blink');
    bubble.innerHTML=renderMd(acc);
    enhanceCodeBlocks(bubble, {renderDiagrams:true});
    streaming=false; abortCtrl=null; setSendState(false);
    scrollBottom();
  }
}
function setSendState(on){
  const b=$('sendBtn');
  b.classList.toggle('stop', on);
  b.innerHTML= on?'■':'➤';
  b.title= on?'Stoppa':'Skicka';
}

$('sendBtn').onclick=send;

/* ---------- Ladda ner chatt som Markdown ---------- */
function pad2(n){return String(n).padStart(2,'0');}
function downloadChatMd(){
  if(!history.length){ toast('Ingen chatt att ladda ner ännu'); return; }
  const d=new Date();
  const ymd=`${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
  const stamp=`${ymd} ${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
  let md='# SKILL Chat\n\n';
  md+=`- **Skill:** ${skillMeta.name||'—'}\n`;
  md+=`- **Modell:** ${config.model?PRESETS[config.provider].label+' / '+config.model:'—'}\n`;
  md+=`- **Exporterad:** ${stamp}\n\n---\n\n`;
  history.forEach(m=>{
    const who = m.role==='user' ? 'Du' : (skillMeta.name ? skillMeta.name+' (assistent)' : 'Skill-assistent');
    md+=`## ${who}\n\n${m.content}\n\n`;
  });
  const blob=new Blob([md],{type:'text/markdown;charset=utf-8'});
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a'); a.href=url; a.download=`skill-chat_${ymd}.md`;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(()=>URL.revokeObjectURL(url),1000);
  toast('Chatt nedladdad');
}
$('downloadBtn').onclick=downloadChatMd;

const input=$('input');
function autoGrow(){input.style.height='auto'; input.style.height=Math.min(input.scrollHeight,160)+'px';}
input.addEventListener('input', autoGrow);
input.addEventListener('keydown', e=>{ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault(); send();} });

/* ---------- File preview popup ---------- */
let fvState={file:null, isMd:false, raw:false};
function escapeHtml(s){return s.replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));}

function openFilePreview(i){
  const f=files[i]; if(!f)return;
  fvState.file=f;
  $('fvPath').textContent=f.path;
  $('fvPath').title=f.path;
  $('fvSize').textContent=fmtSize(f.size);
  const body=$('fvBody'); const seg=$('fvSeg'); const copy=$('fvCopy');
  if(f.isImage){
    seg.style.display='none'; copy.style.display='none';
    body.innerHTML=`<div class="fv-img"><img src="${f.dataUrl}" alt="${escapeHtml(f.path)}"></div>`;
  }else if(f.isText){
    copy.style.display='inline-block';
    fvState.isMd=/\.(md|markdown)$/i.test(f.path);
    fvState.raw=!fvState.isMd;
    seg.style.display=fvState.isMd?'flex':'none';
    renderFileView();
  }else{
    seg.style.display='none'; copy.style.display='none';
    body.innerHTML=`<div class="fv-empty">📦 Förhandsvisning är inte tillgänglig för den här filtypen.<br><strong>${escapeHtml(f.path)}</strong> · ${fmtSize(f.size)}</div>`;
  }
  $('fileOverlay').classList.add('open');
}
function renderFileView(){
  const f=fvState.file; const body=$('fvBody');
  if(fvState.isMd && !fvState.raw){
    body.innerHTML='<div class="fv-md">'+renderMd(f.content)+'</div>';
    enhanceCodeBlocks(body, {renderDiagrams:false});
  }else{
    body.innerHTML='<pre class="fv-pre">'+escapeHtml(f.content)+'</pre>';
  }
  $('fvRendered').classList.toggle('active', !fvState.raw);
  $('fvRaw').classList.toggle('active', fvState.raw);
}
$('fvRendered').onclick=()=>{fvState.raw=false; renderFileView();};
$('fvRaw').onclick=()=>{fvState.raw=true; renderFileView();};
$('fvCopy').onclick=async ()=>{
  if(!fvState.file)return;
  try{ await navigator.clipboard.writeText(fvState.file.content); const b=$('fvCopy'); b.textContent='Kopierat ✓'; b.classList.add('copied'); setTimeout(()=>{b.textContent='Kopiera'; b.classList.remove('copied');},1500); }
  catch(e){ alert('Kunde inte kopiera.'); }
};
function closeFilePreview(){$('fileOverlay').classList.remove('open');}
$('fvClose').onclick=closeFilePreview;
$('fileOverlay').onclick=e=>{if(e.target===$('fileOverlay'))closeFilePreview();};
document.addEventListener('keydown', e=>{ if(e.key==='Escape'){closeFilePreview(); closeSettings();} });

/* mobile sidebar */
$('menuBtn').onclick=()=>$('sidebar').classList.toggle('open');

/* ---------- Kollapsbar sidfot (mer plats åt chatten) ----------
 * Temats sidfot (sidindex + footer) tar plats som annars skulle gått
 * till meddelandelistan. Döljs helt via body.footer-collapsed (se
 * <style> ovan) — .chat-app fyller automatiskt ut resten. Dold som
 * standard vid första besöket; valet minns sig sedan mellan besök via
 * localStorage, precis som ljust/mörkt läge.
 */
const FOOTER_COLLAPSE_KEY='chatFooterCollapsed';
function setFooterCollapsed(on){
  document.body.classList.toggle('footer-collapsed', on);
  const btn=$('footerToggleBtn');
  btn.textContent = on ? '⌃' : '⌄';
  btn.title = on ? 'Visa sidfot' : 'Dölj sidfot (mer plats åt chatten)';
  btn.setAttribute('aria-label', btn.title);
  btn.setAttribute('aria-pressed', String(on));
  try{ localStorage.setItem(FOOTER_COLLAPSE_KEY, on ? '1' : '0'); }catch(e){ /* privat läge etc. — strunta i att minnas */ }
}
$('footerToggleBtn').onclick=()=>setFooterCollapsed(!document.body.classList.contains('footer-collapsed'));
(function initFooterCollapsed(){
  let collapsed=true; // dold som standard (mer plats åt chatten) tills man själv visar den
  try{
    const stored=localStorage.getItem(FOOTER_COLLAPSE_KEY);
    if(stored!==null) collapsed = stored==='1'; // respektera ett tidigare eget val
  }catch(e){}
  setFooterCollapsed(collapsed);
})();

/* ---------- Init ---------- */
(async function init(){
  if(!window.indexedDB){ toast('Varning: IndexedDB stöds inte – inställningar kan inte sparas'); }
  try{
    const saved=await idbGet('config');
    if(saved)config={...config, ...saved};
  }catch(err){ console.warn('Kunde inte läsa sparade inställningar:', err); }
  updateStatus();
  await renderBuiltins();
  marked.setOptions({breaks:true, gfm:true});
  if(window.mermaid){
    try{
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      window.mermaid.initialize({startOnLoad:false, securityLevel:'loose', theme: isDark ? 'dark' : 'base', flowchart:{htmlLabels:true, useMaxWidth:true}});
    }
    catch(e){ console.warn('Mermaid init misslyckades:', e); }
  }
  const docParam = new URLSearchParams(location.search).get('doc');
  if(docParam) await loadDocFromQuery(docParam);
})();
</script>
</body>
</html>
