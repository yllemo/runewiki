# Helskärm och filimport i editorn

Öppna en artikel med **Redigera**. **Helskärm** låter editorn fylla hela
webbläsarens yta med verktygsraden och Spara kvar. Välj **Avsluta helskärm**
eller tryck Escape för att återgå. Texten och markörpositionen bevaras.

## Importera ett dokument

1. Välj **Importera fil** i editorns verktygsrad.
2. Välj en fil och vänta på konverteringen.
3. Granska och vid behov redigera Markdown-texten i förhandsgranskningen.
4. Välj **Infoga vid markören** (ersätter eventuell markering) eller
   **Ersätt hela innehållet**. Det senare kräver bekräftelse och ersätter även
   YAML-frontmatter. Avbryt importen med krysset eller Escape.
5. Kontrollera innehållet i editorn och välj **Spara** som vanligt.

Importen sparar inte automatiskt. Ctrl+Z ångrar en infogning eller ersättning.
Om konverteringen misslyckas finns den befintliga texten kvar.

| Format | Resultat och begränsningar |
|---|---|
| `.md`, `.markdown` | Markdown inklusive metadata importeras som text. |
| `.txt` | Text läses som UTF-8. |
| `.html`, `.htm` | Rubriker, listor, länkar, formatering och enkla tabeller blir Markdown. Aktivt HTML-innehåll tas bort. Bilder blir eventuell alternativtext. |
| `.docx` | Word konverteras till Markdown. Bilder importeras inte; komplex layout och tabeller kan behöva justeras. Äldre `.doc` stöds inte. |
| `.pdf` | Text hämtas sida för sida. Läsordning, spalter och tabeller kan behöva korrigeras. Skannade sidor kräver OCR i ett annat verktyg före import; tomma sidor hoppas över med besked. |

Maximal filstorlek är 25 MB, PDF får ha högst 200 sidor och resultatet får
innehålla högst 2 miljoner tecken. Import sker i webbläsaren; dokumentet skickas
inte till en konverteringstjänst eller PHP-servern. Den vanliga sparfunktionen
och dess åtkomstregler används när du sparar Markdown-texten.

## Teknik och drift

`templates/default/assets/js/editor-tools.js` hanterar helskärm, konvertering,
förhandsgranskning och Monacos undo-historik. Importbiblioteken laddas vid behov
från jsDelivr med låsta versioner: [Mammoth](https://github.com/mwilliamson/mammoth.js)
1.13.0, [Turndown](https://github.com/mixmark-io/turndown) 7.2.5 och
[PDF.js](https://github.com/mozilla/pdf.js) 6.5.438 med samma version för worker.
Webbläsaren behöver tillgång till CDN:en, liksom för Monaco. Ingen extra
PHP-konverterare eller serverinstallation krävs. Anpassad Content Security Policy
måste tillåta dessa skript och PDF.js worker (inklusive blob-worker).

Webbläsartest: `node tests/editor-tools.cjs` från projektroten, med Playwright,
Edge och tillgång till CDN. Testet använder syntetiska dokument och kontrollerar
konvertering, förhandsgranskning, infogning, undo samt helskärm på desktop/mobil.
