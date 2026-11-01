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
4. Välj ZIP-filen och klicka **Importera .zip**.
5. Kontrollera resultatet: antal importerade och överhoppade filer.
6. Öppna startsidan och några sidor i olika namespaces och kontrollera menyerna.

Filerna hamnar under samma relativa sökväg i `content/`. Innehållet ändras inte:
namn, taggar, tidsstämplar och övriga metadata följer med exakt som i exporten.
HTML-cachen rensas efter importförsöket.

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
| Egna mallar/plugins/skills | Anpassningar som inte ingår i standardinstallationen |

Flytta inte serverhemligheter slentrianmässigt till en ny miljö. Kontrollera
OIDC issuer/callback, AD-adresser och CA-sökvägar, MCP-origins och filrättigheter.
Konfigurera namespace-ACL innan känsligt innehåll görs tillgängligt på den nya
webbplatsen. [Auth-guiden](readme-auth.md) och [MCP-guiden](readme-mcp.md) beskriver
inställningarna. Enbart ZIP-import återskapar inte den gamla installationens åtkomstregler.

## Gränser och fel

- Max 64 MB uppladdad ZIP, 64 MB uppackat och 16 MB per fil.
- Max 10 000 arkivposter, inklusive mappar.
- PHP:s `upload_max_filesize` och `post_max_size` kan sätta lägre gränser.
- ZIP-filen måste ha `content/` överst. Andra rotmappar accepteras inte.
- Traversering (`..`), symboliska länkar, specialfiler och dubbla sökvägar avvisas.
- Hela arkivet läses och valideras före skrivning. Vid diskfel under skrivning
  kan importen vara delvis genomförd; åtgärda felet och kör igen. Befintliga filer
  bevaras. Tomma mappar utan filer återskapas inte.

Test: `php tests/content-import.php` (kräver ext-zip) kontrollerar sökvägar,
exakt filinnehåll, befintliga filer och avvisning av otillåtna arkiv.
