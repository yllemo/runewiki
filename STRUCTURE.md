# RuneWiki 0.2 — Struktur & Arkitektur

Databasfri wiki-motor i PHP 8.1+. All kod nedan är implementerad och körbar.

```
runewiki/
├── index.php                    ← Front controller + autoloader (klassnamn = filnamn i core/).
│                                  Delegerar /chat och /admin till sina egna front controllers
├── .htaccess                    ← Rewrite-regler + direktåtkomstskydd (Apache)
├── composer.json                ← Inga externa beroenden; kräver bara ext-mbstring
├── LICENSE                      ← MIT
│
├── config/
│   ├── config.php               ← Aktiv konfiguration (site_name, theme, header_logo/
│   │                              footer_logo, cache, auth…)
│   ├── config.example.php       ← Mall — kopiera till config.php vid ny installation
│   ├── menu.php                 ← Huvudnavigering (label + target page-id)
│   ├── interwiki.php            ← Genvägar: [[wp>Artikel]], [[github>…]] m.fl.
│   ├── media.php                ← Tillåtna filtyper och maxstorlek för uppladdning
│   ├── plugins.php              ← Aktiverade plugins och deras options
│   ├── namespaces.php           ← Namespace-regler — 'menu'-override per namespace tillämpas
│   │                              (se Wiki::menuFor()); 'theme'/'acl' läses in men tillämpas
│   │                              ej ännu
│   ├── strings.php              ← Skriv över valfria UI-texter (tagline, sidfot, knappar…);
│   │                              övriga faller tillbaka på Helpers::defaultStrings()
│   └── acl.php                  ← Grupper/rättigheter (grund finns, tillämpas ej ännu)
│
├── content/                     ← All wiki-text som Markdown-filer
│   ├── start.md                 ← Rotsidan
│   ├── _sidebar.md              ← Global sidopanel (fallback om namespace saknar egen)
│   ├── _topbar.md               ← Styr toppmenyns knappar (ersätter config/menu.php om den finns)
│   └── <namespace>/
│       ├── _sidebar.md          ← Sidopanel för namespacet
│       └── <sida>.md
│
├── media/                       ← Uppladdade filer; speglar content/-namespacen
│
├── core/                        ← Motorns kärnklasser (en klass per fil)
│   ├── Wiki.php                 ← Bygger alla komponenter, dispatchar requesten, kör hooks
│   ├── Router.php               ← URL + ?do= → view/edit/save/delete/download/search/
│   │                              media-*/login/logout
│   ├── PageId.php               ← Värdesobjekt: ID ↔ filsökväg ↔ URL; saniterar mot path traversal
│   ├── MediaId.php              ← Samma mappningsregel för media-filer
│   ├── PageLoader.php           ← Läs/skriv/radera/lista .md-filer; skickar raw till FrontMatter
│   ├── FrontMatter.php          ← YAML-frontmatter: parse(), build(); förhindrar nyrads-injektion
│   ├── Parser.php               ← Markdown + wikilänk + interwiki + media; token-baserad XSS-skydd
│   ├── Media.php                ← Uppladdning (validering), listning, borttagning
│   ├── Search.php               ← Fulltextsökning + queryByTag() för tag:-sökning
│   ├── Cache.php                ← HTML-cache med atomära skrivningar och automatisk stale-rensning
│   ├── NamespaceResolver.php    ← Namespace-inställningar med fallback till root
│   ├── TemplateEngine.php       ← Renderar PHP-vyer i templates/<tema>/; fallback till 'default'
│   ├── PluginManager.php        ← Laddar plugins, hook-register (on/trigger/listAvailable)
│   ├── PluginInterface.php      ← Kontrakt: register(PluginManager, array $options): void
│   ├── History.php              ← Ögonblicksbilder vid sparning i data/history/ (av/på via config)
│   ├── Auth.php                 ← Filbaserad inloggning; startar session med HttpOnly/SameSite=Lax;
│   │                              canEdit() tillämpas i Wiki::handleEdit/Save/Delete/MediaUpload
│   └── Helpers.php              ← e(), slugify, sanitizeFilename/PathSegment, csrfToken(), verifyCsrf()
│
├── plugins/
│   └── example-plugin/          ← Exempelplugin: visar "Senast ändrad"-datum (inaktivt som standard)
│       ├── plugin.json          ← Metadata + options-schema
│       ├── plugin.php           ← Implementerar PluginInterface; hook: page_view
│       └── assets/style.css     ← Plats för plugin-specifika stilar
│
├── templates/default/           ← Tema (enda inbyggda, aktivt) — stil enligt goteborg-dw-template:
│   │                              vitt topphuvud, blå huvudmeny, brödsmulor, kortlayout, mörk sidfot
│   ├── template.json            ← Tema-metadata (name, version, description, author)
│   ├── layout.php               ← Yttre HTML-skal (head, header, sidebar, main, footer)
│   ├── header.php               ← Topphuvud (två logotyper, ljust/mörkt läge) + blå meny med
│   │                              hamburgare (mobil, <768px) + sök-/tema-/menydropdowns +
│   │                              brödsmulor + login/logout-knapp (om auth_enabled = true)
│   ├── sidebar.php              ← Renderar namespacets _sidebar.md i kortstil
│   ├── page.php                 ← Sidinnehåll + verktygsrad (Redigera/Ladda ner .md/Sök
│   │                              liknande/AI Chat) + YAML-metadata + tagg-badges
│   ├── missing.php              ← "Sidan finns inte — Skapa sidan"-vy
│   ├── login.php                ← Inloggningsformulär (/?do=login), CSRF-skyddat
│   ├── edit.php                 ← Monaco Editor med tre IntelliSense-providers (se nedan)
│   ├── search.php               ← Sökresultat; tagg-rubrik vid tag:-sökning; "Skapa sida"-förslag
│   ├── media.php                ← Bildgalleri (rutnät med miniatyrer) + ikonkort för övriga
│   │                              filtyper. /media visar ALLA namespaces grupperat; /media/<ns>
│   │                              bara ett. Uppladdningsformulär bara om canUpload (inloggad)
│   ├── footer.php               ← Sidindex (paginerat, "Visa fler") + mörk sidfot med two-kolumner
│   └── assets/
│       ├── css/style.css        ← GS-färger, ljust/mörkt läge, hamburgare/mobil-brytpunkter,
│       │                          Monaco-container, footer-index
│       ├── js/theme.js          ← Dark/light-toggle (dispatchar "gbg-theme-change") + hamburgare
│       │                          + dropdown-menyer (localStorage)
│       ├── js/mermaid.js        ← Renderar mermaid-kodblock till diagram (laddas villkorat,
│       │                          se layout.php); ritar om alla diagram live vid "gbg-theme-
│       │                          change" (sparar källan i data-mermaid-source för det) —
│       │                          samma .mmd-diagram/.mmd-err-klasser som /chat
│       └── img/
│           ├── favicon.svg      ← SVG-favicon (dokument-ikon i Göteborgsblå)
│           ├── logo.svg         ← Platshållarlogotyp (ljust läge + footer)
│           └── logo-dark.svg    ← Platshållarlogotyp för mörkt läge (genomskinlig, ljus text)
│
├── chat/
│   └── index.php                ← Egen front controller: klientdriven AI-chatt. Återanvänder
│                                  aktivt temas header.php/footer.php (samma TemplateEngine)
│                                  — sitenamn, toppmeny, ljust/mörkt läge och CSS delas med
│                                  resten av wikin. Serverar sidan + JSON-endpoints
│                                  (list-skills, get-skill, list-content, search-content,
│                                  get-content, list-tags) som chattens JS anropar för
│                                  skills (/skills) och snabbkommandona /files, /search
│                                  (/sok) och /tag. Inga LLM-nycklar passerar servern —
│                                  allt sparas i klientens IndexedDB.
│
├── admin/
│   └── index.php                ← Egen front controller (som chat/index.php), men återanvänder
│                                  wikins vanliga layout.php. Bara synlig när Auth::currentUser()
│                                  är satt (oavsett auth_enabled). Byt eget lösenord (sparas som
│                                  bcrypt-hash — CLI-fri väg ut ur klartextläge, t.ex. på
│                                  OpenShift), skapa/ta bort användare, ändra sitenamn/
│                                  auth_enabled (riktad textersättning i config/config.php,
│                                  bevarar kommentarerna).
│
├── skills/                      ← AI-chattens "skills"; blockerad för direkt HTTP-åtkomst
│   └── <skill>/
│       ├── SKILL.md             ← Krävs. YAML-frontmatter: name, description, icon, include
│       └── ...                  ← Ev. övriga textfiler i mappen buntas in automatiskt
│
├── data/
│   ├── cache/                   ← HTML-cache; en underkatalog per sida, filnamn = mtime
│   ├── history/                 ← Sid-versioner (om history_enabled = true)
│   ├── index/                   ← Reserverat för framtida sökindex
│   └── users/                   ← users.php för Auth.php ('användarnamn' => lösenord — bcrypt-
│                                  hash eller klartext, se Auth::isHashed()), hanteras via
│                                  /admin/ eller bin/cli.php create-user/delete-user
│
└── bin/cli.php                  ← CLI: clear-cache, list-pages, create-user, delete-user,
                                    list-users, help
```

---

## Sid-ID → filsökväg

Första kolondelen = mapp (namespace). Resten slås ihop med `.` till filnamnet:

| Sid-ID | Fil | URL |
|--------|-----|-----|
| `start` | `content/start.md` | `/` eller `/start` |
| `projekt:api` | `content/projekt/api.md` | `/projekt/api` |
| `namespace:page1:start` | `content/namespace/page1.start.md` | `/namespace/page1/start` |
| `a:b:c:d` | `content/a/b.c.d.md` | `/a/b/c/d` |

Samma mappningsregel gäller `MediaId` mot `/media`.

---

## Wikitext-syntax

| Syntax | Resultat |
|--------|----------|
| `[[namespace:sida]]` | Intern länk; röd streckad + `?do=edit` om sidan saknas |
| `[[namespace/sida]]` | Samma — snedstreck normaliseras till kolon |
| `[[namespace:sida\|Etikett]]` | Intern länk med egen länktext |
| `[[wp>Göteborg]]` | Interwiki-länk (config/interwiki.php) |
| `[[https://example.com]]` | Absolut URL i wiki-syntax |
| `[text](/namespace/sida)` | Markdown-länk till intern sida (kollar existens) |
| `[text](namespace:sida)` | Markdown-länk med kolon-ID |
| `[text](https://…)` | Extern Markdown-länk med `rel="noopener noreferrer"` |
| `{{namespace:bild.png}}` | Bildinbäddning från `/media` |
| `{{namespace:fil.pdf\|Ladda ner}}` | Nedladdningslänk |
| `**fet**`, `*kursiv*`, `` `kod` `` | Standard Markdown |
| ` ```php … ``` ` | Kodblock med valfri språkmarkering |
| ` ```mermaid … ``` ` | Renderas som ett Mermaid-diagram (assets/js/mermaid.js, laddas villkorat) |
| `# ` … `###### ` | Rubriker h1–h6 |
| `- ` / `1. ` | Oordnad / ordnad lista |
| `> ` | Citat |

---

## YAML-frontmatter

```yaml
---
title: Min sida
date: 2026-09-07
updated: 2026-09-07
author: Erik Andersson
tags: [wiki, exempel]
status: Publicerad
draft: false
description: Kort beskrivning
template: default
---
```

`FrontMatter::parse()` separerar blocket från brödtexten. `build()` återmonterar
filen vid sparning. Stödjer: strängar, listor `[a, b]`, booleans, heltal,
decimaltal, null. Platta nycklar — inga nästade objekt.

Taggar renderas som klickbara badges → `/?do=search&q=tag:nyckelord`.

---

## Request-flöde

1. `index.php` registrerar autoloader mot `core/` och skapar `Wiki`.
2. `Router::resolve()` tolkar `REQUEST_URI` + `?do=` till en intent.
3. `Wiki::run()` dispatchar till `handleView`, `handleEdit`, `handleSave`,
   `handleDelete`, `handleDownload`, `handleSearch`, `handleMediaUpload`,
   `handleMediaList`, `handleLogin` eller `handleLogout`.
4. **Sparning:** CSRF-token verifieras → är hela det postade innehållet
   tomt (`trim() === ''`, varken frontmatter eller brödtext kvar) tas
   sidan bort istället (med en historik-snapshot först) och man skickas
   till `/` — annars: `FrontMatter::parse()` separerar meta/body → titel
   hämtas från frontmatter-`title` eller första `# rubrik` →
   `PageLoader::save()` → `FrontMatter::build()` monterar ihop filen.
5. **Sidvisning:** HTML-cache slås upp med nyckel `page:<id>:<filemtime>`.
   Vid missar: `Parser::toHtml()` → cache. `page_view`-hooken körs alltid,
   även vid cache-träff.

---

## Editor

`edit.php` laddar Monaco Editor 0.52.0 från CDN. Hela råfilen (inkl. frontmatter)
skickas till editorn och returneras odelad vid spara — `Wiki::handleSave()` separerar.

Tre IntelliSense-providers registrerade på `markdown`-språket:

| Provider | Aktiveras när | Erbjuder |
|----------|--------------|----------|
| YAML-frontmatter | Markören är inuti `---`-blocket | Nycklar (utan dubletter), värden för `status` och `draft` |
| Wiki-interlinks | Texten innan markören matchar `\[\[…` | Befintliga sid-ID:n, filtrerat live |
| Markdown-snippets | Annars | H1–H4, fetstil, kursiv, kod, lista, tabell, `[[…]]`, `{{…}}`, `--- frontmatter` m.m. |

Providers är ömsesidigt uteslutande — frontmatter-förslag visas aldrig i brödtext
och vice versa.

`quickSuggestions: false` på editorn — inga automatiska förslag medan man
skriver löpande text. Wiki-interlink-providern har `triggerCharacters:
['[']` (öppnas automatiskt vid `[[`, filtrerar sedan live); de andra två
har inga `triggerCharacters` alls och visas bara via manuellt
<kbd>Ctrl+Space</kbd>.

---

## Cache

`Cache::get(key)` / `Cache::set(key, html)`. Nyckelformat: `page:<id>:<mtime>`.

**Filstruktur:** `data/cache/<sha1(stableKey)>/<version>.html`
- Underkatalog baseras på sid-ID (stabilt).
- Filnamnet är mtime-delen — bara en version per sida behövs.

**Atomär skrivning:** skriv till `<path>.<pid>.tmp`, döp sedan om med `rename()`.
Läsare ser aldrig en halvskriven fil (eliminerar race conditions).

**Automatisk stale-rensning:** `set()` tar bort alla gamla `.html`-filer i
underkatalogens mapp innan den skriver den nya. Cache-katalogen växer inte obegränsat.

**Global rensning vid spara/radera:** `Wiki::handleSave()` och `handleDelete()`
kör `Cache::clear()` (samma som `php bin/cli.php clear-cache`) efter varje
sparning/radering. Annars skulle andra sidors cachade HTML — som t.ex. en röd
`wikilink-new`-länk till en sida som just skapades — inte uppdateras förrän
källsidan själv sparades om, eftersom cache-nyckeln bara beror på den egna
sidans `mtime`.

---

## Säkerhet

| Skydd | Implementering |
|-------|----------------|
| XSS | `Parser::inline()` tokeniserar alla kända mönster; återstående klartext escapes med `htmlspecialchars()` |
| CSRF | `Helpers::csrfToken()` / `verifyCsrf()` med `hash_equals()`; krävs på save, delete, media-upload, login, logout och alla `/admin/`-formulär |
| Inloggning för redigering | `Auth::canEdit()` (binärt) gate:ar `handleEdit/Save/Delete/MediaUpload`; icke inloggade skickas till `/?do=login` (`Wiki::requireLogin()`) och hamnar tillbaka efter lyckad inloggning |
| Path traversal | `PageId`-constructor strippar `..`, `\`, `/`, `\0` ur varje del |
| Session-säkerhet | `Auth::__construct()` sätter `HttpOnly=true`, `SameSite=Lax` innan `session_start()` |
| Open redirect | `redirect_to` i media-upload valideras — måste börja med `/`, inte `//` |
| YAML-injektion | `FrontMatter::build()` tar bort `\n`/`\r` ur strängvärden |
| Innehållsstorlek | Max 512 KB per POST-body i `handleSave()` |
| Filkörning | `.htaccess` blockerar `config/`, `core/`, `content/`, `data/`, `plugins/`; PHP-motor av i `media/` |

---

## Plugin-system

`plugins/<namn>/plugin.php` definierar `<Namn>Plugin implements PluginInterface`.
Aktivering och options anges i `config/plugins.php`.

### Hooks

| Hook | Triggas | Modifierbara context-nycklar |
|------|---------|------------------------------|
| `after_parse` | Efter Markdown → HTML (resultatet cachas) | `html`, `markdown` |
| `page_view` | Vid varje sidvisning, alltid — även cache-träff | `html`, `id`, `page_meta`, `file_path`, `page` |
| `before_save` | Innan sidan sparas — kan modifiera innehåll | `title`, `body`, `meta` |
| `after_save` | Direkt efter sparning | `id`, `title`, `body` |

### Nytt plugin — mönster

```php
class MittPlugin implements PluginInterface
{
    public function register(PluginManager $manager, array $options = []): void
    {
        $manager->on('page_view', function (array $ctx) use ($options): array {
            $ctx['html'] .= '<p>Extra innehåll</p>';
            return $ctx;
        });
    }
}
```

Aktiveras i `config/plugins.php`:
```php
'mitt-plugin' => ['enabled' => true, 'options' => ['nyckel' => 'värde']],
```

---

## Teman

`templates/<tema>/` innehåller PHP-mallar + `template.json`.
Aktivt tema: `'theme' => '<tema>'` i `config/config.php`.
`TemplateEngine` faller tillbaka till `'default'` om mappen saknas.

Nytt tema: kopiera `templates/default/` → `templates/<mitt-tema>/`,
justera `template.json` och CSS.

---

## AI-chatt (/chat) och skills (/skills)

`chat/index.php` är en egen, självständig front controller — separat från
`Wiki`/`Router` i roten, men den bootstrapar `TemplateEngine` (samma klass
som `Wiki`) och renderar det AKTIVA temats riktiga `header`/`footer`-vyer
med `Helpers::loadTopbarMenu()` och `Helpers::buildPageTree()` (samma
statiska hjälpmetoder `Wiki` själv använder) — så chatten alltid är synkad
med sitenamn, toppmeny, tema-CSS och sidindex, oavsett vilket tema som är
aktivt i `config.php`. `view => 'chat'` i header-datan gör att
`header.php` automatiskt döljer "Redigera"-länken och brödsmulorna (de
finns bara för riktiga sidvyer). Allt annat CHAT-UI (sidopanel, bubblor,
komposer, modaler) styls av en `.chat-app`-scopad CSS-modul som ligger i
respektive temas egen `assets/css/style.css` — inte i `chat/index.php`.

Själva chattlogiken är fortfarande helt klientdriven (samma funktioner som
ursprungliga `html/chat.html`): filuppladdning/`.zip`/mapp, streaming-anrop
mot en OpenAI-kompatibel tjänst/LM Studio/Ollama direkt från webbläsaren,
inställningar i klientens IndexedDB (inkl. en "Rensa inställningar"-knapp),
Markdown/Mermaid-rendering (mermaid@latest från CDN), filförhandsvisning
och export av chatten till Markdown. Ljust/mörkt läge hanteras INTE längre
av chattens egen JS — det är samma `assets/js/theme.js` (och samma
`localStorage`-nyckel `"theme"`) som resten av wikin använder, inkluderad
längst ner i `<body>`.

Sidfoten (sidindex + footer, ärvd från temat) är dold som standard
(`body.footer-collapsed`-klass) för att ge `.chat-scroll` mer höjd — en
knapp i chat-toolbaren visar den igen, och valet minns sig sedan i
`localStorage` mellan besök. Systemprompten
(`DEFAULT_SYS`) refererar numera till "kontexten" generellt (skill +
tillagda sidor + uppladdade filer), inte bara en laddad skill —
`buildContext()` byggde redan `{context}` av alla ibockade filer, bara
texten var missvisande innan.

**Chatta med en specifik sida:** `/chat?doc=<sid-id>` laddar automatiskt
in den sidan i kontexten vid sidladdning (`loadDocFromQuery()`, samma
`get-content`-endpoint som "+ Lägg till"). Länkas från en "AI Chat"-knapp
direkt på varje sida (`templates/default/page.php`, bredvid
Redigera/Sök liknande) och från headerns egen AI Chat-knapp/menypost.

**Servern rör aldrig LLM-inställningar eller API-nycklar.** `chat/index.php`
exponerar bara skrivskyddade JSON-endpoints, anropade av samma klient-JS:

| Endpoint | Svar |
|---|---|
| `?action=list-skills` | `[{slug, name, desc, icon}, ...]` — för sidopanelens skill-lista |
| `?action=get-skill&skill=<slug>` | `{slug, name, desc, files:[{path, content}, ...]}` |
| `?action=list-content` | `[{id, title, url}, ...]` — alla sid-ID:n i `/content`, för `/files` |
| `?action=search-content&q=...` | `[{id, title, excerpt, url}, ...]` — `Search::query()`/`queryByTag()`/`queryByNamespace()` (prefix `tag:`/`ns:`), för `/search`, `/sok`, `/tag <tagg>`, `/namespace <namn>` |
| `?action=get-content&id=...` | `{id, path, content}` — en enskild sidas råinnehåll, för "+ Lägg till" och `?doc=`-deep-linken |
| `?action=list-tags` | `[{tag, count}, ...]` — alla taggar i `/content`, för `/tag` |
| `?action=list-namespaces` | `[{ns, label, count}, ...]` — alla namespaces (mappar) i `/content`, rot som `_root`, för `/namespace`/`/folder` |

**Skill-mappar:** `skills/<slug>/SKILL.md` (krävs) + valfria övriga textfiler
i samma mapp (`.md`, `.markdown`, `.txt`, `.json`, `.yaml`, `.yml`) — dessa
buntas automatiskt in tillsammans med `SKILL.md` när skillen väljs, precis
som vid manuell mapp-uppladdning i chatten. `FrontMatter::parse()`
(samma klass som för wikisidor) läser `name`, `description`, `icon` och
`include` ur SKILL.md:s YAML-frontmatter.

**`include`** (valfri lista i frontmatter) buntar automatiskt in sidor från
`/content` som extra kontextfiler:

```yaml
include: [start, projekt:api, playground:*]
```

- ett exakt sid-ID (`start`, `projekt:api`) läses via `PageLoader::load()`
- `namespace:*` expanderas mot `PageLoader::listAll()` till alla sidor i
  det namespacet
- den bifogade filens `path` byggs med `PageId::toFilePath('content')`,
  dvs. samma relativa sökväg som filen faktiskt har på disk

Se `skills/wiki-assistent/SKILL.md` för ett komplett exempel.

**Routing:** `.htaccess` blockerar direktåtkomst till `/skills` (läses bara
server-side av `chat/index.php`) och undantar `/chat` OCH `/admin` helt
från wiki-routern (`RewriteRule ^(chat|admin)(/|$) - [L]`) så Apache kör
respektive `index.php` direkt. Roten `index.php` har samma undantag
inbyggt (`/chat*` och `/admin*` → `require .../index.php; return;`)
eftersom PHP:s inbyggda utvecklingsserver (`php -S ... index.php`) inte
läser `.htaccess` alls — utan den delegeringen skulle `/chat` och `/admin`
bara ge "sidan finns inte" (routas som sid-ID:n) när wikin körs lokalt.

---

## Inloggning (/?do=login) och adminpanel (/admin)

`core/Auth.php` är filbaserad — inga externa beroenden. Ett konto i
`data/users/users.php` är `'användarnamn' => lösenord`, där lösenordet
antingen är en bcrypt-hash (från `password_hash()`, känns igen på
`$2a$`/`$2b$`/`$2y$`-prefixet, se `Auth::isHashed()`) eller klartext.
Klartext stöds medvetet som en snabb startpunkt utan CLI/adminpanel (t.ex.
det allra första kontot på en helt ny installation) — `Auth::verify()`
väljer `password_verify()` eller en tidssäker `hash_equals()`-jämförelse
beroende på vad som är lagrat.

`Auth::canEdit()` är binärt: `!$enabled || currentUser() !== null`. Den
tillämpas i toppen av `Wiki::handleEdit/Save/Delete/MediaUpload` — vid
avslag anropas `Wiki::requireLogin($returnTo)`, som redirectar till
`/?do=login&redirect_to=<returTo>`. `handleLogin()` (GET visar formulär,
POST verifierar) och `handleLogout()` (POST + CSRF) hanteras av
`Wiki::run()`s dispatch; `Router::resolve()` känner igen `do=login`/
`do=logout` globalt, oavsett sid-sökväg (som `do=search`).

**`admin/index.php`** är en egen front controller (se Routing ovan) som
återanvänder wikins vanliga `layout.php` istället för en egen HTML-skal —
den är bara en skrollande innehållssida, till skillnad från `/chat`s
fullhöjds-app-skal. Gate:as på `Auth::currentUser() !== null` (INTE
`canEdit()`/`auth_enabled`), så en administratör kan slå på `auth_enabled`
härifrån första gången utan att redan ha den påslagen.

**Flikar** (fyra `<div data-panel>`, klient-JS togglar `[hidden]` — se
`<script>` längst ner i filen). Servern renderar rätt flik synlig direkt
(progressive enhancement, fungerar utan JS också): varje formulär har ett
dolt `active_tab`-fält som ekas tillbaka i `$_POST` och styr vilken flik
som är synlig efter ett POST-svar (`$activeTab` i toppen av filen).
Funktioner (alla POST + CSRF, ett `action`-fält per formulär):

| Flik | `action` | Gör |
|---|---|---|
| Mitt lösenord | `change_password` | `Auth::changeOwnPassword()` — verifierar nuvarande lösenord, sparar det nya som bcrypt-hash |
| Användare | `create_user` | `Auth::setPassword()` — skapar eller skriver över, sparar alltid som hash |
| Användare | `delete_user` | `Auth::deleteUser()` — vägrar ta bort det egna inloggade kontot |
| Texter | `save_strings` | Ett textfält per nyckel i `Helpers::defaultStrings()` (grupperade via `stringGroups()`); `saveStringOverrides()` skriver om `config/strings.php` HELT (till skillnad från `patchConfigValue()`), men bara med de nycklar vars värde skiljer sig från standard — tomt fält = "använd standard" |
| Texter | `reset_strings` | `saveStringOverrides($path, [])` — töm hela override-filen |
| Webbplats | `save_settings` | `patchConfigValue()` för `'site_name'`/`'auth_enabled'` i `config/config.php` — bevarar filens kommentarer/formatering (skriver INTE om hela filen) |
| Webbplats | `upload_logo` / `reset_logo` | Tre `logo_type`-värden: `header`, `header-dark`, `footer` (`logoTypeConfig()`). Validerar `.svg`/`.png` ≤ 2 MB, sparar som `templates/<tema>/assets/img/custom-<header\|header-dark\|footer>-logo.<ext>` (`removeExistingLogo()` städar bort en ev. tidigare uppladdning av andra filändelsen först), `patchConfigValue()` pekar om `'header_logo'`/`'header_logo_dark'`/`'footer_logo'` i `config.php`. `reset_logo` tar bort filen och pekar tillbaka på standardvärdet (`'img/logo.svg'` för header/footer, tom sträng — "ingen egen, visa header_logo" — för header-dark) |

`bin/cli.php`s `create-user`/`delete-user`/`list-users` delegerar till
samma `Auth`-metoder (`setPassword()`/`deleteUser()`/`listUsernames()`/
`hasPlaintextPassword()`) — enda källan till `users.php`-formatet, oavsett
om ändringen kommer från CLI:t eller `/admin/`.

**Logotyper (ljust/mörkt läge):** headern byter bakgrund med temat (vit /
nästan svart, se `--bg-color`) och har därför TVÅ logotyper —
`header.php` renderar båda `<img>` (`.gbg-logo-light`/`.gbg-logo-dark`,
källor `$headerLogo`/`$headerLogoDark` från `Wiki::baseData()` →
`config.php`:s `header_logo`/`header_logo_dark`) och `assets/js/theme.js`
togglar `hidden` på rätt en live vid temaväxling (samma teknik som
sol/måne-ikonen; kräver en explicit `.gbg-logo[hidden]{display:none}`-
regel eftersom `.gbg-logo` annars har egen `display:block` som skulle
vinna över webbläsarens `[hidden]`-standard). Sidfoten (`footer.php`,
`$footerLogo`) är alltid mörk oavsett tema och har bara EN egen logga,
ingen växling. Samma nycklar skickas med av `chat/index.php` och
`admin/index.php` så logotyperna är konsekventa överallt. Lämnas
`header_logo_dark` tom (standard) visas `header_logo` i båda lägena.

---

## CLI

```bash
php bin/cli.php clear-cache          # Tömmer data/cache/ (alla underkataloger)
php bin/cli.php list-pages           # Listar alla sid-ID:n i content/
php bin/cli.php create-user <namn>   # Skapar/uppdaterar en inloggningsanvändare
php bin/cli.php delete-user <namn>   # Tar bort en inloggningsanvändare
php bin/cli.php list-users           # Listar alla inloggningsanvändare
php bin/cli.php help
```

---

## Ej implementerat / planerat till 0.3

| Funktion | Status |
|----------|--------|
| Auth kopplad till edit-flödet | **Klart** — `auth_enabled: true` kräver inloggning för redigering/spara/radera/media-uppladdning; läsning alltid öppet. Binär modell (inloggad = full redigeringsrätt). |
| Roll-/gruppbaserad ACL per namespace | `config/acl.php` (grupper) och `config/namespaces.php`:s `acl`-nyckel (public/login/private) läses in; tillämpas ej ännu — `namespaces.php`:s `menu`-nyckel fungerar dock redan |
| Sökindex | Filgenomsökning räcker för mindre wikis |
| Historik-visning i UI | `History::revisions()` finns; ingen vy |
| Responsiv mobil-layout | Grundläggande flex; ej optimerad |

---

## Licens

MIT — fri att använda, ändra och distribuera.
