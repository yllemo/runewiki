<?php
/** Personal, read-only API credentials. Never stores the bearer secret. */
class McpKeys
{
    private const PREFIX = "<?php http_response_code(404); exit; ?>\n";

    public function __construct(private string $directory, private Auth $auth) {}

    private function selector(string $user): string
    {
        return substr(hash('sha256', $user), 0, 32);
    }

    private function read(string $selector): ?array
    {
        $path = $this->directory . '/' . $selector . '.php';
        if (!is_file($path)) return null;
        $raw = file_get_contents($path);
        if ($raw === false || !str_starts_with($raw, self::PREFIX)) return null;
        $record = json_decode(substr($raw, strlen(self::PREFIX)), true);
        return is_array($record) ? $record : null;
    }

    public function issue(string $user, bool $includeIfAuth = false, int $days = 90): string
    {
        $fingerprint = $this->auth->credentialFingerprint($user);
        if ($fingerprint === null || $days < 1 || $days > 365) throw new InvalidArgumentException('Ogiltigt konto eller giltighetstid.');
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Kunde inte skapa nyckelkatalogen.');
        }
        $selector = $this->selector($user);
        $key = 'rw_mcp_' . $selector . '.' . bin2hex(random_bytes(32));
        $record = ['user' => $user, 'hash' => hash('sha256', $key), 'account' => $fingerprint,
            'created' => time(), 'expires' => time() + $days * 86400, 'ifAuth' => $includeIfAuth];
        $this->mutate($selector, $record);
        return $key;
    }

    private function mutate(string $selector, ?array $record): void
    {
        if (!is_dir($this->directory)) return;
        $lock = fopen($this->directory . '/.lock', 'c');
        if (!$lock) throw new RuntimeException('Kunde inte låsa nyckellagret.');
        $temp = null;
        try {
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('Kunde inte låsa nyckellagret.');
            $path = $this->directory . '/' . $selector . '.php';
            if ($record === null) {
                if (is_file($path) && !unlink($path)) throw new RuntimeException('Kunde inte återkalla nyckeln.');
            } else {
                $temp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
                $data = self::PREFIX . json_encode($record, JSON_THROW_ON_ERROR);
                if (file_put_contents($temp, $data) !== strlen($data)) throw new RuntimeException('Kunde inte spara nyckeln.');
                chmod($temp, 0600);
                if (!rename($temp, $path)) throw new RuntimeException('Kunde inte ersätta nyckeln.');
            }
        } finally {
            if ($temp !== null && is_file($temp)) unlink($temp);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function revoke(string $user): void
    {
        $this->mutate($this->selector($user), null);
    }

    public function status(string $user): ?array
    {
        $record = $this->read($this->selector($user));
        if (!$record) return null;
        return ['created' => $record['created'], 'expires' => $record['expires'], 'ifAuth' => $record['ifAuth'],
            'active' => $record['expires'] > time() && $record['account'] === $this->auth->credentialFingerprint($user)];
    }

    public function authenticate(string $key): ?array
    {
        if (!preg_match('/^rw_mcp_([a-f0-9]{32})\.[a-f0-9]{64}$/D', $key, $match)) return null;
        $record = $this->read($match[1]);
        if (!$record || !is_string($record['hash'] ?? null) || !hash_equals($record['hash'], hash('sha256', $key))
            || ($record['expires'] ?? 0) <= time() || !is_string($record['user'] ?? null)) return null;
        $fingerprint = $this->auth->credentialFingerprint($record['user']);
        if ($fingerprint === null || !hash_equals($fingerprint, (string) ($record['account'] ?? ''))) return null;
        return ['user' => $record['user'], 'ifAuth' => ($record['ifAuth'] ?? false) === true];
    }
}
