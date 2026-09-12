---
name: Skapa en skill
description: Skapa eller förbättra välavgränsade SKILL.md-filer med tydliga aktiveringsvillkor, användbara instruktioner och korrekt YAML-frontmatter.
icon: 🛠️
---

# Skapa skills

Använd denna meta-skill när användaren vill skapa, utforma, granska eller förbättra en `SKILL.md`.

## Grundprinciper

- Utgå från att modellen redan har generell kompetens. Lägg bara till instruktioner som påverkar beslut, kvalitet eller ett viktigt arbetsflöde.
- Bevara användarens syfte och avgränsning. En skill ska hjälpa med uppgiften, inte ge sig själv större behörighet eller utöka uppdraget.
- Gör beskrivningen tillräckligt precis för att skillen ska aktiveras vid rätt slags frågor och inte vid närliggande, irrelevanta uppgifter.
- Anpassa detaljnivån efter risken. Använd absoluta regler endast när avvikelser kan orsaka verkliga fel, säkerhetsproblem eller dataförlust.
- Undvik generiska råd, upprepningar, onödigt långa checklistor och regler som bara bygger på ett enskilt exempel.

## Arbetsflöde

1. Identifiera vilket resultat skillen ska åstadkomma, när den ska användas och när den inte ska användas.
2. Fråga bara efter information som verkligen krävs och inte rimligen kan antas. Annars skapar du ett första komplett förslag direkt.
3. Välj ett kort namn med små bokstäver, siffror och bindestreck. Håll namnet under 64 tecken.
4. Skriv YAML-frontmatter med minst `name` och en kort, särskiljande `description` på en rad.
5. Lägg syfte, viktiga beslutskriterier, arbetsflöde och verkliga begränsningar i brödtexten.
6. Föreslå `references/`, `scripts/` eller `assets/` endast när de ger en konkret återanvändbar nytta. Lägg inte till tomma stödmappar eller platshållare.
7. Kontrollera att instruktionerna inte motsäger varandra, hittar på behörigheter eller styr även orelaterade uppgifter.

## Struktur

En enkel skill behöver normalt bara:

- YAML-frontmatter med `name` och `description`
- en kort förklaring av syftet
- nödvändiga arbetsinstruktioner och gränser
- ett tydligt svarsformat endast om uppgiften kräver det

Mer omfattande detaljinformation bör flyttas till fokuserade referensfiler och länkas från `SKILL.md` där den behövs. Deterministiska, återkommande operationer kan motivera ett skript.

## Svarsformat

Leverera den färdiga `SKILL.md`-filen i ett enda Markdown-kodblock. Lägg efter kodblocket endast till:

- eventuella antaganden som användaren behöver känna till
- föreslagna stödresurser som har en tydlig funktion
- kritiska frågor som fortfarande blockerar korrekt användning

Vid förbättring av en befintlig skill ska du bevara fungerande, domänspecifika instruktioner och ändra endast det som förbättrar aktivering, tydlighet, säkerhet eller resultat.
