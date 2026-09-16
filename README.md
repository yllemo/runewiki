# RuneWiki 0.2

Databasfri wiki-motor i PHP 8.1+. Innehåll lagras som Markdown-filer på disk,
i stil med DokuWiki — komplett med Monaco-editor (VS Code), YAML-frontmatter,
wikilänkar, tagg-sökning, interwiki, mediagalleri, plugin-system, valfri
inloggning med en egen adminpanel, en klientdriven AI-chatt och en
responsiv mobilvy. Fri och öppen källkod (MIT).

## Krav

- PHP 8.1 eller senare med **ext-mbstring**
- Ingen databas, inga Composer-beroenden
- Apache med `mod_rewrite` (produktion) eller PHPs inbyggda server (lokalt)
- Internetanslutning vid redigering (Monaco Editor laddas från CDN)

## Snabbstart

```bash
cd runewiki
php -S 127.0.0.1:8080 index.php
```

Öppna `http://127.0.0.1:8080/` — index.php hanterar routing, snygga URL:er
fungerar direkt med den inbyggda servern.

`/content` levereras med en minimal startsida (`start.md`) och en
syntax-guide (`hjalp:syntax`) — döp om, redigera eller radera dem som du
vill, det är bara ett förslag på en första struktur.

## Driftsättning (Apache)

1. Peka Apache mot **projektets rot** — `index.php` ligger direkt i webroten.
2. Kontrollera att `mod_rewrite` är aktiverat (`.htaccess` finns redan).
3. Ge webbservern skrivrättigheter till `content/`, `images/` och `data/`.
   Egna logotyper via `/admin/` sparas under `images/logos/`.
4. Kopiera `config/config.example.php` till `config/config.php` och justera.

## Funktioner

### Redigering
- **Monaco Editor** (VS Code) med IntelliSense för tre kontexter:
  - YAML-frontmatter (nycklar och värden inuti `---`-blocket)
  - Markdown-snippets (rubriker, formatering, kodblock, listor, tabeller, wiki-syntax)
  - Wiki-interlinks `[[...]]` med förslag på befintliga sid-ID:n
- Förslagen dyker bara upp när man ber om dem — <kbd>Ctrl+Space</kbd>
  (alla tre) eller automatiskt när man skriver `[[` (wiki-interlinks) —
  inte medan man skriver löpande text
- Hela råfilen (inkl. frontmatter) visas och redigeras i ett enda fönster
- Spara-knapp både ovanför och nedanför editorn
- "AI Chat"-knapp direkt ovanför sidans innehåll (bredvid Redigera/Sök
  liknande) öppnar `/chat` med just den sidan redan laddad i kontexten
- "Ladda ner .md"-knapp bredvid Redigera — laddar ner sidans råa
  Markdown-fil (`?do=download`), ingen inloggning krävs (samma som att läsa sidan)
- Sparar man en helt tom fil (allt innehåll raderat, även frontmatter)
  tas sidan bort istället för att spara en tom `.md`-fil

### Innehåll och syntax
- Färgade Markdown-boxar: `::: info`, `::: warning`, `::: tip`, `::: note`
  m.fl., avslutade med `:::`. Egen rubrik och Markdown-innehåll stöds,
  liksom GitHub-alerts (`> [!NOTE]`). Mallar finns via Ctrl+Space → `box`;
  syntaxguiden visar alla typer och exempel. Färgerna följer ljust/mörkt läge.
- **YAML-frontmatter** — titel, datum, taggar, status m.m. renderas som snygg metadata-rad
- **Titel från rubrik** — första `# Rubrik` blir sidans titel; sid-ID som fallback
- **Taggar** — klickbara, söker fram alla sidor med samma tagg
- **Wikilänkar:** `[[namespace:sida]]` och `[[namespace/sida]]`
- **Röda länkar** — sidor som inte finns visas med röd streckad länk och leder till `?do=edit`
- **Markdown-interlinks:** `[text](/namespace/sida)` kollar existens
- **Interwiki:** `[[wp>Göteborg]]`, `[[github>…]]` m.fl. (config/interwiki.php)
- **Media-embeds:** `{{namespace:bild.png}}` — inbäddning eller nedladdningslänk
- **Mermaid-diagram** — ett kodblock märkt ```` ```mermaid ```` renderas som
  ett riktigt diagram (mermaid.js laddas bara från CDN på sidor som
  faktiskt har ett); ritas om automatiskt i rätt färgschema om man togglar
  ljust/mörkt läge, ingen omladdning
- Standard Markdown: rubriker, fet/kursiv, kod/kodblock, listor, citat, tabeller

### Sökning
- Fulltextsökning i titel och brödtext
- `tag:nyckelord` söker exakt tagg-matchning i frontmatter
- "Skapa sida"-förslag när inga träffar hittas

### Media (/images)
- Varje fil visar en utfällbar lista över sidor som refererar till den,
  inklusive sidopaneler och toppmeny. Användning som logotyp/favicon visas
  separat. Borttagningsbekräftelsen visar antalet referenser.
- Under varje wikisida visas bakåtlänkar: andra sidor som länkar dit.
  Wikilänkar, Markdown-länkar och bildreferenser räknas utifrån parserns
  HTML; kodexempel räknas inte. Varje källsida listas bara en gång.
  Referenserna räknas om per sidvisning, utan databas eller separat indexfil.
- `/images` (utan namespace i URL:en) visar en **global översikt över alla
  namespaces** med media på en gång, grupperat med en klickbar rubrik per
  namespace; `/images/<namespace>` visar (och laddar upp till) bara det
  namespacet
- Bilder visas som miniatyrer i ett rutnät så man ser vilka bilder som
  redan finns; övriga filtyper (PDF m.fl.) som ikonkort — filnamn,
  storlek och embed-koden (`{{namespace:fil.png}}`) visas på varje kort
- Bläddring/visning alltid öppet; **uppladdning kräver inloggning** när
  `auth_enabled: true` (formuläret döljs annars till förmån för en
  inloggningslänk — CSRF-skyddat när det väl visas)

### AI-chatt (/chat)
- Klientdriven AI-chatt (`chat/index.php`) — anropar OpenAI-kompatibel/LM
  Studio/Ollama direkt från webbläsaren, ingen nyckel passerar servern
- Delar aktivt temas header, footer, sitenamn och toppmeny med resten av
  wikin (samma `TemplateEngine`, samma `assets/css/style.css`) — byt tema
  i config och chatten följer med automatiskt
- Färdiga "skills" läses från `/skills/<skill>/SKILL.md`; en skill kan
  automatiskt bunta in sidor från `/content` via `include` i frontmatter
- Snabbkommandon i chattrutan: `/files` listar alla wikisidor, `/search`
  (eller `/sok`) fritextsöker, `/tag` listar taggar, `/namespace` (eller
  `/folder`) listar namespaces och kan lägga till alla sidor i ett
  namespace på en gång, `/context` (eller `/kontext`) listar och tar bort
  Markdown-filer som redan ligger i kontexten — valda sidor läggs i
  kontexten precis som en skill-fil
- Egna filer/`.zip`/mappar kan även laddas upp manuellt i chatten
- Markdown- och Mermaid-rendering av svar (diagrammen ritas om live om man
  togglar ljust/mörkt läge — precis som på vanliga wikisidor), filförhandsvisning,
  export av chatten till Markdown
- Sidfoten (sidindex + footer) är dold som standard för mer plats åt
  chatten — ett klick visar den igen, och valet minns sig mellan besök

### Navigering och layout
- Kollapsbart sidindex i sidfoten — träd per namespace, paginerat med "Visa fler"
- Sidopanel per namespace via `_sidebar.md`
- Toppmenyns knappar styrs via `content/_topbar.md` (punktlista av länkar) — faller
  tillbaka till `config/menu.php` om filen saknas
- Ljust/mörkt läge (localStorage) — headern har egna **separata logotyper
  för ljust/mörkt läge** (`header_logo`/`header_logo_dark` i config.php)
  som växlar live vid temaväxling, ingen omladdning; sidfoten är alltid
  mörk och har bara en egen logga (`footer_logo`)
- **Responsiv mobilvy** — huvudnavigeringen (`_topbar.md`/`config/menu.php`)
  fälls bakom en hamburgermeny under 768px istället för att klämmas ihop;
  verktygsraden radbryter snyggt, långa användarnamn/ord trunkeras/bryts
  istället för att tvinga fram horisontell scroll
- SVG-favicon

### Teknik och drift
- **Cache** — självläkande HTML-cache via `filemtime`; atomära skrivningar med `rename()`; gamla versioner rensas automatiskt
- **Plugin-system** med hooks: `after_parse`, `page_view`, `before_save`, `after_save`
- **Utbytbara teman** — kopiera `templates/default/`, byt `theme` i config
- **Versionshistorik** — ögonblicksbilder vid sparning (aktiveras via config)
- **CLI** för cache-tömning och sid-listning

### Säkerhet
- **Valfri inloggning** (`auth_enabled` i config) — läsning öppet som
  standard, redigering/spara/radera/media-uppladdning kan kräva inloggning
  (`core/Auth.php`, användare via `php bin/cli.php create-user`)
- **Finmaskig ACL per namespace och grupp** (`config/acl.php` + `core/Acl.php`,
  tillämpas i `Wiki::canReadNamespace()`/`canEditNamespace()`) — se
  "Inloggning och redigeringsrättigheter" nedan
- **Inloggningen håller sig** (`session_lifetime_days` i config, standard 30
  dagar) — glidande fönster, cookien förnyas vid varje besök som inloggad
- XSS-skydd: inline Markdown-parser isolerar mönster med tokens; råa HTML-taggar escapes
- CSRF-tokens på alla formulär (spara, radera, mediauppladdning, inloggning/utloggning)
- Path traversal förhindras via PageId-sanitering (`..` tas bort ur varje del)
- Session-cookie: `HttpOnly`, `SameSite=Lax`
- Open redirect-kontroll på `redirect_to`-parameter
- YAML-nyrads-injektion förhindras i `FrontMatter::build()`
- Innehållsgräns: max 512 KB per sparat dokument
- `.htaccess` blockerar direktåtkomst till `config/`, `content/`, `core/`, `data/`, `plugins/`
- PHP-körning inaktiverad i `media/`

## Konfiguration

Under **Admin → Webbplats → Länkar** väljer du om externa webblänkar ska
öppnas i ny flik (standard: på). Interna länkar öppnas i samma flik.
Externa länkar identifieras genom att deras origin skiljer sig från den
aktuella webbplatsens. Du kan även aktivera separata färger för interna
och externa innehållslänkar; annars används temats färger. Länkar till
sidor som saknas behåller sin varningsfärg. Inställningarna används även
för nya länkar som tillkommer i chatten.

| Fil | Syfte |
|-----|-------|
| `config/config.php` | Site-namn, tema, logotyper (header ljust/mörkt + footer), cache, auth, `session_lifetime_days`, history m.m. |
| `config/plugins.php` | Aktivera plugins och sätt deras options |
| `config/menu.php` | Huvudnavigering (åsidosätts av `content/_topbar.md` om den finns) |
| `config/interwiki.php` | Interwiki-genvägar |
| `config/media.php` | Tillåtna filtyper och maxstorlek |
| `config/namespaces.php` | Egen meny per namespace (aktivt); `'acl'` (public/login/private) styr läsrätt, se "Inloggning och redigeringsrättigheter" |
| `config/strings.php` | Skriv över UI-texter (tagline, sidfot, knappar, inloggning m.m.) |
| `config/acl.php` | Grupper för läs-/redigeringsrätt per namespace (kombineras med `namespaces.php`:s `'acl'`) — tilldelas konton via adminpanelen eller CLI:t |

## YAML-frontmatter

Placera ett `---`-block längst upp i en sida:

```yaml
---
title: Min sida
date: 2026-09-07
author: Erik Andersson
tags: [wiki, exempel]
status: Publicerad
draft: false
description: En kortare beskrivning
---

# Min sida

Brödtext...
```

Frontmatter renderas som en snygg metadata-rad direkt under rubriken.
Taggar är klickbara och söker fram alla sidor med samma tagg.

## Toppmenyn (_topbar.md)

Lägg en `content/_topbar.md` för att styra knapparna i toppmenyn direkt via
innehåll istället för `config/menu.php`. Finns filen används den — ALLTID,
site-wide, oavsett tema eller namespace.

Menyprioritet (`Wiki::menuFor()`):
1. `content/_topbar.md` — om den finns, vinner den alltid.
2. `config/namespaces.php` — ett namespace kan ha en egen `'menu'`-nyckel
   (samma format som `config/menu.php`), används bara om `_topbar.md` saknas.
3. `config/menu.php` — global fallback.

```markdown
- [[start|Start]]
- [[projekt:api|API]]
- [Om wikin](https://exempel.se)
```

Varje rad i punktlistan blir en knapp, i angiven ordning:

| Syntax | Resultat |
|--------|----------|
| `[[sida]]` | Wiki-länk; knappens text = sidans titel |
| `[[sida\|Egen text]]` | Wiki-länk med egen knapptext |
| `[Egen text](https://...)` | Extern länk (eller absolut sökväg, t.ex. `/images`) |

Rader som inte matchar något av detta ignoreras, och HTML-kommentarer
(`<!-- ... -->`, även flerradiga) tas bort innan tolkning — filen kan alltså
dokumentera sin egen syntax utan att exempelrader blir riktiga knappar.

## AI-chatt och skills (/chat, /skills)

`chat/index.php` är en klientdriven AI-chatt som återanvänder **aktivt
temas** riktiga `header.php`/`footer.php` (via samma `TemplateEngine` som
resten av wikin) — samma sitenamn, logotyp, toppmeny, ljust/mörkt läge och
`assets/css/style.css` som alla andra sidor. Byt tema i `config/config.php`
och chatten följer automatiskt med; ingen egen inbäddad design kvar.

Själva chattlogiken (skicka/ta emot, filuppladdning, inställningar) är
fortfarande helt klientdriven — statisk JS som pratar direkt med en
OpenAI-kompatibel tjänst, LM Studio eller Ollama via `fetch()`. Servern
lagrar och ser **aldrig** bas-URL, API-nyckel, modell eller systemprompt —
allt sparas enbart i webbläsarens **IndexedDB** och skickas därifrån direkt
till din valda LLM-endpoint (streaming). `chat/index.php` exponerar bara
skrivskyddade JSON-endpoints som chattens JS använder:

- `?action=list-skills` — listar alla skills för sidopanelen
- `?action=get-skill&skill=<slug>` — hämtar en skills filer
- `?action=list-content` — listar alla sid-ID:n i `/content` (för `/files`)
- `?action=search-content&q=...` — fritext- eller `tag:`-sökning (för `/search`)
- `?action=get-content&id=...` — hämtar en enskild sidas råinnehåll
- `?action=list-tags` — listar alla taggar med antal sidor (för `/tag`)

`list-content`, `search-content`, `get-content`, `list-tags` och
`list-namespaces` respekterar samma läsrätt per namespace som resten av
wikin (se "Finmaskig ACL per namespace och grupp") — en sida i ett
namespace man saknar läsrätt till visas eller hittas inte via `/chat`
heller, oavsett om man frågar direkt eller via ett `/search`/`/files`-
kommando. Undantaget är skills `include`-fält i `SKILL.md`
(`?action=get-skill`): en skill som en admin skapat kan medvetet bunta in
valfria sidor, ACL-filtreras inte.

### Skapa en skill

Lägg en mapp under `/skills/<mitt-skill>/` med minst en `SKILL.md`:

```markdown
---
name: Min skill
description: Kort beskrivning som visas i skill-listan.
icon: 🧩
---

# Min skill

Instruktioner till assistenten...
```

Alla textfiler i samma mapp (`.md`, `.txt`, `.json`, `.yaml`) buntas
automatiskt in som extra kontextfiler tillsammans med `SKILL.md`, precis
som när en hel skill-mapp laddas upp manuellt i chatten.

### Inkludera wiki-innehåll (`include`)

En skill kan automatiskt bunta in sidor från `/content` via `include` i
frontmatter — se `skills/wiki-assistent/SKILL.md` för ett fullständigt
exempel:

```yaml
include: [start, projekt:api, playground:*]
```

- `start` / `projekt:api` — ett enskilt sid-ID, buntas in som det är
- `playground:*` — jokertecken; tar med **alla** sidor i det namespacet

Detta gör att en skill kan svara på frågor om valfri del av wikins eget
innehåll utan att användaren manuellt behöver ladda upp filer.

### Snabbkommandon i chattrutan

Utöver skills kan man manuellt bläddra och lägga till wiki-sidor direkt i
chatten, utan att skriva ett `include`. Kommandona körs lokalt mot servern
(inte mot LLM:en) och hamnar inte i konversationshistoriken:

| Kommando | Resultat |
|---|---|
| `/files` | Listar alla sidor i `/content`, med en "+ Lägg till"-knapp per sida |
| `/search <term>` (eller `/sok <term>`) | Fritextsöker — samma resultat som wikins `?do=search` |
| `/tag` | Listar alla taggar med antal sidor |
| `/tag <tagg>` | Visar alla sidor med taggen, med en "+ Lägg till alla"-knapp |
| `/namespace` (eller `/folder`) | Listar alla namespaces (mappar under `/content`) med antal sidor |
| `/namespace <namn>` | Visar alla sidor i namespacet, med en "+ Lägg till alla"-knapp |
| `/context` (eller `/kontext`) | Listar Markdown-filer som redan ligger i kontexten, med en "Ta bort"-knapp per fil |

Tillagda sidor dyker upp i "Filer i kontext" i sidopanelen precis som en
skills filer, och skickas med i nästa fråga till LLM:en.

### Routing

`/chat` hanteras av sin egen front controller (`chat/index.php`) och går
INTE via `Wiki`/`Router` i roten. Under Apache styr `.htaccess` dit direkt;
`index.php` i roten har även en explicit delegering för PHP:s inbyggda
utvecklingsserver (`php -S ...`), som annars skulle skicka allt genom
wiki-routern. `/skills/` är blockerad för direkt HTTP-åtkomst i `.htaccess`
— filerna läses bara på serversidan av `chat/index.php`.

## Plugin-exempel

```php
// plugins/mitt-plugin/plugin.php
class MittPlugin implements PluginInterface
{
    public function register(PluginManager $manager, array $options = []): void
    {
        $manager->on('page_view', function (array $ctx) use ($options): array {
            $ctx['html'] .= '<p class="notice">Extra rad</p>';
            return $ctx;
        });
    }
}
```

```php
// config/plugins.php
'mitt-plugin' => ['enabled' => true, 'options' => ['nyckel' => 'värde']],
```

Tillgängliga hooks: `after_parse`, `page_view`, `before_save`, `after_save`.

## Inloggning och redigeringsrättigheter

`auth_enabled` styr detta i `config/config.php` och är **aktiverat** i den
här installationen, med ett standardkonto klart att logga in med:

> **Användarnamn:** `admin` · **Lösenord:** `admin123`
>
> ⚠️ Kontot skapades utan CLI-åtkomst (se nedan) och ligger därför i
> **klartext** i `data/users/users.php`. Logga in och byt lösenordet på
> `/admin/` så snart du kan — det sparas då som en riktig bcrypt-hash.

Sätt `auth_enabled: false` istället för en helt öppen wiki (alla kan läsa
och redigera, ingen inloggning behövs, ACL nedan gör då ingenting). Med
`true` gäller som grundregel: läsning är öppet för alla, men redigering/
spara/radera/media-uppladdning kräver inloggning
(`core/Auth.php::canEdit()`, tillämpas i `core/Wiki.php`). Ett försök att
redigera utan att vara inloggad skickas till `/?do=login` och hamnar
tillbaka där man kom ifrån efter lyckad inloggning. Man behöver inte logga
in på nytt varje gång — se "Hur länge en inloggning håller sig" nedan.

### Finmaskig ACL per namespace och grupp

Utöver grundregeln ovan kan du begränsa LÄSNING per namespace och styra
REDIGERING per grupp — två delar som samverkar:

1. **`config/namespaces.php`:s `'acl'`-nyckel** styr läsrätten till ett
   namespace: `public` (standard, alla läser), `login` (kräver inloggning,
   vilket konto som helst) eller `private` (kräver inloggning OCH en grupp
   med uttrycklig läs- eller redigeringsrätt till just det namespacet).
2. **`config/acl.php`:s grupper** styr vad ett konto FÅR göra — varje grupp
   är en lista rättighetssträngar, t.ex. `'*'` (allt), `'projekt:edit'`
   (läs+redigera bara namespacet `projekt`) eller `'*:read'` (bara läsa,
   överallt). Inbyggda grupper: `admin` (allt), `editor` (läs+redigera
   överallt — standard för konton utan egna grupper, se nedan) och
   `reader` (bara läsa). Lägg till egna grupper i filen för finare
   uppdelning.

Vilka grupper ETT KONTO tillhör sätts under **Admin → Användare** (bocka i
grupper per konto) eller `php bin/cli.php set-groups <namn> <grupp1,grupp2>`
— inte i `config/acl.php`, som bara definierar vad gruppnamnen FÅR göra.
Ett konto som aldrig fått egna grupper räknas som `editor` (kan redigera
överallt), så en uppgradering från en äldre installation inte plötsligt
låser ute befintliga redaktörer. Redigeringsrätt kräver alltid `edit`/`*`
för namespacet, oavsett `'acl'`-läge. Namespaces utan egen `'acl'`-rad
ärver rotens (`namespaces.php`:s nyckel `''`).

Sökträffar, bakåtlänkar, sidindexet i sidfoten och mediaöversikten
(`/images`) filtreras alla efter samma läsrätt, så ett namespace med
`acl: private` läcker varken titlar eller filnamn till den som saknar
behörighet.

### Hur länge en inloggning håller sig

`session_lifetime_days` i `config/config.php` (standard 30, ändras även
under **Admin → Webbplats**) styr hur länge man förblir inloggad utan att
logga in på nytt. Glidande fönster: cookien förnyas vid varje besök som
inloggad (`Auth::refreshSessionCookie()`), så aktiva konton loggas aldrig
ut mitt i användningen — bara efter `session_lifetime_days` dagars total
inaktivitet, eller om man loggar ut/rensar cookies för hand.

### Adminpanel (/admin/) — ingen CLI-åtkomst krävs

`admin/index.php` är en egen front controller (precis som `/chat/`), bara
synlig när man är inloggad. Fyra flikar (klientväxlade, ingen sidladdning):

- **Mitt lösenord** — byt eget lösenord, sparas alltid som en riktig
  bcrypt-hash. Det här är den CLI-fria vägen ut ur klartextläget, t.ex. på
  OpenShift eller annan hosting utan shell/PHP CLI-åtkomst: en vanlig
  POST-request kör `password_hash()` i webbserverns egen PHP-process.
- **Användare** — skapa/ta bort inloggningsanvändare (kan inte ta bort
  sitt eget inloggade konto), samt bocka i vilka **grupper**
  (`config/acl.php`) varje konto tillhör — se "Finmaskig ACL" ovan.
- **Texter** — alla UI-texter (`Helpers::defaultStrings()`, grupperade i
  fieldsets: header/sidfot, navigering, sidverktyg, AI Chat, sidindex,
  inloggning, mitt konto) — skriver `config/strings.php`. Ett tomt fält
  återställer den texten till standardvärdet; en egen knapp återställer allt.
- **Webbplats** — sitenamn, `auth_enabled`, `session_lifetime_days`,
  export av `/content` som .zip, samt **tre logotyp-uppladdningar**
  (`.svg`/`.png`, max 2 MB var): header (ljust läge), header (mörkt läge)
  och footer — laddas upp till
  `images/logos/custom-<header|header-dark|footer>-logo.<ext>`
  och pekas ut i `config.php`:s `header_logo`/`header_logo_dark`/`footer_logo`
  (`Återställ`-knapp går tillbaka till standardloggan, eller till "samma
  som ljust läge" för header-varianten i mörkt läge). Sitenamn/`auth_enabled`
  skrivs via riktad textersättning i `config/config.php` (bevarar filens
  kommentarer, skriver INTE om hela filen).

Panelen kräver bara att man är inloggad (`Auth::currentUser()`), oavsett
`auth_enabled` — annars vore den låst ute om man behövde slå på
inställningen härifrån första gången. Panelen är inte ACL-begränsad: vem
som helst som är inloggad kommer åt hela `/admin/`, oavsett gruppmedlemskap.

Har du shell-åtkomst går samma sak (och underhåll i övrigt) att göra via
CLI:t istället — se `## CLI` nedan.

Kontona sparas i `data/users/users.php` (blockerad för direktåtkomst av
`.htaccess`) som `'användarnamn' => ['password' => ..., 'groups' => [...]]`.
Lösenordet är antingen en bcrypt-hash (`$2y$...`, från `password_hash()`)
eller klartext som en snabb startpunkt utan CLI/adminpanel (t.ex. det
första kontot på en helt ny installation: skapa filen för hand med ett
klartextlösenord, logga in, byt det direkt på `/admin/`). Äldre installationer
kan fortfarande ha kontona som en ren `'användarnamn' => lösenord`-sträng
(utan `groups`) — läses in precis som förut och tolkas som gruppen `editor`
tills du sätter egna grupper. Rör aldrig hasharna för hand.

## CLI

```bash
php bin/cli.php clear-cache          # Töm data/cache/
php bin/cli.php list-pages           # Lista alla sid-ID:n
php bin/cli.php create-user <namn>   # Skapa/uppdatera en inloggningsanvändare (sparar hash)
php bin/cli.php delete-user <namn>   # Ta bort en inloggningsanvändare
php bin/cli.php list-users           # Lista användare (grupper + klartext-flagga)
php bin/cli.php set-groups <namn> <grupp1,grupp2>  # Sätt kontots grupper (config/acl.php), tomt tar bort alla
php bin/cli.php help
```

## Sid-ID och filsökväg

Sid-ID:n normaliseras till små ASCII-bokstäver, siffror, understreck och
bindestreck. Svenska tecken translittereras (`å/ä → a`, `ö → o`), mellanslag
och annan interpunktion blir understreck. Samma regler gäller namespaces.
Det är filnamnen som normaliseras, inte språket i sidans innehåll eller titel.

- `[[ärende]]` → `content/arende.md`
- `[[Mina Ärenden:Första mötet]]` → `content/mina_arenden/forsta_motet.md`
- `[[Övrigt:Årsrapport 2026]]` → `content/ovrigt/arsrapport_2026.md`

De särskilda styrfilerna `_sidebar.md` och `_topbar.md` behåller sina namn.
Namn utan några användbara ASCII-tecken får ett stabilt `sida-<hash>`-namn.
Olika stavningar som normaliseras lika (t.ex. `ärende` och `arende`) avser
samma sida. Befintliga filer med äldre namn behöver döpas om separat;
kontrollera namnkonflikter innan du flyttar dem. Ingen automatisk migrering sker.

Första kolondelen = mapp (namespace), resten = punktseparerat filnamn:

| Sid-ID | Fil |
|--------|-----|
| `start` | `content/start.md` |
| `projekt:api` | `content/projekt/api.md` |
| `namespace:page1:start` | `content/namespace/page1.start.md` |

URL speglar ID:t med kolon → snedstreck: `projekt:api` nås på `/projekt/api`.

## Byta namn och flytta

Välj **Byt namn / flytta** i sidans verktygsrad och ange ett nytt sid-ID,
t.ex. `projekt:nytt_namn`. Förhandsvisningen visar det normaliserade
filnamnet och berörda sidor innan flytten bekräftas. Befintliga målsidor
eller mål med historik skrivs inte över. Sidans titel och metadata bevaras.

Wikilänkar och relativa Markdown-länkar i innehållsfiler, `_sidebar` och
`_topbar` uppdateras. Kodexempel och bildadresser lämnas orörda. Historiken
flyttas separat under `data/history/`; berörda sidors tidigare innehåll
sparas även om automatisk historik är avstängd. Externa bokmärken, absoluta
webbadresser och menyer i PHP-konfiguration behöver uppdateras separat.

## Versionshistorik

Knappen **Versionshistorik** på en sida visar äldre versioner och låter
redigeringsbehöriga användare läsa deras Markdown och återställa dem.
Nuvarande innehåll säkerhetskopieras före återställning. Borttagna sidor
kan återskapas via versionshistoriken på sin gamla adress.

Historiska filer ligger separat i `data/history/<sid-ID:s SHA-256>/<UTC-tid>-<unik kod>.md`,
aldrig i `content/`, och exponeras inte som vanliga wikisidor eller sökträffar.
Apache blockerar direktåtkomst till `data/` via `.htaccess`. Ge PHP-processen
skrivrättigheter till `data/history/`, och använd beständig lagring för både
`content/` och `data/` i OpenShift om innehållet ska överleva podbyten.

Aktivera **Spara versionshistorik** under **Admin → Webbplats** på befintliga
installationer där `history_enabled` tidigare var `false`. Nya installationer
har historik på som standard. Versioner skapas från nästa ändring; tidigare
innehåll kan inte återskapas retroaktivt. Det äldre experimentella
historikformatet importeras inte automatiskt.

## Kända begränsningar

- **Adminpanelen är inte ACL-begränsad** — grupper (`config/acl.php`) styr
  bara läs-/redigeringsrätt till wikisidor och media per namespace; vem som
  helst som är inloggad kommer åt hela `/admin/` (användare, texter,
  webbplatsinställningar, export). En helt ny installation
  (`config.example.php`) har `auth_enabled` avstängd som standard.
- **Mediaöversiktens uppladdningsknapp** i den samlade `/images`-vyn laddar
  alltid upp till ROTEN, oavsett vilka enskilda namespaces som visas —
  borttagningsknappen per fil respekterar däremot rätt namespace.
- **Sökning utan index** — filgenomsökning vid varje sökning; tillräckligt för
  mindre wikis, skalbart med indexbaserad implementation utan API-ändringar.
- **Monaco Editor** kräver internet (laddas från CDN vid redigering).

## Licens

MIT — fri att använda, ändra och distribuera, kommersiellt eller privat.
