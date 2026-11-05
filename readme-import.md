# Exportera, importera och flytta innehåll

Funktionen finns under **Admin → Webbplats → Exportera och importera innehåll**.
Du måste vara inloggad. PHP-tillägget **zip** krävs på båda installationerna.

## Exportera

1. Logga in på den gamla installationen och öppna adminpanelen.
2. Välj **Webbplats → Ladda ner .zip**.
3. Spara filen. Namnet bygger på wikins namn och exportdatum.

ZIP-filen innehåller `content/` med filer och mappar som de ligger på servern:

```text
content/
  start.md
  _topbar.md
  _sidebar.md
  projekt/
    api.start.md
```

Exporten bevarar råinnehållet, inklusive YAML-frontmatter, menyfiler och
`<ifAuth>`-block. Den är en administrationsbackup, inte en filtrerad offentlig
nedladdning. Förvara den med samma åtkomstskydd som originalinnehållet.

## Importera på den nya installationen

1. Installera RuneWiki och skapa ett lokalt konto som kan öppna adminpanelen.
2. Kontrollera att PHP kan skriva till `content/` och `data/cache/`.
3. Öppna **Admin → Webbplats → Importera en wikiexport**.
4. Välj ZIP-filen och klicka **Importera .zip**. Behåll sidan öppen.
5. Kontrollera resultatet: antal importerade och överhoppade filer.
6. Öppna startsidan och några sidor i olika namespaces och kontrollera menyerna.

Filerna hamnar under samma relativa sökväg i `content/`. Innehållet ändras inte:
namn, taggar, tidsstämplar och övriga metadata följer med exakt som i exporten.
HTML-cachen rensas efter importförsöket.

Med JavaScript laddas ZIP-filen upp i delar om högst 256 KiB (automatiskt mindre
om PHP:s gränser kräver det). Därefter importeras normalt högst 25 filer per
anrop. Förloppsindikatorn visar uppladdning och import. **Pausa** stoppar efter
aktuellt anrop; **Fortsätt import** återupptar arbetet på samma sida. Vid ett
nätfel kan du också försöka fortsätta. Upprepade anrop med samma position
duplicerar inte delar eller redan genomförda omgångar.

Temporära ZIP-filer sparas i `data/imports/`, som PHP måste kunna skriva till.
De raderas när importen är klar. Övergivna filer äldre än ett dygn städas när
en ny import startar. Om du laddar om sidan eller sessionen går ut väljer du
filen på nytt; befintliga innehållsfiler hoppas över som vanligt.
Utan JavaScript används den äldre engångsuppladdningen, där hela filen måste
rymmas inom PHP:s uppladdningsgränser.

**Befintliga filer skrivs aldrig över.** En medföljande `start.md` på den nya
installationen hoppas därför över. Om den ska ersättas: säkerhetskopiera den och
ta bort eller byt namn på den innan importen. Detta gäller även `_topbar.md`
och `_sidebar.md`. Kör importen igen för att lägga till filer som fortfarande saknas.

## Vad följer inte med?

Exporten innehåller endast `content/`. För en full flytt behöver du separat
säkerhetskopiera och överföra följande via serverns filåtkomst:

| Sökväg | Innehåll |
|---|---|
| `images/` | Bilder, bilagor och egna logotyper |
| `config/` | Webbplatsinställningar, namespaces, ACL, auth, metadata och MCP-inställningar |
| `data/users/` | Lokala konton och eventuella personliga MCP-nycklar |
| `data/history/` | Versionshistorik |
| `data/comments/` | Artikelkommentarer och svarstrådar |
| Egna mallar/plugins/skills | Anpassningar som inte ingår i standardinstallationen |

Flytta inte serverhemligheter slentrianmässigt till en ny miljö. Kontrollera
OIDC issuer/callback, AD-adresser och CA-sökvägar, MCP-origins och filrättigheter.
Konfigurera namespace-ACL innan känsligt innehåll görs tillgängligt på den nya
webbplatsen. [Auth-guiden](readme-auth.md) och [MCP-guiden](readme-mcp.md) beskriver
inställningarna. Enbart ZIP-import återskapar inte den gamla installationens åtkomstregler.

## Gränser och fel

- Standard: max 256 MB ZIP, 512 MB uppackat och 32 MB per fil.
- Max 50 000 arkivposter, inklusive mappar.
- Ändra gränser och omgångsstorlek direkt i `config/import.php`:

  ```php
  return [
      'max_zip_mb' => 256,
      'max_unpacked_mb' => 512,
      'max_file_mb' => 32,
      'max_entries' => 50000,
      'batch_files' => 25,
  ];
  ```

- PHP:s `upload_max_filesize` och `post_max_size` gäller varje uppladdad del.
  Aktuella värden visas i admin. En liten ZIP kan innehålla mycket större
  uppackat innehåll; felmeddelandet anger den gräns som behöver kontrolleras.
- Webbserver/proxy kan ha egna gränser. HTTP 413 betyder normalt att en
  uppladdning nekats före PHP; kontrollera dess inställningar om det sker även
  med små delar. Filinnehåll packas upp direkt till disk i block om 64 KiB,
  även utan JavaScript. Hela filer laddas inte till minnet. Listan över
  arkivposter och övriga delar av applikationen behöver fortfarande minne.
  Storlek och CRC-kontrollsumma verifieras; ofullständiga filer tas bort.
- ZIP-filen måste ha `content/` överst. Andra rotmappar accepteras inte.
- Traversering (`..`), symboliska länkar, specialfiler och dubbla sökvägar avvisas.
- Arkivets sökvägar och storlekar valideras före skrivning. Filinnehållet läses
  under respektive importomgång. Vid korrupt fil eller diskfel under skrivning
  kan importen vara delvis genomförd; åtgärda felet och kör igen. Befintliga filer
  bevaras. Tomma mappar utan filer återskapas inte.

Test: `php tests/content-import.php` (kräver ext-zip) kontrollerar sökvägar,
exakt filinnehåll, befintliga filer och avvisning av otillåtna arkiv.
`php tests/import-memory.php` testar både engångsimport och import i omgångar
med 128 MB minnesgräns, 100 MB simulerat applikationsminne och 160 MB uppackat
innehåll. På OpenShift måste den uppdaterade `core/ContentImport.php` finnas i
den körande containern; bygg och rulla ut den nya versionen innan omtest.
Med ext-zip aktiverat testar `php tests/mcp-http.php` dessutom riktig delad
uppladdning, återförsök, sessions-/CSRF-kontroller och rensning av temporära filer.
