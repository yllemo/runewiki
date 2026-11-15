# Kom igång med RuneWiki

[Till README](README.md) · [Alla guider](DOCUMENTATION.md)

Den här guiden tar dig från en ny klon till en wiki med ett eget konto.

## 1. Kontrollera PHP

```bash
php -v
php -m
```

| Funktion | PHP-krav |
| --- | --- |
| Wiki och textbehandling | PHP 8.1+ och `mbstring` |
| ZIP-export och import | `zip` |
| SVG-redigering och validering | `dom` |
| OpenID Connect | `curl` och `openssl` |
| AD/LDAP-inloggning | `ldap` |

Ingen databas eller Composer-installation behövs. YAML-parsern ingår.
Monaco, Mermaid och importbibliotek laddas från CDN när funktionerna används.

## 2. Starta en lokal installation

```bash
git clone https://github.com/yllemo/runewiki.git
cd runewiki
cp config/config.example.php config/config.php
php -S 127.0.0.1:8080 index.php
```

`cp` fungerar i vanliga Unix-skal och som alias i PowerShell. Du kan också
kopiera filen i filhanteraren. Ersätt inte en befintlig konfiguration vid uppdatering.

Öppna [http://127.0.0.1:8080/](http://127.0.0.1:8080/).
PHP:s inbyggda server används här för lokal utveckling.

## 3. Skapa ett konto

Öppna en andra terminal i projektmappen:

```bash
php bin/cli.php create-user mittnamn
```

Ange och bekräfta lösenordet. Logga in via `/?do=login` och öppna `/admin/`.
Adminpanelen kräver alltid en inloggad användare, även på en öppet redigerbar wiki.

Välj under admin om redigering ska kräva inloggning. Om du använder grupper
behöver kontot också rätt namespace-behörigheter. Se [inloggning och ACL](readme-auth.md)
och [handboken](HANDBOOK.md#inloggning-och-redigeringsrättigheter).

## 4. Skapa innehåll

1. Öppna startsidan och välj **Redigera**.
2. Skriv en rubrik, lite text och en wikilänk, exempelvis `[[team:start|Teamet]]`.
3. Spara. Följ länken för att skapa den nya sidan.
4. Använd **Bläddra i media** för bilder och SVG, eller Ctrl+Space för syntaxförslag.

```text
content/
+-- start.md            Din startsida
+-- _topbar.md          Huvudmenyn
+-- _sidebar.md         Sidomenyn
+-- team/
    +-- start.md        /team/start
    +-- rutiner.md      /team/rutiner
```

[Exempelguiden](EXAMPLES.md) innehåller färdiga utgångspunkter.

## 5. Kör med Apache

- Peka webbplatsens dokumentrot mot projektroten.
- Aktivera `mod_rewrite` och tillåt reglerna i projektets `.htaccess`.
- Ge PHP skrivrätt till `content/`, `images/` och `data/`.
- För inställningar via admin behöver PHP kunna skriva till `config/`.
- MCP:s valfria diagnostiklogg kräver även skrivrätt till `mcp/`.
- Använd HTTPS för MCP och extern inloggning.

Innan du öppnar installationen för andra: konfigurera konton och behörigheter
och kontrollera åtkomsten som utloggad besökare. Sätt `require_login: true`
på sidor som kräver inloggning; se [sidåtkomst](readme-page-access.md).

## Säkerhetskopiera rätt saker

```text
Innehall     content/ + images/
Driftdata    data/    (historik, kommentarer, konton med mera)
Installning  config/
```

Adminpanelens innehållsexport gäller `content/`, inte hela installationen.
Säkerhetskopiera även media, driftdata och konfiguration om hela wikin ska
kunna återställas. Se [export och import](readme-import.md).
