# Samarbeta och kommentera

Öppna en artikel och välj **… → Samarbeta och kommentera**. Samarbetsvyn använder
RuneWikis vanliga layout, tema och Markdown-rendering, inklusive länkar och Mermaid.
Den vanliga artikeln ändras inte när någon kommenterar.

## Lämna synpunkter

- **Kommentera artikeln** skapar en allmän kommentar.
- Pratbubblan vid ett textblock öppnar en popup med styckets kommentarer och
  formulär. Artikeln behåller sin bredd. Stäng med krysset, Escape eller klick
  utanför popupen. **Visa stycke** stänger popupen och visar textblocket.
  Hovra över pratbubblan eller fokusera den med Tab för att markera det
  tillhörande blocket med en ram. Varje bubbla sitter vid sitt eget renderade
  Markdown-block; exempelvis markeras en hel lista, tabell eller ett kodblock.
- Ange ett valfritt namn och skriv kommentaren. Gästnamn markeras med **(gäst)**
  och är inte verifierade identiteter. Inloggade visas med sitt wikikonto.
- **Svara** lägger ett svar i en tråd. **Alla/Öppna/Lösta** filtrerar trådarna.
- Kommentarerna uppdateras var femtonde sekund när sidan är synlig; **Uppdatera**
  hämtar direkt. Text du skriver i formuläret påverkas inte av uppdateringen.
- Inloggade användare med redigeringsrätt för artikeln kan lösa/återöppna trådar
  och ta bort kommentarer. Radering av en tråd tar också bort dess svar.

Gäster behöver som standard inget konto på offentliga sidor.
Under **Admin → Webbplats → Kräv inloggning för att se Samarbeta och kommentera** kan du
kräva inloggning för hela samarbetsfunktionen, inklusive läsning, skrivning och
export av kommentarer. Inställningen sparas i `config/config.php` som
`'colab_require_login' => true` (standard `false`) och fungerar oberoende av
`auth_enabled`. Gäster skickas till inloggning när de öppnar vyn; API-anrop och
export nekas med HTTP 401. Menyvalet döljs också för gäster.

Inställningen **Kräv inloggning för att ge feedback** styr separat om gäster får
skapa kommentarer och svar. Den sparas som `'colab_feedback_require_login' => true`
i samma fil (standard `false`). Med offentlig visning och skyddad feedback kan
gäster läsa kommentarer, men får en inloggningslänk i stället för
formuläret. Servern nekar också direkta försök att skriva feedback med HTTP 401.
Om visningen kräver inloggning kan gäster inte använda funktionen alls, oavsett
feedbackinställning. Namespace-regler gäller fortfarande:
en sida som kräver inloggning blir inte offentlig genom samarbetsvyn.
Även sidans YAML-fält `require_login: true` skyddar samarbetsvyn, kommentarer
och exporter, oavsett colab-inställningarna. Se [sidans åtkomstskydd](readme-page-access.md).
Kommentarer knutna till text som bara finns i `ifAuth` markeras som endast för
inloggade; även svar och exporter respekterar detta. Allmänna kommentarer är synliga
för alla som får läsa artikeln, så skriv inte hemligheter i en allmän tråd.

## Export och lagring

Knapparna **Exportera kommentarer** och **JSON** visas endast för inloggade.
Detta styr knapparnas synlighet: direkta exportadresser följer samma läsrätt
som samarbetsvyn. Aktivera `colab_require_login` för att även kräva inloggning
för direktåtkomst till kommentarer och exporter.

**Exportera kommentarer** laddar ner en textfil. **JSON** exporterar strukturerad
data med artikel-ID, trådar, författare, tid, citat och status. Exporten innehåller
bara kommentarer som den aktuella besökaren får läsa.

Kommentarerna sparas i `data/comments/<sha256-av-sid-id>.json`. PHP behöver
skrivrättigheter där. Mappen skyddas av det befintliga direktåtkomstskyddet för
`data/` och ignoreras av Git. Samtidiga läsningar/skrivningar använder fillås.
Ta med `data/comments/` i serverbackup; innehålls-ZIP-exporten omfattar inte kommentarer.

Stycken kopplas med ett citat från den renderade texten, högst 1 000 tecken.
Identiska citat delar kommentarssammanhang. Om texten ändras behålls kommentaren
och dess ursprungliga citat, med besked om att stycket inte längre kan hittas.
Kommentarer följer sid-ID:t; sidflytt/namnbyte flyttar inte kommentarsfilen automatiskt.

## Begränsningar och drift

Max 5 000 tecken per kommentar, 80 tecken i gästnamn och 1 000 kommentarer eller
4 MB lagrad JSON per artikel. Sessionen måste vänta fem sekunder mellan inlägg.
Alla skrivningar kräver CSRF-token. Kommentarer renderas som ren text, inte HTML.
På en publik internetinstallation kan ytterligare spambegränsning behövas i proxyn.
Detta är artikelgranskning med kommentarer, inte samtidig redigering av Markdown.

Tester: `php tests/comments.php` och `php tests/mcp-http.php` (mbstring) verifierar
lagring, trådar, gästinlägg, moderering, separata krav för visning och feedback,
admininställningarnas sparning och filtrering av privata kommentarer/exporter.
