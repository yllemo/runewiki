# Redirect

[Tillbaka till RuneWiki](../../README.md)

Omdirigerar direkt med HTTP 302 när en besökare öppnar en sida. Ingen
mellansida eller notifikation visas. Sidans läsbehörighet kontrolleras först.

## Aktivera eller stäng av

I `config/plugins.php`:

```php
'redirect' => ['enabled' => true, 'options' => []],
```

Sätt `enabled` till `false` för att stänga av alla omdirigeringar från pluginet.
Pluginet levereras aktiverat, men gör inget på sidor utan `redirect`.

## Ange adress på en sida

Redigera sidans YAML-frontmatter:

```markdown
---
title: Gammal sida
redirect: https://example.com/ny-sida
---

# Gammal sida
```

En lokal adress fungerar också: `redirect: /projekt/ny-sida`.
Använd full `https://`/`http://`-URL eller en lokal sökväg som börjar med `/`.
JavaScript-URL:er, protokollrelativa URL:er (`//...`) och adresser med
inloggningsuppgifter accepteras inte. Ogiltiga värden ger vanlig sidvisning.

## Ändra eller ta bort omdirigeringen

Öppna sidans egen adress med `?do=edit`, t.ex. `/gammal-sida?do=edit`.
Redigeringsläget omdirigeras aldrig. Ändra `redirect` eller ta bort fältet
och spara. Redigering kräver samma behörighet som tidigare.

Om fältet ska föreslås i Ctrl+Space, välj **redirect** under
**Admin → Metadata → Föreslå i Ctrl+Space**.

Metadata, historik, nedladdning, fokusläge och MCP behåller sina normala
funktioner. Omdirigering körs före sidans HTML-cache och använder `no-store`,
så ändrade adresser börjar gälla utan permanent 301-cache i webbläsaren.
En direkt lokal länk tillbaka till samma sökväg ignoreras. Undvik kedjor som
går tillbaka till ursprungssidan; externa och flersidiga loopar upptäcks inte.

## Kontrollera installationen

1. Skapa en testsida med `redirect: /start` och spara.
2. Öppna sidan normalt: webbläsaren ska direkt hamna på startsidan.
3. Öppna testsidan med `?do=edit`: editorn ska visas med redirect-fältet kvar.
4. Sätt pluginets `enabled` till `false`: sidan ska visas normalt igen.

Kör `php tests/redirect.php` för URL-validering och aktivering/avaktivering.
`php tests/mcp-http.php` verifierar också HTTP-omdirigering, redigering och
nedladdning i en isolerad testinstallation.
