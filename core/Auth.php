<?php
/**
 * core/Auth.php
 *
 * Valfri, enkel filbaserad autentisering (aktiveras via
 * config('auth_enabled')). Användare lagras i data/users/users.php.
 * Ingen databas.
 *
 * Varje användarpost är antingen:
 *   - en sträng (ÄLDRE, fortfarande läsbar form): bara lösenordet.
 *     Grupplöshet tolkas som gruppen "editor" (se groupsFor()) så
 *     befintliga konton behåller sina rättigheter oförändrade.
 *   - en array ['password' => ..., 'groups' => ['editor', ...]] — den
 *     form allt sparas som från och med att kontot rörs (lösenordsbyte,
 *     gruppändring m.m.), se normalizeRecord()/saveUsers().
 *
 * Lösenordet kan vara antingen en riktig bcrypt-hash (från password_hash(),
 * känns igen på $2a$/$2b$/$2y$-prefixet) ELLER klartext. Klartext stöds som
 * en snabb startpunkt på miljöer utan PHP CLI-åtkomst (t.ex. OpenShift) —
 * själva webbservern kan fortfarande hasha lösenord via password_hash()
 * (det kräver ingen CLI, bara en vanlig PHP-request), så byt till hash så
 * snart det går: antingen "php bin/cli.php create-user <namn>" (om du får
 * CLI-åtkomst) eller självbetjäning på /?do=account (kräver ingen CLI alls).
 *
 * Grupper (vilka namespaces ett konto får läsa/redigera) avgörs av
 * config/acl.php + core/Acl.php — den här klassen känner bara till VILKA
 * grupper ett konto har (groupsFor()/setUserGroups()), inte vad grupperna
 * får göra.
 *
 * Avstängt som standard — helt öppen wiki.
 */

class Auth
{
    /** Cookiens/sessionens livslängd i sekunder — se $sessionLifetimeDays. */
    private int $sessionLifetime;

    /**
     * @param int $sessionLifetimeDays Hur länge en inloggning ska hålla i
     *   sig utan ny inloggning (config.php:s 'session_lifetime_days',
     *   standard 30). Glidande fönster: cookien förnyas vid varje request
     *   från en inloggad användare, se refreshSessionCookie().
     */
    public function __construct(private string $usersFile, private bool $enabled, int $sessionLifetimeDays = 30)
    {
        $this->sessionLifetime = max(1, $sessionLifetimeDays) * 86400;

        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            // Servern måste hålla sessionsfilen vid liv minst lika länge
            // som cookien påstår sig gälla, annars loggas man ut i förtid
            // trots att webbläsarens cookie fortfarande finns kvar (PHPs
            // garbage collection kör annars på session.gc_maxlifetime,
            // som ofta bara är ~24 minuter i standard-php.ini).
            ini_set('session.gc_maxlifetime', (string) $this->sessionLifetime);

            // Säkra session-cookie-flaggor innan session startas
            session_set_cookie_params([
                'lifetime' => $this->sessionLifetime,
                'httponly' => true,
                'samesite' => 'Lax',
                // 'secure' => true, // aktivera om HTTPS används
            ]);
            session_start();

            $this->refreshSessionCookie();
        }
    }

    /**
     * Skjuter fram cookiens utgångstid vid varje request från en redan
     * inloggad användare — annars sätts utgångstiden bara EN gång (vid
     * session_start()) och man loggas ut $sessionLifetimeDays efter första
     * inloggningen oavsett hur ofta man faktiskt besöker wikin emellanåt.
     */
    private function refreshSessionCookie(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || $this->currentUser() === null) {
            return;
        }
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $this->sessionLifetime,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** Känns värdet igen som en bcrypt-hash (från password_hash()) eller är det klartext? */
    private static function isHashed(string $value): bool
    {
        return (bool) preg_match('/^\$2[abxy]\$/', $value);
    }

    /** Verifierar $password mot lagrat värde — hash (password_verify) eller klartext (tidssäker jämförelse). */
    private static function verify(string $password, string $stored): bool
    {
        return self::isHashed($stored)
            ? password_verify($password, $stored)
            : hash_equals($stored, $password);
    }

    /**
     * Normaliserar en rå användarpost (äldre platt sträng ELLER ny array,
     * se klasskommentaren) till ['password' => sträng, 'groups' => lista].
     *
     * Ett konto som ALDRIG fått egna grupper (äldre strängpost, eller en
     * array som helt saknar 'groups'-nyckeln) räknas som "editor" — samma
     * rättigheter alla inloggade hade innan grupper fanns, så en
     * uppgradering inte plötsligt låser ute befintliga redaktörer.
     *
     * OBS: skiljer avsiktligt på "saknar 'groups'-nyckeln" (→ "editor")
     * och "'groups' är en TOM lista" (→ inga rättigheter alls). Det senare
     * händer om en admin bockar ur alla grupper för ett konto i
     * adminpanelen — det ska faktiskt ta bort åtkomsten, inte tyst falla
     * tillbaka till "editor" igen (setUserGroups() sparar alltid en
     * 'groups'-nyckel, tom eller ej, så den skillnaden går att göra här).
     */
    private static function normalizeRecord(mixed $record): array
    {
        if (is_array($record) && array_key_exists('groups', $record)) {
            return [
                'password' => (string) ($record['password'] ?? ''),
                'groups'   => array_values(array_filter((array) $record['groups'], fn ($g) => is_string($g) && $g !== '')),
            ];
        }
        if (is_array($record)) {
            return ['password' => (string) ($record['password'] ?? ''), 'groups' => ['editor']];
        }
        return ['password' => (string) $record, 'groups' => ['editor']];
    }

    public function login(string $user, string $password): bool
    {
        $users = $this->loadUsers();
        if (!isset($users[$user])) {
            return false;
        }
        $record = self::normalizeRecord($users[$user]);
        if (!self::verify($password, $record['password'])) {
            return false;
        }
        $_SESSION['runewiki_user'] = $user;
        $this->refreshSessionCookie();
        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['runewiki_user']);
    }

    public function currentUser(): ?string
    {
        return $_SESSION['runewiki_user'] ?? null;
    }

    /**
     * Grovt: är man inloggad alls (eller är auth avstängt)? Styr t.ex.
     * om redigeringsknappar/formulär visas överhuvudtaget. Själva VILKA
     * namespaces man får redigera avgörs av Wiki::canEditNamespace()
     * (config/acl.php via groupsFor() + core/Acl.php), inte här.
     */
    public function canEdit(): bool
    {
        return !$this->enabled || $this->currentUser() !== null;
    }

    /** Grupperna $user tillhör (config/acl.php:s nycklar) — tomt om användaren inte finns. */
    public function groupsFor(string $user): array
    {
        $users = $this->loadUsers();
        if (!isset($users[$user])) {
            return [];
        }
        return self::normalizeRecord($users[$user])['groups'];
    }

    /** Grupperna för den inloggade användaren, eller en tom lista om ingen är inloggad. */
    public function currentUserGroups(): array
    {
        $user = $this->currentUser();
        return $user !== null ? $this->groupsFor($user) : [];
    }

    /** Sätter $user:s grupper (skriver över befintliga). False om användaren inte finns. */
    public function setUserGroups(string $user, array $groups): bool
    {
        $users = $this->loadUsers();
        if (!isset($users[$user])) {
            return false;
        }
        $record = self::normalizeRecord($users[$user]);
        $record['groups'] = array_values(array_unique(array_filter($groups, fn ($g) => is_string($g) && $g !== '')));
        $users[$user] = $record;
        return $this->saveUsers($users);
    }

    /**
     * Byter $user:s eget lösenord — kräver rätt nuvarande lösenord (hash
     * eller klartext, se verify()) och sparar det nya alltid som en riktig
     * bcrypt-hash. Detta är vägen ut ur klartextläget utan CLI-åtkomst:
     * en vanlig POST-request kör password_hash() i webbserverns egen
     * PHP-process, ingen kommandorad behövs. Används av /?do=account.
     */
    public function changeOwnPassword(string $user, string $currentPassword, string $newPassword): bool
    {
        $users = $this->loadUsers();
        if (!isset($users[$user])) {
            return false;
        }
        $record = self::normalizeRecord($users[$user]);
        if (!self::verify($currentPassword, $record['password'])) {
            return false;
        }
        $record['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $users[$user] = $record;
        return $this->saveUsers($users);
    }

    /**
     * Sätter (skapar eller skriver över) ett lösenord utan att kontrollera
     * ett gammalt — för betrodda administrationsvägar (bin/cli.php
     * create-user, som redan kräver filsystemsåtkomst till servern).
     * Sparas alltid som en riktig bcrypt-hash. Ändrar INTE ett befintligt
     * kontos grupper (bara lösenordet) — nya konton får "editor" som
     * standard (se normalizeRecord()), tilldela andra grupper separat via
     * setUserGroups().
     */
    public function setPassword(string $user, string $newPassword): bool
    {
        $users = $this->loadUsers();
        $record = self::normalizeRecord($users[$user] ?? null);
        $record['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $users[$user] = $record;
        return $this->saveUsers($users);
    }

    /** Tar bort en användare. True om den fanns och togs bort. */
    public function deleteUser(string $user): bool
    {
        $users = $this->loadUsers();
        if (!isset($users[$user])) {
            return false;
        }
        unset($users[$user]);
        return $this->saveUsers($users);
    }

    /** Alla användarnamn (utan lösenord/hash) — för list-users i CLI:t. */
    public function listUsernames(): array
    {
        return array_keys($this->loadUsers());
    }

    /** True om $user finns, oavsett om lösenordet är hashat eller klartext. */
    public function userExists(string $user): bool
    {
        return isset($this->loadUsers()[$user]);
    }

    /** True om $user:s lösenord fortfarande ligger som klartext (inte hashat än). */
    public function hasPlaintextPassword(string $user): bool
    {
        $users = $this->loadUsers();
        return isset($users[$user]) && !self::isHashed(self::normalizeRecord($users[$user])['password']);
    }

    private function loadUsers(): array
    {
        return Helpers::loadConfig($this->usersFile, []);
    }

    /** Skriver hela användarfilen — samma genererade format oavsett anropskälla (CLI eller webb). */
    private function saveUsers(array $users): bool
    {
        $dir = dirname($this->usersFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $php = "<?php\n/**\n * data/users/users.php\n *\n"
            . " * 'användarnamn' => ['password' => hash-eller-klartext, 'groups' => [...]].\n"
            . " * Lösenordet är antingen en bcrypt-hash (från password_hash(),\n"
            . " * \$2y\$... e.dyl.) eller klartext som en snabb startpunkt utan\n"
            . " * CLI-åtkomst. Byt till hash via /?do=account (kräver ingen CLI)\n"
            . " * eller \"php bin/cli.php create-user\" om du har shell-åtkomst.\n"
            . " * 'groups' avgör vilka namespaces kontot får läsa/redigera, se\n"
            . " * config/acl.php — sätts via adminpanelen (fliken \"Användare\")\n"
            . " * eller \"php bin/cli.php set-groups\". Rör inte filen för hand.\n"
            . " */\nreturn " . var_export($users, true) . ";\n";
        return file_put_contents($this->usersFile, $php, LOCK_EX) !== false;
    }
}
