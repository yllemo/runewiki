# Inloggningskrav per sida

Lägg `require_login: true` i YAML-frontmatter längst upp i Markdown-filen:

```markdown
---
title: Intern information
require_login: true
tags:
  - internt
---

# Intern information

Den här sidan kräver inloggning.
```

Använd `true` eller `false` **utan citattecken**. `false`, eller att ta bort
fältet, tar bort sidans extra inloggningskrav. Namespace-regler och gruppbehörigheter
gäller fortfarande. Kravet gäller även när webbplatsens `auth_enabled` är `false`.

Fältet finns via Ctrl+Space inne i frontmatter. På installationer med sparade
editorfält kan det behöva väljas under **Admin → Metadata**. Att dölja fältet
i metadata-inställningarna stänger inte av dess åtkomstskydd.

## Skyddade ytor

- Artikel, metadata, läs-/fokusläge och Markdown-nedladdning.
- Samarbetsvy, kommentarer och kommentarsexporter.
- Redigering, sparning, radering, flytt och formulär som arbetar med sidan.
- Historik och återställning. Äldre versioner med eget inloggningskrav skyddas
  även när den nuvarande sidan blivit offentlig eller tagits bort.
- Fritextsökning, exakt sid-ID, taggar och namespaces.
- Sidindex, bakåtlänkar, bildreferenser, automatiska länktitlar och editorförslag.
- AI-chattens filval, förhandsvisning, sökning, taggar, namespace-antal och
  wikiinnehåll som läggs till genom en skills `include`.
- MCP-läsning, listning och sökning.

Direkt åtkomst i wikin skickar gäster till inloggning. Listor och sökresultat
utelämnar skyddade sidor. HTTP-svar för wiki och chatt använder `private, no-store`.
Uttryckliga länkar till befintliga skyddade sidor visas med vanlig länkfärg,
ett lås och texten **Inloggning krävs** vid hover. De använder länkens skrivna
text eller sid-ID:ts namn, inte skyddade titlar från sidinnehållet. Klick leder
till inloggning och därefter tillbaka till sidan. Saknade sidor är fortsatt röda.
Renderingar med interna länkar återanvänds inte från HTML-cachen, eftersom
målsidans skydd kan ändras.

För MCP krävs både giltig personlig nyckel och **Tillåt … ifAuth** för att läsa
`require_login: true`. Kontot måste också klara namespace-ACL. Utan behörighet
behandlas sidan som saknad, även i sökning och listning.

## Filhantering

Kravet gäller Markdown-sidor under `content/`, inklusive `_sidebar.md` och
`_topbar.md`. Den medföljande `.htaccess` blockerar direkt statisk åtkomst.
Egen webbserverkonfiguration måste också blockera `content/`, `data/` och övriga
interna mappar. Bilder och bilagor under `images/` ärver inte sidans skydd.
Tidigare nedladdade kopior eller text kopierad till en offentlig sida skyddas
inte i efterhand.

Administratörens ZIP-export är en backup med råfiler och frontmatter; skyddet
följer med vid import till en uppdaterad RuneWiki-installation.

Ogiltig, oavslutad eller alltför stor frontmatter nekas för gäster så att YAML-fel
inte öppnar skyddat innehåll. Inloggade kan rätta filen. Den filbaserade
kontrollen läser högst 1 MiB frontmatter.

Tester: `php tests/page-access.php`, `php tests/mcp.php` och
`php tests/mcp-http.php` (mbstring; zip aktiverar även importtesterna).

## Skyddade namespaces

Under **Admin → Inloggning → Skyddade namespaces** kan du ange ett namespace
per rad, exempelvis `internt` och `projekt`. Namespace är första mappen under
`content/`; dess sidor och underliggande sökvägar omfattas. `*` kräver
inloggning för hela wikin. En tom lista tar bort just dessa krav utan att
ändra befintliga ACL-regler eller sidornas `require_login`.

Inställningen sparas i `config/namespaces.php`, exempelvis:

```php
return [
    'internt' => ['require_login' => true],
];
```

Lägg till fältet i befintlig konfiguration; behåll övriga inställningar.
Kravet gäller oavsett `auth_enabled`. Utloggade besökare får inte läsa
artiklarna via visning, nedladdning, historik, läsläge, samarbete, sökning
eller AI-chattens innehålls-API. MCP kräver en giltig personlig nyckel med
`ifAuth: true`; namespace-ACL gäller fortfarande. En global restriktion
under nyckeln `''` kan inte kringgås med `require_login: false` i ett namespace.

Skyddet gäller wikiinnehåll. Direktlänkade filer i `images/` är separata
medieresurser och skyddas inte av artikelns namespace-inställning.
