# RuneWiki MCP

RuneWiki har en skrivskyddad MCP-server på **`https://din-wiki.example/mcp/`**.
Även `/mcp` och `/mcp/index.php` fungerar med projektets Apache-konfiguration.
Servern ger en AI-klient verktyg för att lista, söka och läsa wikisidor med
användarens behörigheter. Den kräver PHP 8.1+ och `mbstring`, precis som wikin.
Ingen databas, Node-process eller separat tjänst behövs.

## Personlig nyckel

### Felsökningslogg

Under **Admin → Min MCP-nyckel → MCP-inställningar** kan du aktivera
**Logga MCP-anrop**. Inställningen `log_requests` sparas i `config/mcp.php`
och är av som standard. Varje anrop, även misslyckad autentisering, skrivs
som en JSON-rad i `/mcp/log.txt`: UTC-tid, HTTP-metod, MCP-metod, verktygsnamn,
storlek, svarskod, felkod, svarstid och om autentiseringsheaders kom fram.
Nycklar, cookies, argument och svarsinnehåll sparas inte.

Läs filen via serverns filåtkomst. Apache-reglerna blockerar direkt HTTP-åtkomst
till loggen; på Nginx/IIS måste `/mcp/log.txt` också blockeras i serverkonfigurationen.
PHP behöver skrivrättighet till `mcp/`. Loggen växer tills den rensas manuellt;
stäng av felsökningsloggningen när du är klar. Befintlig logg behålls när den stängs av.

### Skapa en nyckel

1. Logga in på wikin och öppna **Admin → Min MCP-nyckel**.
2. Ange ditt nuvarande lösenord och välj 30, 90 eller 365 dagars giltighet.
3. Markera **Tillåt … ifAuth** om klienten också ska få läsa sådant innehåll.
   Rutan är avmarkerad från början.
4. Klicka **Skapa / ersätt min nyckel** och kopiera nyckeln direkt. Den visas
   bara i det svaret och kan inte hämtas igen.
5. Ange endpointen och HTTP-headern `Authorization: Bearer DIN_NYCKEL` i din
   MCP-klient. Använd klientens stöd för hemligheter/miljövariabler.

Exempel på konfigurationsform för en klient med Streamable HTTP och egna headers
(exakta fältnamn varierar mellan klienter):

```json
{
  "mcpServers": {
    "runewiki": {
      "url": "https://din-wiki.example/mcp/",
      "headers": {
        "Authorization": "Bearer DIN_PERSONLIGA_MCP_NYCKEL"
      }
    }
  }
}
```

Alternativt kan klienten skicka HTTP-headern `API_TOKEN: DIN_NYCKEL`
(även `API-Token` stöds). Värdet är bara nyckeln, utan `Bearer` eller `=`.
Samma nyckelkontroll, ACL och ifAuth gäller. Finns `Authorization` används
den alltid; en felaktig Authorization-header kringgås inte med API_TOKEN.
Vissa proxyservrar blockerar understreck i headers; använd då `API-Token`.

Ett klientfält märkt **Environment (KEY=value per line)** kan exempelvis
innehålla `API_TOKEN=DIN_NYCKEL`, men klienten måste uttryckligen koppla
variabeln till en utgående header. Servern kan inte läsa klientens miljö.
Rekommenderad klientkoppling är `Authorization: Bearer <värdet av API_TOKEN>`.
Skicka inte nyckeln i URL:en eller som MCP-verktygsargument.

Det finns en aktiv nyckel per användare. En ny nyckel ersätter den gamla.
**Återkalla min nyckel** stoppar fortsatt användning. Lösenordsbyte och borttagning
av kontot gör också nyckeln ogiltig. Gruppändringar används vid nästa anrop.
Redan utlämnat innehåll kan inte återkallas från klienten.

Detta är en lokalt utfärdad API-nyckel med Bearer-header, **inte en OAuth-server**.
Klienten måste kunna konfigurera en egen Authorization-header. Klienter som
kräver MCP:s OAuth-flöde behöver en separat OAuth-integration; endpointen
annonserar inte OAuth-discovery eller dynamisk klientregistrering.

## Behörigheter och ifAuth

- Varje POST autentiseras separat. Ingen nyckel, fel nyckel, utgången nyckel
  eller borttaget konto ger **401**. Webbläsarcookies och användarnamn i
  request-body ger ingen MCP-behörighet. Nycklar i URL/query stöds inte.
- Namespace-inställningar hämtas från `config/namespaces.php` och grupper från
  `config/acl.php` och det aktuella kontot. `public` och `login` är läsbara för
  ett konto med giltig nyckel. `private` kräver uttrycklig läs-/redigeringsrätt
  genom en grupp. Okända ACL-lägen nekas.
- **MCP tillämpar dessa regler även om `auth_enabled` är avstängt för webben.**
  En öppen webbinloggningsinställning får inte kringgå API-behörigheterna.
- `<ifAuth>` och `</ifAuth>` fungerar på egna rader, även nästlade. Innehållet
  lämnas bara ut med en giltig nyckel där ifAuth-valet är aktiverat, och bara
  på en sida som användaren får läsa. Utan valet filtreras blocken bort
  **före** titlar, sökning, utdrag och Markdown-svar. Ett oavslutat block
  döljer återstående innehåll. Markörerna tas bort; kodexempel bevaras.
- `ifAuth` är en enkel inloggningsgräns, inte en egen gruppregel. Lägg innehåll
  som endast vissa grupper ska läsa i ett `private` namespace.
- En nekad sida ger samma svar som en saknad sida. Inga globala sökindex eller
  delade svarscachar används. Skyddade titlar, träffantal eller textutdrag
  lämnas inte ut till obehöriga.
- Verktygen läser endast vanliga Markdown-sidor under `content/`. Systemfiler
  som `_sidebar.md`, konfiguration, användarregister, historik och media exponeras
  inte. Symlänkar och icke-kanoniska sid-ID:n nekas. En enskild sida över 1 MiB
  exponeras inte. API:t kan inte skriva sidor, köra kommandon eller hämta externa URL:er.

HTML-kommentarer är författarstöd, **inte ett sekretesskydd**. De följer med
läst Markdown om de inte ligger inuti ett block/namespace som filtreras bort.
Utgående Markdown kan nämna andra sidor, men MCP följer inte länkar automatiskt.
Varje separat läsning kräver egen behörighetskontroll.

## Protokoll och verktyg

Implementationen utgår från den publicerade specifikationen **2026-07-28**,
kontrollerad 2026-09-27, med bakåtkompatibilitet för **2025-11-25**.

Transporten är Streamable HTTP med ett JSON-svar per POST. Ingen SSE-ström,
serverinitierad sampling, prenumeration, session eller bakgrundsuppgift erbjuds.
GET/DELETE ger 405; gamla HTTP+SSE-endpoints ingår inte. JSON-RPC-batcher avvisas.
`Accept` måste inkludera både `application/json` och `text/event-stream` enligt
transportreglerna, även om servern väljer JSON. Request-body begränsas till 64 KiB.

### 2026-07-28

- Ingen initialize-handshake. Varje request har `MCP-Protocol-Version` och
  `Mcp-Method`, plus `Mcp-Name` för `tools/call`.
- `params._meta` innehåller samma protokollversion och ett objekt med
  klientens capabilities. Header/body kontrolleras mot varandra.
- `server/discover`, `ping`, `tools/list` och `tools/call` stöds.
- Resultat innehåller `resultType: "complete"` och serverinformation i `_meta`.
- Felaktiga metadataheaders ger HTTP 400 / `-32020`; okänd version ger
  HTTP 400 / `-32022` med stödda versioner. Okänd metod ger HTTP 404 / `-32601`.

### 2025-11-25

Klienten börjar med `initialize` (headern får saknas på just det anropet),
skickar `notifications/initialized` och använder sedan
`MCP-Protocol-Version: 2025-11-25`. Servern utfärdar ingen sessionsidentifierare;
nyckeln måste följa med varje anrop. Inga moderna metadataheaders krävs i detta läge.

| Verktyg | Argument | Resultat |
|---|---|---|
| `list_pages` | Valfria `namespace`, `offset`, `limit` | Läsbara sid-ID:n och titlar |
| `search_pages` | `query`; valfria `namespace`, `offset`, `limit` | Tillåtna träffar med textutdrag |
| `read_page` | `id` | Sid-ID, titel och filtrerad Markdown |

`namespace` är exakt namespace; tom sträng betyder roten. `limit` är 1–100,
standard 50. `nextOffset` skickas tillbaka som nästa `offset`; `null` betyder
slut på listan. Listan kan ändras mellan anrop om innehåll eller rättigheter ändras.
Verktygsresultaten innehåller både `structuredContent` och en JSON-text i `content`.
Listningen av själva verktygen är densamma för alla autentiserade användare.

Exempel på ett modernt request-body för läsning:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "read_page",
    "arguments": {"id": "start"},
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {},
      "io.modelcontextprotocol/clientInfo": {"name": "example-client", "version": "1.0"}
    }
  }
}
```

Headers för detta anrop:

```http
Content-Type: application/json
Accept: application/json, text/event-stream
Authorization: Bearer DIN_PERSONLIGA_MCP_NYCKEL
MCP-Protocol-Version: 2026-07-28
Mcp-Method: tools/call
Mcp-Name: read_page
```

## Drift

Använd HTTPS. Bakom en TLS-terminerande reverse proxy ska webbservern sätta
PHP:s `HTTPS=on` **enbart för trafik från betrodda proxyn**. Endpointen litar
inte direkt på klientens `X-Forwarded-Proto`.

Apache-routningen i `.htaccess` leder `/mcp` till `mcp/index.php` och vidarebefordrar
Authorization till PHP. Kontrollera att proxy/webbserver inte tar bort headern.
För Nginx/IIS behövs motsvarande separat PHP-route, Authorization-vidarebefordran
och blockering av direktåtkomst till `config/`, `content/`, `core/`, `data/`,
`plugins/` och `skills/`, liksom för resten av wikin.

Nycklar lagras som SHA-256-hash av ett slumpmässigt 256-bitars hemligt värde i
`data/users/mcp/`. Filerna har en PHP-exit-vakt, skrivs atomiskt och katalogen
är blockerad från HTTP genom rotens regler. Katalogen måste vara skrivbar av PHP;
skydda den även på filsystemnivå och i backuper. Klartextnyckeln lagras inte i
session, logg, localStorage eller Git. Undvik att logga Authorization-headers
och adminsidans response-body i proxy/APM.

`config/mcp.php` innehåller:

- `allowed_origins`: exakta betrodda webborigins, t.ex. `https://client.example`.
  Tom lista är standard och nekar alla anrop som har en Origin-header.
  Native MCP-klienter utan Origin-header fungerar med nyckel. Ingen wildcard-CORS.
- `allow_local_http`: avstängt från början. För lokal utveckling kan det aktiveras
  tillsammans med exakt `local_hosts`, t.ex. `127.0.0.1:8080`. Både Host och
  anslutningens loopback-adress måste matcha. Bind utvecklingsservern till loopback.

Sätt lämplig rate limiting och timeout i webbserver/reverse proxy för din
installation. Servern har storleksgränser men ingen distribuerad rate limiter.

## Testning

```sh
php tests/mcp.php
php tests/mcp-http.php
php tests/auth-visibility.php
```

MCP-testerna använder en separat temporär wiki med testkonton. De kontrollerar
protokollversioner, headers, ACL, ifAuth, sökning, path traversal, nyckelrotation,
återkallning, gruppändring, lösenordsbyte och borttagning. Ett symlänktest körs
när operativsystemet tillåter att testprocessen skapar symlänkar.
HTTP-testet startar en lokal PHP-server mot en temporär kopia och kontrollerar
även adminflödets CSRF, lösenordsbekräftelse och att en användare inte kan skapa
eller återkalla någon annans nyckel. Det kräver att `proc_open` är tillgängligt.

## Specifikationer

- [MCP 2026-07-28: versionshantering](https://modelcontextprotocol.io/specification/2026-07-28/basic/versioning)
- [Streamable HTTP](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http)
- [Schema och metadata](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/main/schema/2026-07-28/schema.ts)
- [MCP 2025-11-25: transport](https://modelcontextprotocol.io/specification/2025-11-25/basic/transports)
