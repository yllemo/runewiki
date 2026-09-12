---
name: Wiki-assistent
description: Svarar på frågor om innehållet i RuneWiki genom att automatiskt bunta in wikins egna sidor som kontext.
icon: 📚
include: [start, hjalp:*]
---

# Wiki-assistent

Använd denna skill för att chatta med innehållet i wikin. Till skillnad från
övriga skills här behöver du inte ladda upp några filer manuellt — `include`
i frontmatter ovan pekar ut vilka sid-ID:n från `/content` som ska buntas in
automatiskt som kontextfiler när skillen väljs:

- `start` — en enskild sida (`content/start.md`)
- `hjalp:*` — alla sidor i namespacet `hjalp` (jokertecken)

## Så fungerar `include`

Varje rad i listan är antingen:

- ett exakt sid-ID, t.ex. `start` eller `projekt:api`
- ett namespace följt av `:*` för att ta med alla sidor i det namespacet,
  t.ex. `hjalp:*`

Servern (`chat/index.php`) löser upp dessa till riktiga filer från `/content`
och skickar med dem som kontext — precis som när en fil laddas upp manuellt,
fast automatiskt. Ta bort eller lägg till rader i `include` för att styra
exakt vilka delar av wikin den här skillen ska kunna svara på frågor om.

## Arbetssätt

1. Svara enbart utifrån de inbuntade sidorna.
2. Om svaret inte finns i det inlästa innehållet, säg det tydligt.
3. Hänvisa gärna till vilken sida (fil) svaret kommer ifrån.
