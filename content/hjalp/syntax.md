---
title: Syntax-guide
description: Genomgång av wikins Markdown- och wikisyntax
tags: [hjälp, dokumentation]
---

# Syntax-guide

En snabb genomgång av allt du kan skriva i en wikisida. Fullständig
dokumentation finns i `README.md` i projektroten.

## Wikilänkar

`[[namespace:sida]]` länkar internt. Sidor som inte finns än visas som en
röd, streckad länk — klicka för att skapa dem direkt:

[[hjalp:en-sida-som-inte-finns-an]]

Namespace-syntaxen `namespace:page1:start` mappas till filen
`content/namespace/page1.start.md` — första kolondelen blir mappen, resten
slås ihop med `.` till filnamnet.

Egen länktext: `[[namespace:sida|Egen text]]`. Snedstreck fungerar också:
`[[namespace/sida]]` normaliseras automatiskt till kolon.

## Markdown-länkar

`[text](/namespace/sida)` och `[text](namespace:sida)` fungerar som interna
länkar (samma röda-länk-koll som ovan). `[text](https://...)` blir en
extern länk.

## Interwiki-länkar

Genvägar konfigurerade i `config/interwiki.php`:

* [[wp>Wikipedia]] — Wikipedia
* [[php>function.array-map]] — PHP-manualen

## Media

Ladda upp en fil på [/images](/images) och bädda in den i en sida:

```
{{namespace:bild.png}}
{{namespace:bild.png|Alt-text}}
{{namespace:dokument.pdf|Ladda ner}}
```

Bilder (`png`, `jpg`, `gif`, `svg`, `webp` m.fl.) visas inbäddade —
övriga filtyper blir en nedladdningslänk.

Du kan också kopiera en bild eller skärmbild och trycka **Ctrl+V** direkt
i editorn. Bilden laddas upp till `images/` (i sidans namespace om det finns)
och infogas som `![Beskrivning av bilden](/images/bild-….png)`.
Byt beskrivningen till en passande alt-text. Spara-knapparna blir tillgängliga
när uppladdningen är klar. Vanlig text klistras in som vanligt.

## Taggar

Sätt `tags: [en, två]` i frontmatten så blir de klickbara badges som söker
fram alla sidor med samma tagg (`tag:nyckelord`).

## Standard-Markdown

**Fet text**, *kursiv text*, `inline-kod`, kodblock med språkmarkering,
punktlistor, numrerade listor och citat:

```php
echo "kodblock med syntax-markering";
```

- Punktlista
- Ännu en punkt

1. Numrerad lista
2. Andra punkten

> Ett citat.

### Tabeller

Använd kolon i avdelarraden för vänster-, höger- eller mittjustering:

| Område | Klart | Status |
| :--- | ---: | :---: |
| **Dokumentation** | 80 % | Pågår |
| Bilder | 100 % | Klar |

På små skärmar kan breda tabeller rullas i sidled. Skriv `\|` för ett
lodrätt streck i en cell. Wikilänkar och inline-kod fungerar också i tabeller.

### Checklistor och underlistor

- [x] Färdig uppgift
- [ ] Återstående uppgift
  - En underpunkt
  - Ytterligare en underpunkt

Ändra `[ ]` till `[x]` i editorn för att markera en uppgift som klar.
Du kan även skriva ~~överstruken text~~ och använda `_kursiv_` eller
`__fet__` text. Vanliga Markdown-bilder stöds: `![Alt-text](/images/bild.png)`.

## Mermaid-diagram

Ett kodblock märkt `mermaid` renderas som ett riktigt diagram, både på
wikisidor och i AI-chatten:

```mermaid
flowchart LR
    A[Skriv Markdown] --> B{Sparar du?}
    B -- Ja --> C[Sidan cachas som HTML]
    B -- Nej --> A
```

På wikisidor kan du klicka på diagrammet eller **Förstora diagram** för
att öppna diagramvisaren. Zooma med mushjulet eller knapparna **+ / −**,
och dra diagrammet för att panorera. **Anpassa** visar hela diagrammet.
Med tangentbordet fungerar piltangenterna, **+ / −**, **0** för att anpassa
och **Esc** för att stänga.
