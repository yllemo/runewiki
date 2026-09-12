<!--
  content/_topbar.md — styr knapparna i den blå toppmenyn.

  Finns den här filen används den ISTÄLLET FÖR config/menu.php.
  Ta bort filen för att gå tillbaka till config/menu.php.

  Varje knapp är en rad i punktlistan nedan, i den ordning de ska visas:

    - [[namespace:sida]]                wiki-länk, etikett = sidans titel
    - [[namespace:sida|Egen etikett]]    wiki-länk med egen text på knappen
    - [Egen etikett](https://exempel.se) extern länk (eller absolut sökväg, t.ex. /media)

  Rader som inte matchar något av dessa (som denna kommentar, eller
  vanlig brödtext) ignoreras — filen renderas INTE som sidinnehåll
  någonstans, bara punktlistan tolkas.
-->

- [[start|Start]]
- [[hjalp:syntax|Hjälp]]
