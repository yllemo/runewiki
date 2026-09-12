<?php
/**
 * core/Auth.php
 *
 * Valfri, enkel filbaserad autentisering (aktiveras via
 * config('auth_enabled')). Användare lagras i data/users/users.php som
 * ['användarnamn' => lösenord]. Ingen databas.
 *
 * Lösenordet kan vara antingen en riktig bcrypt-hash (från password_hash(),
 * känns igen på $2a$/$2b$/$2y$-prefixet) ELLER klartext. Klartext stöds som
 * en snabb startpunkt på miljöer utan PHP CLI-åtkomst (t.ex. OpenShift) —
 * själva webbservern kan fortfarande hasha lösenord via password_hash()
 * (det kräver ingen CLI, bara en vanlig PHP-request), så byt till hash så
 * snart det går: antingen "php bin/cli.php create-user <namn>" (om du får
 * CLI-åtkomst) eller självbetjäning på /?do=account (kräver ingen CLI alls).
 *
 * Avstängt som standard — helt öppen wiki.
 */

class Auth
{
    public function __construct(private string $usersFile, private bool $enabled)
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            // Säkra session-cookie-flaggor innan session startas
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                // 'secure' => true, // aktivera om HTTPS används
            ]);
            session_start();
        }
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

    public function login(string $user, string $password): bool
    {
        $users = $this->loadUsers();
        if (!isset($users[$user]) || !self::verify($password, (string) $users[$user])) {
            return false;
        }
        $_SESSION['runewiki_user'] = $user;
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

    /** Alla inloggade får redigera. ACL per namespace kan byggas ut senare. */
    public function canEdit(): bool
    {
        return !$this->enabled || $this->currentUser() !== null;
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
        if (!isset($users[$user]) || !self::verify($currentPassword, (string) $users[$user])) {
            return false;
        }
        $users[$user] = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->saveUsers($users);
    }

    /**
     * Sätter (skapar eller skriver över) ett lösenord utan att kontrollera
     * ett gammalt — för betrodda administrationsvägar (bin/cli.php
     * create-user, som redan kräver filsystemsåtkomst till servern).
     * Sparas alltid som en riktig bcrypt-hash.
     */
    public function setPassword(string $user, string $newPassword): bool
    {
        $users = $this->loadUsers();
        $users[$user] = password_hash($newPassword, PASSWORD_DEFAULT);
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
        return isset($users[$user]) && !self::isHashed((string) $users[$user]);
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
            . " * 'användarnamn' => lösenord — antingen en bcrypt-hash (från\n"
            . " * password_hash(), \$2y\$... e.dyl.) eller klartext som en snabb\n"
            . " * startpunkt utan CLI-åtkomst. Byt till hash via /?do=account\n"
            . " * (kräver ingen CLI) eller \"php bin/cli.php create-user\" om du\n"
            . " * har shell-åtkomst. Rör inte hasharna för hand.\n"
            . " */\nreturn " . var_export($users, true) . ";\n";
        return file_put_contents($this->usersFile, $php, LOCK_EX) !== false;
    }
}
