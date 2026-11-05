# Samarbeta och kommentera

Öppna en artikel och välj **… → Samarbeta och kommentera**. Samarbetsvyn använder
RuneWikis vanliga layout, tema och Markdown-rendering, inklusive länkar och Mermaid.
Den vanliga artikeln ändras inte när någon kommenterar.

## Lämna synpunkter

- **Kommentera artikeln** skapar en allmän kommentar.
- Pratbubblan vid ett textblock väljer det stycket som sammanhang.
- Ange ett valfritt namn och skriv kommentaren. Gästnamn markeras med **(gäst)**
  och är inte verifierade identiteter. Inloggade visas med sitt wikikonto.
- **Svara** lägger ett svar i en tråd. **Alla/Öppna/Lösta** filtrerar trådarna.
- Kommentarerna uppdateras var femtonde sekund när sidan är synlig; **Uppdatera**
  hämtar direkt. Text du skriver i formuläret påverkas inte av uppdateringen.
- Inloggade användare med redigeringsrätt för artikeln kan lösa/återöppna trådar
  och ta bort kommentarer. Radering av en tråd tar också bort dess svar.

Gäster behöver inget konto på offentliga sidor. Namespace-regler gäller fortfarande:
en sida som kräver inloggning blir inte offentlig genom samarbetsvyn.
Kommentarer knutna till text som bara finns i `ifAuth` markeras som endast för
inloggade; även svar och exporter respekterar detta. Allmänna kommentarer är synliga
för alla som får läsa artikeln, så skriv inte hemligheter i en allmän tråd.

## Export och lagring

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
lagring, trådar, gästinlägg, moderering och filtrering av privata kommentarer/exporter.
