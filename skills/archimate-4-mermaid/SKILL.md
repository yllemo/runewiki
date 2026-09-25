---
name: ArchiMate 4 – Mermaid
icon: 🧩
description: >
  Skapa ArchiMate 4 (C260)-diagram i Mermaid med officiella Modern Color Set-färger,
  svenska etiketter och stereotyper i guillemets. Täcker alla sju domäner:
  Motivation, Strategy, Common (ny i AM4), Business, Application, Technology
  och Implementation & Migration. Physical Layer är borttaget — fysiska element
  ingår nu i Technology-domänen. Common-domänen samlar delade beteendeelement.
keywords:
  - archimate
  - archimate 4
  - archimate c260
  - mermaid
  - enterprise architecture
  - verksamhetsarkitektur
  - applikationsarkitektur
  - teknologiarkitektur
  - förmågekarta
  - värdeström
  - migrationsplan
  - systemlandskap
author: The Open Group C260 / svensk Mermaid-anpassning
version: "4.0"
applyTo: ["**/*.md", "**/*.mermaid", "**/*.mmd", "**/*.html"]
tools: [mermaid, diagrams, markdown]
domains: [enterprise-architecture, business-modeling, system-design, documentation]
---

# ArchiMate 4 (C260) Modern Color Set för Mermaid (svenska)

Denna skill används för att generera Mermaid-diagram som följer ArchiMate 4 (C260) Modern Color Set med svenska termer, fasta domänfärger och konsekvent notation. Fokus ligger på Mermaid-diagram, men skillen innehåller även riktlinjer för HTML-inbäddning när det behövs för bättre textstyling.

## Syfte

## Användning i AI-chatten: /mm

- Skapa ett färdigt Mermaid-diagram utifrån valda kontextfiler och den aktuella konversationen.
- Text efter `/mm` anger önskad diagramtyp, fokus och avgränsning, till exempel `/mm integrationskarta` eller `/mm sekvensdiagram för beställning`.
- Utan extra instruktion: välj en domänindelad arkitekturöversikt med `flowchart TB`. Ta bara med domäner som är relevanta för materialet, i den angivna domänordningen.
- Svara med en kort svensk introduktion följd av ett komplett kodblock märkt `mermaid`, så att chatten kan rendera diagrammet. Skapa inte en ny skillfil som svar.
- Utgå från fakta i underlaget. Hitta inte på system, relationer eller datum. Om underlag saknas för den efterfrågade vyn, ställ en konkret följdfråga.
- Chattens renderare stöder HTML-etiketter. Använd därför HTML-varianten för flowchart. Inkludera de sju classDef-definitionerna i flowchart; använd diagramtypens egna tema- och färginställningar för övriga typer eftersom classDef inte stöds överallt.
- De diagramspecifika undantagen nedan har företräde framför generella stereotypregler.

## När skillen används

Använd denna skill när användaren vill:

- skapa ArchiMate-diagram i Mermaid
- få korrekta ArchiMate 4-färger
- visualisera domäner som Motivation, Strategy, Common, Business, Application och Technology
- göra förmågekartor, värdeströmmar, integrationskartor eller migrationsplaner
- få all text på svenska med ArchiMate-liknande stereotyper

## Obligatoriska regler

Följ alltid dessa regler:

1. Använd alltid exakt ArchiMate 4-palett, aldrig ungefärliga färger.
2. Allt innehåll ska vara på svenska.
3. Elementtyp ska alltid visas i guillemets: `«typ»`.
4. Stereotypen ska alltid stå på egen rad ovanför elementnamnet. Undantag: i `gantt` och `timeline` ska stereotyp inte visas.
5. Domänordningen ska hållas: Motivation → Strategy → Common → Business → Application → Technology → Implementation & Migration.
6. Text på färgade noder ska vara mörk (`#000000` eller `#1a1a1a`), aldrig vit.
7. Strategy-domänen ska användas när förmågor, resurser, värdeströmmar eller handlingsplaner modelleras.
8. Common-domänen (ny i AM4) används för generiska beteendeelement (process, funktion, tjänst, händelse) som inte tillhör en specifik domän.
9. Physical Layer finns inte i AM4. Fysiska element (utrustning, anläggning, material) ingår i Technology-domänen.
10. Stereotypen ska vara **kursiv**. Om renderaren stöder markdown/html labels ska stereotypen också vara mindre och grå.

## Färgpalett

| Domän | Fyllning | Kontur | Typiska element |
|---|---|---|---|
| Motivation | `#D8C1E4` | `#B39BCF` | «intressent», «drivkraft», «bedömning», «mål», «princip», «krav», «värde» |
| Strategy | `#EFBD5D` | `#D4A43B` | «resurs», «förmåga», «värdeström», «handlingsplan» |
| Common | `#E8E5D3` | `#C4BFA6` | «process», «funktion», «tjänst», «händelse», «kollaboration», «roll», «interaktion» |
| Business | `#F4DE7F` | `#E8C555` | «aktör», «roll», «samarbete», «gränssnitt», «affärsobjekt», «produkt» |
| Application | `#B6D7E1` | `#8CC5D4` | «komponent», «samarbete», «gränssnitt», «dataobjekt» |
| Technology | `#C3E1B4` | `#9BD083` | «nod», «enhet», «systemprogramvara», «nätverk», «kommunikationsväg», «tjänst», «artefakt», «utrustning», «anläggning» |
| Implementation & Migration | `#F8C2BE` | `#F09B95` | «arbetspaket», «leverans», «platå» |

## Domäner i detalj

Varje domän med färg, ikon, syfte och typiska elementtyper.

### 💡 Motivationsdomän — `#D8C1E4` / `#B39BCF`

**Varför:** Representerar mål, drivkrafter, krav och principer som motiverar arkitekturbeslut. Placerad i centrum av AM4-hexagonen — all arkitektur börjar här.

Typiska element: «intressent», «drivkraft», «bedömning», «mål», «utfall», «princip», «krav», «betydelse», «värde».

### 🎯 Strategidomän — `#EFBD5D` / `#D4A43B`

**Hur:** Representerar förmågor, resurser och handlingsplaner. Bryggan mellan motivationen och den operativa verksamheten.

Typiska element: «resurs», «förmåga», «värdeström», «handlingsplan».

### 🔗 Common-domän — `#E8E5D3` / `#C4BFA6` *(ny i AM4)*

**Delade element:** Generiska beteendeelement som delas av alla domäner. I AM3.2 duplicerades dessa per lager (BusinessProcess, ApplicationProcess, TechnologyProcess). I AM4 finns de bara en gång i Common.

Typiska element: «process», «funktion», «tjänst», «händelse», «kollaboration», «roll», «interaktion», «stig».

### 💼 Verksamhetsdomän — `#F4DE7F` / `#E8C555`

**Affär:** Representerar aktörer, roller och värdeskapande mot kunder. Beteenden (process, tjänst) ärvs från Common-domänen.

Typiska element: «aktör», «roll», «samarbete», «gränssnitt», «affärsobjekt», «produkt».

### 💻 Applikationsdomän — `#B6D7E1` / `#8CC5D4`

**IT-stöd:** Representerar applikationskomponenter, gränssnitt och dataobjekt. Beteenden ärvs från Common-domänen.

Typiska element: «komponent», «samarbete», «gränssnitt», «dataobjekt».

### 🖥️ Teknologidomän — `#C3E1B4` / `#9BD083`

**Infrastruktur & OT:** Representerar IT-infrastruktur och fysisk operativ teknik. Physical Layer från AM3.2 är nu inbakat här — utrustning, anläggningar och material hör hit.

Typiska element: «nod», «enhet», «systemprogramvara», «samarbete», «gränssnitt», «nätverk», «kommunikationsväg», «tjänst», «artefakt», «utrustning», «anläggning», «material».

### 📋 Implementation & Migration — `#F8C2BE` / `#F09B95`

**Förändring:** Representerar program, projekt, arbetspaket, leveranser och platåer i en transformationsresa.

Typiska element: «arbetspaket», «leverans», «platå».

## Visuell standard för stereotyp-rad

Stereotyp-raden, till exempel `«process»`, ska följa denna målbild:

- kursiv stil
- mindre storlek än elementnamnet, cirka `0.75em`
- grå färg, helst `#555555`
- egen rad ovanför elementnamnet

## Viktigt om Mermaid-stöd

Mermaid kan hantera kursiv stil i etiketter när markdown strings eller HTML-baserade etiketter stöds av renderaren. Däremot är separat fontstorlek och separat färg för just första raden i samma nod inte alltid portabelt i ren Mermaid.

Därför gäller följande prioritet:

1. **Förstahandsval:** använd kursiv stereotyp via markdown eller HTML i nodetiketten.
2. **Andrahandsval:** använd radbrytning så att stereotypen åtminstone visas på egen rad ovanför namnet.
3. **När HTML/CSS runt Mermaid är möjligt:** använd CSS eller HTML-labels för att göra stereotypen mindre och grå.

## Rekommenderad Mermaid-init

Använd denna init när renderaren stöder moderna Mermaid-funktioner:

```text
%%{init: {
  "theme": "base",
  "securityLevel": "loose",
  "flowchart": { "htmlLabels": true },
  "themeVariables": {
    "fontFamily": "Inter, Segoe UI, Arial, sans-serif",
    "fontSize": "13px",
    "primaryTextColor": "#1a1a1a",
    "lineColor": "#666666"
  }
}}%%
```

## Standardblock för ArchiMate-klasser

Inkludera alltid detta block i Mermaid-diagram (AM4):

```text
classDef motivation     fill:#D8C1E4,stroke:#B39BCF,stroke-width:1px,color:#000000;
classDef strategy       fill:#EFBD5D,stroke:#D4A43B,stroke-width:1px,color:#000000;
classDef common         fill:#E8E5D3,stroke:#C4BFA6,stroke-width:1px,color:#000000;
classDef business       fill:#F4DE7F,stroke:#E8C555,stroke-width:1px,color:#000000;
classDef application    fill:#B6D7E1,stroke:#8CC5D4,stroke-width:1px,color:#000000;
classDef technology     fill:#C3E1B4,stroke:#9BD083,stroke-width:1px,color:#000000;
classDef implementation fill:#F8C2BE,stroke:#F09B95,stroke-width:1px,color:#000000;
```

## Diagramspecifika etikettregler

- I `flowchart` används i första hand HTML-etiketter med `<br/>` när HTML labels stöds.
- I `sequenceDiagram` ska HTML och stereotyp inte användas. Använd endast deltagarnamn utan `«typ»`, och använd gärna tema eller grupper för färgsättning.
- I `mindmap` kan `themeVariables` användas för ett enkelt ArchiMate 4-färgtema för hela diagrammet.
- I `gantt` och `timeline` ska `«typ»` eller annan stereotyp inte visas alls.
- Om en renderare inte stöder HTML-etiketter fullt ut, använd portabel fallback för berörda diagramtyper.

## Rekommenderat nodformat

### Variant A: Markdown-baserad kursiv stereotyp

Använd denna variant när Mermaid-renderaren stöder markdown strings i etiketter:

```mermaid
flowchart TD
    P["`*«process»*\nFakturahantering`"]:::common

    classDef common fill:#E8E5D3,stroke:#C4BFA6,stroke-width:1px,color:#1a1a1a;
```

### Variant B: HTML-baserad kursiv, mindre och grå stereotyp

Använd denna variant när renderaren stöder HTML labels:

```mermaid
%%{init: {"securityLevel": "loose", "flowchart": {"htmlLabels": true}} }%%
flowchart TD
    P["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«process»</span><br/>Fakturahantering"]:::common

    classDef common fill:#E8E5D3,stroke:#C4BFA6,stroke-width:1px,color:#1a1a1a;
```

### Variant C: Portabel fallback

Använd denna när du vill vara säker på bred kompatibilitet:

```mermaid
flowchart TD
    P["«process»\nFakturahantering"]:::common

    classDef common fill:#E8E5D3,stroke:#C4BFA6,stroke-width:1px,color:#1a1a1a;
```

## Renderingsregel

När du genererar Mermaid med denna skill ska du välja etikettformat i följande ordning:

1. HTML-variant om miljön uttryckligen stöder `htmlLabels` och `securityLevel: loose`.
2. Markdown-variant om miljön stöder markdown strings men inte HTML labels.
3. Portabel fallback i alla andra fall.

## Exempel 1: Domänstaplat flowchart

```mermaid
%%{init: {"theme": "base", "securityLevel": "loose", "flowchart": {"htmlLabels": true}} }%%
flowchart TB
    subgraph MOT["Motivationsdomän"]
        M1["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«mål»</span><br/>Minska kostnader"]:::motivation
    end

    subgraph STR["Strategidomän"]
        S1["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«förmåga»</span><br/>Automatisering"]:::strategy
    end

    subgraph COM["Common-domän"]
        Co1["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«process»</span><br/>Fakturahantering"]:::common
        Co2["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«tjänst»</span><br/>Leverantörsstöd"]:::common
    end

    subgraph APP["Applikationsdomän"]
        A1["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«komponent»</span><br/>ERP-system"]:::application
        A2["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«komponent»</span><br/>Fakturaportal"]:::application
    end

    subgraph TEC["Teknologidomän"]
        T1["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«nod»</span><br/>Applikationsserver"]:::technology
        T2["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«artefakt»</span><br/>Databas"]:::technology
    end

    M1 --> S1 --> Co1 --> A1 --> T1
    Co2 --> A2 --> T2

    classDef motivation     fill:#D8C1E4,stroke:#B39BCF,stroke-width:1px,color:#000000;
    classDef strategy       fill:#EFBD5D,stroke:#D4A43B,stroke-width:1px,color:#000000;
    classDef common         fill:#E8E5D3,stroke:#C4BFA6,stroke-width:1px,color:#000000;
    classDef application    fill:#B6D7E1,stroke:#8CC5D4,stroke-width:1px,color:#000000;
    classDef technology     fill:#C3E1B4,stroke:#9BD083,stroke-width:1px,color:#000000;
```

## Exempel 2: Förmågekarta

```mermaid
%%{init: {"theme": "base", "securityLevel": "loose", "flowchart": {"htmlLabels": true}} }%%
flowchart LR
    subgraph STR["Strategidomän"]
        C1["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«förmåga»</span><br/>Kunddialog"]:::strategy
        C2["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«förmåga»</span><br/>Dataanalys"]:::strategy
        C3["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«förmåga»</span><br/>Automatisering"]:::strategy
        C4["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«förmåga»</span><br/>Ärendehantering"]:::strategy
    end

    C1 --- C2
    C2 --- C3
    C3 --- C4

    classDef strategy fill:#EFBD5D,stroke:#D4A43B,stroke-width:1px,color:#000000;
```

## Exempel 3: Värdeström

```mermaid
%%{init: {"theme": "base", "securityLevel": "loose", "flowchart": {"htmlLabels": true}} }%%
flowchart LR
    V1["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«värdeström»</span><br/>Identifiera behov"]:::strategy -->
    V2["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«värdeström»</span><br/>Hantera beställning"]:::strategy -->
    V3["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«värdeström»</span><br/>Leverera tjänst"]:::strategy -->
    V4["<span style='color:#555555;font-size:0.75em;font-style:italic;'>«värdeström»</span><br/>Följa upp resultat"]:::strategy

    classDef strategy fill:#EFBD5D,stroke:#D4A43B,stroke-width:1px,color:#000000;
```

## Exempel 4: Sequence-diagram

I `sequenceDiagram` ska HTML och stereotyp inte användas. Visa i stället rena deltagarnamn.

### Exempel 4A: Sequence-diagram med tema

```mermaid
%%{init: {
  "theme": "base",
  "themeVariables": {
    "background": "transparent",
    "primaryColor": "#B6D7E1",
    "primaryBorderColor": "#8CC5D4",
    "primaryTextColor": "#1F1F1F",
    "lineColor": "#6B7280",
    "signalColor": "#6B7280",
    "signalTextColor": "#1F1F1F",
    "fontFamily": "Segoe UI, Arial, sans-serif"
  }
}}%%
sequenceDiagram
    participant Kund
    participant Beställning
    participant Portal
    participant APIplattform

    Kund->>Beställning: Initierar ärende
    Beställning->>Portal: Skickar beställning
    Portal->>APIplattform: Anropar tjänst
    APIplattform-->>Portal: Returnerar svar
    Portal-->>Beställning: Bekräftelse
    Beställning-->>Kund: Status
```

### Exempel 4B: Sequence-diagram med grupper

```mermaid
sequenceDiagram
    box rgba(244,222,127,0.35) Verksamhetsdomän
        participant Kund
        participant Beställning
    end

    box rgba(182,215,225,0.35) Applikationsdomän
        participant Portal
        participant APIplattform
    end

    Kund->>Beställning: Initierar ärende
    Beställning->>Portal: Skickar beställning
    Portal->>APIplattform: Anropar tjänst
    APIplattform-->>Portal: Returnerar svar
    Portal-->>Beställning: Bekräftelse
    Beställning-->>Kund: Status
```

## Exempel 5: Gantt för migration

I `gantt` ska stereotyp inte visas. Använd endast aktivitetsnamn och milstolpar.

```mermaid
gantt
    title Migreringsplan
    dateFormat YYYY-MM-DD

    section Förberedelse
    Analys :done, a1, 2026-01-01, 2026-02-01
    Design :active, a2, 2026-02-02, 2026-03-15

    section Införande
    Migrering :a3, 2026-03-16, 2026-05-01
    Driftsatt :milestone, p1, 2026-05-01, 1d
```

## Exempel 6: Mindmap

```mermaid
%%{init: {
  "theme": "base",
  "themeVariables": {
    "primaryColor": "#B6D7E1",
    "primaryBorderColor": "#8CC5D4",
    "primaryTextColor": "#1F1F1F",
    "lineColor": "#6B7280",
    "background": "transparent",
    "fontFamily": "Segoe UI, Arial, sans-serif"
  }
}}%%
mindmap
  root((Digitalisering))
    Verksamhet
      Kunddialog
      Ärendehantering
    Applikation
      Dataanalys
      Automatisering
    Teknologi
      Integrationsplattform
      Driftmiljö
    Styrning
      Principer
      Krav
```

## Exempel 7: Timeline

```mermaid
%%{init: {
  "theme": "base",
  "themeVariables": {
    "fontFamily": "Segoe UI, Arial, sans-serif",
    "primaryTextColor": "#1F1F1F",
    "lineColor": "#F09B95",
    "background": "transparent",
    "cScale0": "#F8C2BE",
    "cScaleLabel0": "#1F1F1F",
    "cScale1": "#F8C2BE",
    "cScaleLabel1": "#1F1F1F"
  }
}}%%
timeline
    title Transformationsresa
    2026 Q1 : Förstudie
    2026 Q2 : Upphandling
    2026 Q3 : Införande fas 1
    2026 Q4 : Stabil grundplattform
    2027 Q1 : Införande fas 2
```

## Exempel 8: Ishikawa / fiskbensdiagram

```mermaid
%%{init: {
  "theme": "base",
  "themeVariables": {
    "fontFamily": "Arial, Inter, Segoe UI, sans-serif",
    "primaryTextColor": "#1f1f1f",
    "lineColor": "#6b6b6b",
    "background": "transparent"
  }
}}%%
ishikawa-beta
  Lång svarstid i lösningen

    Applikation
      För många synkrona anrop
      Saknad caching
      Oeffektiv orkestrering

    Data
      Saknade index
      Stora resultatuppsättningar
      Hot tables vid peak

    Integration
      Hög API-latens
      Ingen retry-strategi
      För stora payloads

    Teknologi
      Underdimensionerad miljö
      Ingen autoskalning
      Nätverksflaskhalsar

    Process
      Ingen prestandatestning
      Otydliga NFR-krav
      Svag releasevalidering

    Styrning
      Oklart ägarskap
      Saknade arkitekturstyrningar
      Ingen uppföljning av rotorsaker
```

## Diagrammönster

### Förmågekarta

- använd Strategy-domänen
- använd `flowchart LR`
- gruppera närliggande förmågor i subgrafer
- använd helst HTML-variant för mindre grå kursiv stereotyp

### Systemlandskap

- använd Business, Application och Technology i vertikal ordning
- lägg gemensamma beteenden (process, tjänst) i Common-domänen
- använd subgrafer per domän
- koppla tjänster till komponenter och noder

### Migrationsplan

- använd Implementation & Migration för arbetspaket och platåer
- koppla gärna mot målarkitektur i Application- eller Technology-domänen

### Integrationsöversikt

- visa verksamhetstjänst (Business) över applikationskomponent (Application) över teknisk nod (Technology)
- använd tydlig uppifrån-och-ned-ordning

## HTML/CSS-komplement för dokumentation

```html
<div class="archimate-card archimate-card--common">
  <span class="archimate-stereotype">«process»</span>
  <span class="archimate-name">Fakturahantering</span>
</div>
```

```css
:root {
  --am-common: #E8E5D3;
  --am-common-stroke: #C4BFA6;
  --am-text: #1a1a1a;
  --am-stereotype: #555555;
}

.archimate-card {
  padding: 0.75rem 1rem;
  border-radius: 6px;
  border-left: 4px solid var(--am-common-stroke);
  background: var(--am-common);
  color: var(--am-text);
}

.archimate-stereotype {
  display: block;
  font-size: 0.75em;
  color: var(--am-stereotype);
  font-style: italic;
  line-height: 1.2;
  margin-bottom: 0.1rem;
}

.archimate-name {
  display: block;
  font-size: 1em;
  color: var(--am-text);
  font-weight: 600;
  line-height: 1.25;
}
```

## Genereringsinstruktioner

När denna skill används ska utdata:

- alltid välja svenska ArchiMate-termer
- alltid inkludera guillemets runt typen
- alltid sätta typen på egen rad ovanför namnet
- göra stereotypen kursiv där den används
- inte använda stereotyp i `sequenceDiagram`, `gantt` eller `timeline`
- använda Common-domänen (`#E8E5D3`) för generiska beteendeelement (process, funktion, tjänst, händelse)
- använda Technology-domänen (`#C3E1B4`) för fysiska element (utrustning, anläggning, material) — Physical Layer finns inte i AM4
- använda lagerfärger i `mindmap` baserat på respektive gren/domän
- alltid använda rätt klass för rätt domän
- alltid använda färger enligt tabellen ovan
- prioritera `flowchart` för klassiska ArchiMate-vyer
- kunna använda `sequenceDiagram`, `gantt`, `timeline`, `mindmap` och `ishikawa-beta` när syftet kräver det
- utgå från senaste Mermaid och tillåta beta-diagram där de är relevanta
- hålla `ishikawa-beta` enkelt när färgstöd eller detaljstyling är osäkert

## Referensinformation

- **Standard**: The Open Group ArchiMate 4, specifikation C260, april 2026
- **Färger**: ArchiMate 2025 Modern Color Set
- **Dokumentation**: [opengroup.org/archimate-forum](https://www.opengroup.org/archimate-forum)
- **Viktigaste AM4-förändring**: Physical Layer borttaget, Common-domän tillagd, "domäner" ersätter "lager"
