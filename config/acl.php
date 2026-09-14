<?php
/**
 * config/acl.php
 *
 * Grupper för finmaskig behörighet, kopplade in i core/Acl.php och
 * tillämpade i core/Wiki.php (Wiki::canReadNamespace()/canEditNamespace()).
 * Kombineras med namespace-nivåns 'acl'-läge (public/login/private) i
 * config/namespaces.php — se kommentaren där.
 *
 * Varje grupp är en lista rättighetssträngar:
 *   "*"                 — full åtkomst (läs + redigera) till ALLA namespaces
 *   "<namespace>"        — läs + redigera ETT namespace (samma som "<namespace>:*")
 *   "<namespace>:read"   — bara läsrätt till ett namespace
 *   "<namespace>:edit"   — läs- och redigeringsrätt till ett namespace
 *   "*:read" / "*:edit"  — samma rättighet men för alla namespaces
 *
 * <namespace> är den exakta mappen under /content (t.ex. "projekt"), inte
 * punktseparerade filnamnsdelar. Roten (sidor utan eget namespace, t.ex.
 * "start") har namespace "" (tom sträng) — skriv "" om du vill ge en
 * grupp rätt bara till rotsidorna.
 *
 * Vilka grupper en användare har sätts per konto (adminpanelen, fliken
 * "Användare", eller "php bin/cli.php set-groups <namn> <grupp1,grupp2>")
 * — inte här. Den här filen definierar bara VAD varje gruppnamn FÅR göra.
 * En användare utan egna grupper räknas som "editor" (samma rättigheter
 * alla inloggade hade innan ACL kopplades in), så befintliga konton
 * fortsätter fungera precis som förut efter en uppgradering.
 *
 * ACL gäller bara när 'auth_enabled' är true i config.php — annars är
 * hela wikin öppen för alla, som vanligt.
 */

return [
    'groups' => [
        'admin'  => ['*'],       // full åtkomst (läs + redigera) till allt
        'editor' => ['*:edit'],  // kan läsa och redigera alla namespaces (standard för konton utan egna grupper)
        'reader' => ['*:read'],  // kan bara läsa — ge den här gruppen till konton som ska in i namespaces med acl: private, men inte redigera

        // Exempel: en grupp som bara får redigera ETT namespace:
        // 'projekt-redaktor' => ['projekt:edit'],
    ],
];
