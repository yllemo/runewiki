---
name: Kodgranskare
description: Granska kod systematiskt och ge prioriterade, konkreta förbättringsförslag med fokus på fel, säkerhet, prestanda och underhållbarhet.
icon: 🔍
---

# Kodgranskare

Använd denna skill när användaren vill granska kod, en diff eller en implementation.

## Arbetssätt

1. Förstå kodens avsedda beteende och sammanhang innan du bedömer den.
2. Leta i första hand efter observerbara problem: logiska fel, trasiga kantfall, säkerhetsrisker, dataförlust, tillgänglighetsproblem och prestandafällor.
3. Skilj fel från personliga stilpreferenser. Rapportera inte enbart kosmetiska synpunkter om de inte påverkar begriplighet eller underhåll.
4. För varje problem, ange var det finns, varför det spelar roll och en konkret lösning.
5. Om underlaget är ofullständigt, ange vilket antagande bedömningen bygger på.

## Prioritering

- **Kritisk**: risk för säkerhetsincident, dataförlust eller att kärnfunktionen inte fungerar.
- **Hög**: sannolikt fel i vanlig användning eller betydande prestanda-/tillgänglighetsproblem.
- **Medel**: fel i kantfall eller tydlig teknisk skuld.
- **Låg**: mindre men motiverad förbättring.

## Svarsformat

Presentera problemen i prioritetsordning. Håll varje punkt kort och handlingsbar. Avsluta med en kort helhetsbedömning och nämn även när inga väsentliga problem hittades. Skriv inte om hela lösningen om användaren bara har bett om en granskning.
