---
name: ArchiMate 4 – Mermaid
description: Mermaid-diagram enligt ArchiMate 4 (C260) med Modern Color Set och fetstilt nodformat.
icon: 🧩
keywords: [archimate, archimate 4, mermaid, enterprise architecture, verksamhetsarkitektur, förmågekarta, värdeström, migrationsplan, systemlandskap]
author: The Open Group C260 / svensk Mermaid-anpassning
version: "4.0"
applyTo: ["**/*.md", "**/*.mermaid", "**/*.mmd", "**/*.html"]
tools: [mermaid, diagrams, markdown]
domains: [enterprise-architecture, business-modeling, system-design, documentation]
---

# ArchiMate 4 (C260) Modern Color Set för Mermaid (svenska)

Denna skill används för att generera Mermaid-diagram som följer ArchiMate 4 (C260) Modern Color Set med svenska termer, fasta domänfärger och konsekvent notation. Elementnamnet skrivs i **fetstil** och stereotypen i guillemets `«typ»` på egen rad ovanför namnet.

## Syfte

Använd denna skill när användaren vill:

- skapa ArchiMate-diagram i Mermaid
- få korrekta ArchiMate 4-färger (Modern Color Set)
- visualisera domäner som Motivation, Strategy, Common, Business, Application och Technology
- göra förmågekartor, värdeströmmar, integrationskartor eller migrationsplaner
- få all text på svenska med ArchiMate-liknande stereotyper och fetstilt elementnamn

## Obligatoriska regler

Följ alltid dessa regler:

1. Använd alltid exakt ArchiMate 4-palett, aldrig ungefärliga färger.
2. Allt innehåll ska vara på svenska.
3. Elementtyp ska alltid visas i guillemets: `«typ»`.
4. Stereotypen ska alltid stå på egen rad ovanför elementnamnet (via `<br/>`). Undantag: i `sequenceDiagram`, `gantt` och `timeline` ska stereotyp inte visas.
5. **Elementnamnet ska alltid vara i fetstil** med `<b>…</b>`. En valfri beskrivande tredje rad kan läggas till inom parentes utan fetstil.
6. Domänordningen ska hållas: Motivation → Strategy → Common → Business → Application → Technology → Implementation & Migration.
7. Text på färgade noder ska vara mörk (`#000000` eller `#1a1a1a`), aldrig vit.
8. Strategy-domänen ska användas när förmågor, resurser, värdeströmmar eller handlingsplaner modelleras.
9. Common-domänen (ny i AM4) används för generiska beteendeelement (process, funktion, tjänst, händelse) som inte tillhör en specifik domän.
10. Physical Layer finns inte i AM4. Fysiska element (utrustning, anläggning, material) ingår i Technology-domänen.

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

## Nodformat

### Primärt format (fetstilt namn — förstahandsval)

Elementnamnet skrivs i fetstil med `<b>`, stereotypen på egen rad ovanför med `<br/>`. Kräver att renderaren stöder HTML-etiketter (`htmlLabels: true`, `securityLevel: loose`).

```text
X["«typ»<br/><b>Elementnamn</b>"]:::klass
```

Med en valfri beskrivande tredje rad (utan fetstil):

```text
X["«typ»<br/><b>Elementnamn</b><br/>(kort beskrivning)"]:::klass
```

### Portabel fallback

Om renderaren inte stöder HTML-etiketter, använd radbrytning så att stereotypen åtminstone hamnar på egen rad. Fetstil utgår då:

```text
X["«typ»\nElementnamn"]:::klass
```

### Renderingsregel

Välj nodformat i denna ordning:

1. **Fetstilt HTML-format** när miljön stöder `htmlLabels: true` och `securityLevel: loose` — detta är standard.
2. **Portabel fallback** i alla andra fall.

## Rekommenderad Mermaid-init

Använd denna init så att fetstil och radbrytning fungerar:

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
classDef motivation     fill:#D8C1E4,stroke:#B39BCF,stroke-width:1px,color:#000;
classDef strategy       fill:#EFBD5D,stroke:#D4A43B,stroke-width:1px,color:#000;
classDef common         fill:#E8E5D3,stroke:#C4BFA6,stroke-width:1px,color:#000;
classDef business       fill:#F4DE7F,stroke:#E8C555,stroke-width:1px,color:#000;
classDef application    fill:#B6D7E1,stroke:#8CC5D4,stroke-width:1px,color:#000;
classDef technology     fill:#C3E1B4,stroke:#9BD083,stroke-width:1px,color:#000;
classDef implementation fill:#F8C2BE,stroke:#F09B95,stroke-width:1px,color:#000;
```

## Diagramspecifika etikettregler

- I `flowchart` används alltid fetstilt HTML-format med `«typ»<br/><b>Namn</b>`.
- I `sequenceDiagram` ska HTML och stereotyp inte användas. Använd endast deltagarnamn utan `«typ»`, och använd gärna tema eller `box`-grupper för domänfärgning.
- I `mindmap` kan `themeVariables` användas för ett enkelt ArchiMate 4-färgtema för hela diagrammet.
- I `gantt` och `timeline` ska `«typ»` eller annan stereotyp inte visas alls.
- Om en renderare inte stöder HTML-etiketter fullt ut, använd portabel fallback för berörda diagramtyper.

## Exempel 1: Domänöversikt (sju domäner)

```mermaid
%% ArchiMate 4 — Domänöversikt (sju domäner)
%%{init: {"theme": "base", "securityLevel": "loose", "flowchart": {"htmlLabels": true}} }%%
flowchart TD
    M["«motivation»<br/><b>Motivationsdomän</b><br/>(Varför)"]:::motivation
    S["«strategi»<br/><b>Strategidomän</b><br/>(Hur vi skapar värde)"]:::strategy
    Co["«common»<br/><b>Common-domän</b><br/>(Delade beteendeelement)"]:::common
    V["«verksamhet»<br/><b>Verksamhetsdomän</b><br/>(Affär & organisation)"]:::business
    A["«applikation»<br/><b>Applikationsdomän</b><br/>(IT-stöd)"]:::application
    T["«teknologi»<br/><b>Teknologidomän</b><br/>(Infrastruktur & OT)"]:::technology
    I["«implementering»<br/><b>Implementering & Migration</b><br/>(Förändring)"]:::implementation

    M --> S
    S --> Co
    Co --> V
    Co --> A
    Co --> T
    V & A & T --> I

    V --> BO["«affärsobjekt»<br/><b>Kundärende</b>"]:::business
    A --> DO["«dataobjekt»<br/><b>Ärendedata</b>"]:::application
    BO -. realiseras av .-> DO

    classDef motivation     fill:#D8C1E4,stroke:#B39BCF,stroke-width:1px,color:#000;
    classDef strategy       fill:#EFBD5D,stroke:#D4A43B,stroke-width:1px,color:#000;
    classDef common         fill:#E8E5D3,stroke:#C4BFA6,stroke-width:1px,color:#000;
    classDef business       fill:#F4DE7F,stroke:#E8C555,stroke-width:1px,color:#000;
    classDef application    fill:#B6D7E1,stroke:#8CC5D4,stroke-width:1px,color:#000;
    classDef technology     fill:#C3E1B4,stroke:#9BD083,stroke-width:1px,color:#000;
    classDef implementation fill:#F8C2BE,stroke:#F09B95,stroke-width:1px,color:#000;
```

## Exempel 2: Domänstaplat flowchart

```mermaid
%%{init: {"theme": "base", "securityLevel": "loose", "flowchart": {"htmlLabels": true}} }%%
flowchart TB
    subgraph MOT["Motivationsdomän"]
        M1["«mål»<br/><b>Minska kostnader</b>"]:::motivation
    end

    subgraph STR["Strategidomän"]
        S1["«förmåga»<br/><b>Automatisering</b>"]:::strategy
    end

    subgraph COM["Common-domän"]
        Co1["«process»<br/><b>Fakturahantering</b>"]:::common
        Co2["«tjänst»<br/><b>Leverantörsstöd</b>"]:::common
    end

    subgraph APP["Applikationsdomän"]
        A1["«komponent»<br/><b>ERP-system</b>"]:::application
        A2["«komponent»<br/><b>Fakturaportal</b>"]:::application
    end

    subgraph TEC["Teknologidomän"]
        T1["«nod»<br/><b>Applikationsserver</b>"]:::technology
        T2["«artefakt»<br/><b>Databas</b>"]:::technology
    end

    M1 --> S1 --> Co1 --> A1 --> T1
    Co2 --> A2 --> T2

    classDef motivation  fill:#D8C1E4,stroke:#B39BCF,stroke-width:1px,color:#000;
    classDef strategy    fill:#EFBD5D,stroke:#D4A43B,stroke-width:1px,color:#000;
    classDef common      fill:#E8E5D3,stroke:#C4BFA6,stroke-width:1px,color:#000;
    classDef application fill:#B6D7E1,stroke:#8CC5D4,stroke-width:1px,color:#000;
    classDef technology  fill:#C3E1B4,stroke:#9BD083,stroke-width:1px,color:#000;
```

## Exempel 3: Förmågekarta

```mermaid
%%{init: {"theme": "base", "securityLevel": "loose", "flowchart": {"htmlLabels": true}} }%%
flowchart LR
    subgraph STR["Strategidomän"]
        C1["«förmåga»<br/><b>Kunddialog</b>"]:::strategy
        C2["«förmåga»<br/><b>Dataanalys</b>"]:::strategy
        C3["«förmåga»<br/><b>Automatisering</b>"]:::strategy
        C4["«förmåga»<br/><b>Ärendehantering</b>"]:::strategy
    end

    C1 --- C2 --- C3 --- C4

    classDef strategy fill:#EFBD5D,stroke:#D4A43B,stroke-width:1px,color:#000;
```

## Exempel 4: Värdeström

```mermaid
%%{init: {"theme": "base", "securityLevel": "loose", "flowchart": {"htmlLabels": true}} }%%
flowchart LR
    V1["«värdeström»<br/><b>Identifiera behov</b>"]:::strategy -->
    V2["«värdeström»<br/><b>Hantera beställning</b>"]:::strategy -->
    V3["«värdeström»<br/><b>Leverera tjänst</b>"]:::strategy -->
    V4["«värdeström»<br/><b>Följa upp resultat</b>"]:::strategy

    classDef strategy fill:#EFBD5D,stroke:#D4A43B,stroke-width:1px,color:#000;
```

## Exempel 5: Systemlandskap

```mermaid
%%{init: {"theme": "base", "securityLevel": "loose", "flowchart": {"htmlLabels": true}} }%%
flowchart TB
    subgraph BUS["Verksamhetsdomän"]
        B1["«affärstjänst»<br/><b>Ärendehantering</b>"]:::business
    end
    subgraph COM["Common-domän"]
        Co1["«process»<br/><b>Handlägg ärende</b>"]:::common
    end
    subgraph APP["Applikationsdomän"]
        A1["«komponent»<br/><b>Ärendesystem</b>"]:::application
        A2["«dataobjekt»<br/><b>Ärendedata</b>"]:::application
    end
    subgraph TEC["Teknologidomän"]
        T1["«nod»<br/><b>Applikationsserver</b>"]:::technology
    end

    B1 --> Co1 --> A1
    A1 --> A2
    A1 --> T1

    classDef business    fill:#F4DE7F,stroke:#E8C555,stroke-width:1px,color:#000;
    classDef common      fill:#E8E5D3,stroke:#C4BFA6,stroke-width:1px,color:#000;
    classDef application fill:#B6D7E1,stroke:#8CC5D4,stroke-width:1px,color:#000;
    classDef technology  fill:#C3E1B4,stroke:#9BD083,stroke-width:1px,color:#000;
```

## Exempel 6: Migrationsplan (gantt)

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

## Exempel 7: Sequence-diagram

I `sequenceDiagram` ska HTML och stereotyp inte användas. Visa i stället rena deltagarnamn, och färga gärna med `box`-grupper.

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

## Exempel 8: Mindmap

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

## Diagrammönster

### Förmågekarta

- använd Strategy-domänen
- använd `flowchart LR`
- gruppera närliggande förmågor i subgrafer
- fetstilt namn med `«förmåga»` som stereotyp

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

## Genereringsinstruktioner

När denna skill används ska utdata:

- alltid välja svenska ArchiMate-termer
- alltid inkludera guillemets runt typen: `«typ»`
- alltid sätta typen på egen rad ovanför namnet via `<br/>`
- **alltid skriva elementnamnet i fetstil med `<b>…</b>`**
- använda en valfri beskrivande tredje rad inom parentes utan fetstil
- inte använda stereotyp i `sequenceDiagram`, `gantt` eller `timeline`
- använda Common-domänen (`#E8E5D3`) för generiska beteendeelement (process, funktion, tjänst, händelse)
- använda Technology-domänen (`#C3E1B4`) för fysiska element (utrustning, anläggning, material) — Physical Layer finns inte i AM4
- alltid använda rätt klass för rätt domän och rätt färg enligt tabellen
- inkludera `securityLevel: loose` och `htmlLabels: true` i init så att fetstil och radbrytning fungerar
- prioritera `flowchart` för klassiska ArchiMate-vyer, men kunna använda `sequenceDiagram`, `gantt`, `timeline` och `mindmap` när syftet kräver det

## Referensinformation

- **Standard**: The Open Group ArchiMate 4, specifikation C260, april 2026
- **Färger**: ArchiMate 2025 Modern Color Set
- **Dokumentation**: [opengroup.org/archimate-forum](https://www.opengroup.org/archimate-forum)
- **Viktigaste AM4-förändring**: Physical Layer borttaget, Common-domän tillagd, "domäner" ersätter "lager"

