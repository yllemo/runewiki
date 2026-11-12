# Skapa och redigera SVG

## I artikelredigeraren

Välj **Bläddra i media → Redigera SVG** i artikelredigeraren. Fönstret visar SVG-kod och en automatisk
förhandsvisning sida vid sida (staplade på mobil). Här kan du:

- Börja från **Kort sammanfattning**, **Publiceringsflöde**, **Systemöversikt**,
  **Tidslinje** eller **Ansvarsfördelning**. Mallarna har svenska exempeltexter
  för wikiartiklar; anpassa innehåll, roller och färger i SVG-koden.
- Öppna en lokal `.svg` eller välja en befintlig SVG från media.
- Ändra former, färger, text, storlek och `viewBox` i koden.
- **Infoga SVG-kod** som ett redigerbart `svg`-kodblock i Markdown.
- **Spara SVG i media**, eller **Spara och infoga bild** som en vanlig bildlänk.

Sätt markören i ett befintligt `svg`-kodblock och välj **Bläddra i media → Redigera SVG** för att
öppna just det blocket. Även markerad råkod som börjar med `<svg` kan öppnas.
Infogningen går att ångra med Ctrl+Z. Artikeln sparas först med vanliga **Spara**;
mediefilen sparas direkt när du väljer en av SVG-fönstrets sparknappar.

Ctrl+Space i Markdown-editorn har **SVG informationsruta** och **SVG ikon**.
I SVG-kodfältet öppnar Ctrl+Space exempelväljaren; Tab infogar två mellanslag.

## I mediahanteraren

Under **Media** finns **Skapa SVG** och **Redigera SVG** på befintliga SVG-filer.
Ange ett medie-ID som `projekt:diagram.svg` för att spara i
`images/projekt/diagram.svg`, eller `diagram.svg` för roten. Medialistan
uppdateras när du stänger fönstret efter sparning.

Befintliga filer måste öppnas innan de skrivs över. Om filen ändrats sedan den
öppnades nekas sparningen; öppna filen igen och för över dina ändringar.
Att välja ett nytt filnamn skapar en separat fil.

## SVG direkt i Markdown

````markdown
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 100" width="320" height="100">
  <rect x="2" y="2" width="316" height="96" rx="12" fill="#e8f4fc" stroke="#0077bc"/>
  <text x="24" y="60" font-family="sans-serif" font-size="24">Hej SVG!</text>
</svg>
```
````

Giltiga block visas som bilder och kan öppnas i SVG-visaren. Ogiltiga eller
otillåtna block visas som vanlig kod. För att dokumentera SVG som kod utan att
visa bilden, använd exempelvis ett `xml`-kodblock i stället.
SVG-filer kan också bäddas in med `{{projekt:diagram.svg?300}}` eller
`![Diagram](/images/projekt/diagram.svg?300)`.

## Behörighet och formatstöd

Redigering av mediefiler följer samma läs-/redigeringsrätt per namespace som
mediahanteraren. En helt öppen wiki tillåter därför även gästredigering.
POST-sparning kräver CSRF-token och SVG måste vara tillåtet i `config/media.php`.
Sidans `require_login` skyddar artikeln, men mediefiler är separata resurser.

Maximal kodstorlek är 1 MiB. PHP:s DOM-tillägg krävs för validering.
Statisk SVG stöds: former, text, grupper, gradienter, lokala referenser,
masker, klippning och vanliga filter. Skript, händelseattribut, animation,
`foreignObject`, externa resurser, DTD/entiteter och komplex CSS stöds inte.
Använd SVG-presentationsegenskaper som `fill`, `stroke` och `font-size`.
Spararen avvisar otillåten kod i stället för att tyst ta bort delar av bilden.

Förhandsvisningen och renderade Markdown-block visas som isolerade bilder;
SVG injiceras inte som aktiv HTML. Den nya valideringen gäller SVG-editorns
sparfunktion och `svg`-kodblock, inte retroaktivt för äldre medieuppladdningar.
Redigeraren är kodbaserad, inte ett ritprogram med dragbara former.

Tillfälliga skrivlås ligger under `data/svg-locks/` och följer inte med Git.
Tester: `php tests/svg.php` och `php tests/mcp-http.php` verifierar validering,
sökvägar, filkonflikter, CSRF och åtkomst. UI-test: `node tests/svg-editor.cjs`
(Playwright, Edge, PHP och tillgång till Monaco-CDN; välj PHP med `PHP_BINARY`).
