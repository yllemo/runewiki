# Byråkrazy

Byråkrazy är aktiverat i `config/plugins.php`. Lägg formuläret på en vanlig Markdown-sida. Inlämning kräver samma redigeringsrätt till målsidans namespace som den vanliga editorn. Formulären skyddas med CSRF-token.

## Lägg till på en befintlig sida

```text
<form>
action pagemod start add_down
fieldset "New item"
textbox "Item"
fieldset ""
submit "Add"
</form>

<pagemod add_down output_after>
* @@Item@@
</pagemod>
```

`action pagemod MÅLSIDA REGEL` ändrar en befintlig sida. Använd `.` som målsida för sidan med formuläret. Regeln definieras i ett `<pagemod NAMN output_after>` eller `<pagemod NAMN output_before>`-block på samma sida. Regeln visas inte för läsaren. `output_after` lägger text sist i brödtexten; `output_before` lägger den först i brödtexten, efter eventuell YAML-frontmatter. Båda sätter en tom rad mellan det nya och befintliga innehållet.

För att lägga till en punkt högst upp byter du regelns öppningsrad till `<pagemod add_down output_before>`. Formulärets `action pagemod start add_down` är oförändrad.

## Skapa sida från mall

Skapa exempelvis sidan `mallar:artikel`:

```text
---
tags: [@@Taggar@@]
---

# @@Rubrik@@

@@Text@@
```

Lägg sedan detta på en formulärsida:

```text
<form>
action template mallar:artikel artiklar:@@Rubrik@@
textbox "Rubrik"
textarea "Text"
hidden "Taggar" "wiki, nyhet"
submit "Skapa"
</form>
```

`action template MALLSIDA NY_SIDA` skapar en ny `.md`-sida. `@@Fältnamn@@` ersätts med det inskickade värdet både i mallen och målsidans ID. En befintlig sida skrivs aldrig över. Sid-ID normaliseras på samma sätt som i resten av RuneWiki.

Fälttyper: `textbox "Namn"`, `textarea "Namn"`, `checkbox "Namn"`, `select "Namn" Val1|Val2` och `hidden "Namn" "värde"`. Ett dolt fält visas inte i formuläret. Värdet hämtas från formulärdefinitionen vid inlämning, så ett ändrat HTTP-anrop kan inte byta ut det. Exemplet ovan skapar frontmatter-raden `tags: [wiki, nyhet]`.

`fieldset "Rubrik"` grupperar efterföljande fält, `fieldset ""` avslutar gruppen och `submit "Text"` anger knappens text.

## Fler fälttyper och regler

```text
<form>
action template mallar:anstalld personal:@@Employee Name@@
fieldset "A set of fields"
textbox "Employee Name" "=Your Name"
number "Your Age" >13 <99
email "Your E-Mail Address"
textbox "Occupation (optional)" !
password "Some password"
fieldset ""
submit "Skapa"
</form>
```

`"=Your Name"` sätter ett förifyllt värde. `!` gör fältet frivilligt. För `number` betyder `>13 <99` att värdet måste vara större än 13 och mindre än 99. `email` kontrollerar adressens format. `password` döljer inmatningen på skärmen men värdet blir vanlig text om det sätts in i en Markdown-sida. Använd det därför inte för hemliga lösenord.

Formulärdefinitioner läses om från sidan vid inlämning. Fältvärden kan inte välja en annan mall eller regel än den som sidan anger. Sidmallar och målsidor måste vara läsbara för besökaren, och målsidan måste vara redigerbar.
