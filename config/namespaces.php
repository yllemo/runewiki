<?php
/**
 * config/namespaces.php
 *
 * Regler per namespace: åtkomst, tema-override, egen meny.
 * Nyckeln är namespace-sökvägen (samma som mappstrukturen i /content).
 * Namespace ärver inställningar från sina föräldrar om inget annat anges.
 *
 * 'acl' (public | login | private) styr LÄSRÄTTEN till namespacet — bara
 * verksamt när 'auth_enabled' är true i config.php (annars är allt öppet):
 *   - public  (standard): alla kan läsa, precis som idag.
 *   - login:  kräver inloggning (vilket konto som helst) för att läsa.
 *   - private: kräver inloggning OCH en grupp (config/acl.php) med
 *     uttrycklig läs- eller redigeringsrätt till just det här namespacet.
 * Redigeringsrätt kräver alltid en grupp med "edit"/"*" för namespacet
 * (config/acl.php), oavsett 'acl'-läge — se Wiki::canEditNamespace().
 * Namespaces utan egen 'acl'-rad ärver rotens (nyckeln '' nedan).
 *
 * Menyprioritet (se Wiki::menuFor()):
 *   1. content/_topbar.md — om den filen finns styr den ALLTID toppmenyn,
 *      site-wide. Den vinner över 'menu' här nedan.
 *   2. 'menu' på ett namespace nedan — används bara om _topbar.md saknas.
 *   3. config/menu.php — global fallback om varken _topbar.md eller ett
 *      namespace-specifikt 'menu' finns.
 *
 * Ta alltså bort/döp om content/_topbar.md om du vill att ett namespace
 * ska kunna visa en egen meny via 'menu' här.
 */

return [

    // Exempel: hela wikin (root) — standardinställningar
    '' => [
        'theme' => 'default',
        'acl'   => 'public',   // public | login | private
    ],

    // Exempel: eget tema, striktare åtkomst och egen meny för ett namespace
    // (menyn gäller bara när content/_topbar.md INTE finns — se ovan):
    // 'projekt' => [
    //     'theme' => 'default',
    //     'acl'   => 'login',
    //     'menu' => [
    //         ['label' => 'Projekt-start', 'target' => 'projekt:start'],
    //         ['label' => 'Tillbaka till start', 'target' => 'start'],
    //     ],
    // ],
];
