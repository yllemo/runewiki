<?php
/**
 * config/acl.php
 *
 * FRAMTIDA finmaskig behörighet: grupper/rättigheter tänkta att kombineras
 * med en namespace-nivås 'acl' (public/login/private) i config/namespaces.php
 * — läses in men tillämpas INTE ännu (se README "Kända begränsningar").
 *
 * Det som FAKTISKT är kopplat idag är den enkla, globala växeln:
 *   - config.php:s 'auth_enabled' = false (standard): helt öppen wiki,
 *     alla kan läsa OCH redigera.
 *   - 'auth_enabled' = true: läsning är fortfarande öppet för alla, men
 *     redigering/spara/radera/media-uppladdning kräver inloggning
 *     (core/Auth.php::canEdit(), tillämpas i core/Wiki.php).
 *
 * Användarkonton för den enkla växeln ovan hanteras INTE här, utan i
 * data/users/users.php ('användarnamn' => password_hash(...)) — skapas
 * och tas bort med:
 *
 *   php bin/cli.php create-user <användarnamn>
 *   php bin/cli.php delete-user <användarnamn>
 *   php bin/cli.php list-users
 *
 * Alla användare som skapas där kan redigera allt (binärt: inloggad
 * eller inte). Grupperna nedan är ett skelett för en senare, mer
 * finmaskig behörighetsmodell (t.ex. "editor" får bara redigera vissa
 * namespaces) och har ingen effekt förrän den kopplas in.
 */

return [
    'groups' => [
        'admin'  => ['*'],           // full åtkomst till allt
        'editor' => ['content:*'],   // kan redigera allt innehåll
        'reader' => ['content:read'],
    ],
];
