# Dokumentation

[Till RuneWiki](README.md)

## Ny i projektet?

| Guide | Innehåll |
| --- | --- |
| [README](README.md) | Vad RuneWiki är och vad du kan använda det till. |
| [Kom igång](GETTING_STARTED.md) | Krav, lokal start, första konto och Apache. |
| [Exempel](EXAMPLES.md) | Handbok, menyer, diagram och bilder att bygga vidare på. |
| [Arkitekturöversikt](ARCHITECTURE.md) | Hur filer, PHP, webbläsare, AI och MCP hänger ihop. |

## Skriva och samarbeta

- [Editorn](readme-editor.md) — import, helskärm, bilder och Mermaid-redigering.
- [SVG](readme-svg.md) — skapa illustrationer, redigera kod och spara media.
- [Samarbete](readme-colab.md) — blockkommentarer, behörigheter och export.
- [Handboken](HANDBOOK.md) — syntax, menyer, metadata, layout, sökning och adminens fileditor.

## Installera och administrera

- [Inloggning](readme-auth.md) — OIDC/OAuth och lokalt AD/LDAP.
- [Åtkomst per sida](readme-page-access.md) — `require_login`, sökning och andra läsvägar.
- [Export och import](readme-import.md) — ZIP, uppladdning i delar och flytt mellan installationer.
- [MCP](readme-mcp.md) — personliga nycklar, anslutning, behörigheter och felsökning.

## Utöka och utveckla

- [Projektstruktur](STRUCTURE.md) — filer, klasser och tekniska flöden.
- [Chatt och skills](HANDBOOK.md#ai-chatt-och-skills-chat-skills) — instruktioner, kontext och snabbkommandon.
- [Plugin-exempel](HANDBOOK.md#plugin-exempel) — struktur och händelser.
- [Redirect-plugin](plugins/redirect/README.md) — omdirigering per sida.
- [Byråkrazy-plugin](plugins/byrakrazy/README.md) — ytterligare ett konkret plugin-exempel.

## Hitta vanliga inställningar

| Jag vill ändra… | Läs här |
| --- | --- |
| Menyn och dess underpunkter | [Toppmenyn](HANDBOOK.md#toppmenyn-_topbarmd) |
| Sidans bredd eller dölja navigation | [Sidlayout](HANDBOOK.md#sidlayout-för-landningssidor) |
| YAML-fält, taggar och OKF | [Metadata](HANDBOOK.md#yaml-frontmatter-och-okf-metadata) |
| Antal sparade versioner | [Versionshistorik](HANDBOOK.md#versionshistorik) |
| Filer och mappar direkt i admin | [Rå filredigering](HANDBOOK.md#rå-filredigering-under-admin) |
| Visa nyligen ändrade sidor i en artikel | [Senast ändrat](HANDBOOK.md#senast-ändrade-sidor) |
| Kräva inloggning för ett helt namespace | [Skyddade namespaces](readme-page-access.md#skyddade-namespaces) |
