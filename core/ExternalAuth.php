<?php
/** Explicit account mapping: no email matching, provisioning or implicit group grants. */
class ExternalAuth
{
    public static function settings(string $root): array
    {
        return array_replace_recursive([
            'oidc' => ['enabled' => false, 'label' => 'Logga in med organisationens konto', 'issuer' => '', 'client_id' => '', 'client_secret' => '', 'redirect_uri' => '', 'token_auth' => 'client_secret_basic', 'users' => []],
            'ad' => ['enabled' => false, 'label' => 'Logga in med AD', 'url' => '', 'domain' => '', 'base_dn' => '', 'ca_file' => '', 'users' => []],
        ], Helpers::loadConfig($root . '/config/auth.php'));
    }

    public static function httpsUrl(string $url): void
    {
        $p = parse_url($url);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['fragment']) || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new RuntimeException('En giltig HTTPS-adress krävs.');
        }
    }

    public static function validate(array $settings, Auth $auth): void
    {
        foreach (['oidc', 'ad'] as $provider) {
            $c = $settings[$provider];
            if (!is_array($c['users'])) throw new RuntimeException('Kontokopplingarna måste vara ett JSON-objekt.');
            foreach ($c['users'] as $identity => $local) {
                if ((string) $identity === '' || !is_string($local) || !$auth->userExists($local)) throw new RuntimeException('Varje kontokoppling måste peka på ett befintligt wikikonto.');
                if ($provider === 'ad' && !preg_match('/^[a-z0-9._-]+$/D', (string) $identity)) throw new RuntimeException('AD-konton anges med samAccountName i gemener, utan domän.');
            }
            if (!$c['enabled']) continue;
            if ($provider === 'oidc') {
                self::httpsUrl($c['issuer']); self::httpsUrl($c['redirect_uri']);
                if (parse_url($c['issuer'], PHP_URL_QUERY) !== null) throw new RuntimeException('Issuer får inte ha en query-sträng.');
                if ($c['client_id'] === '' || !in_array($c['token_auth'], ['client_secret_basic', 'client_secret_post', 'none'], true)) throw new RuntimeException('Kontrollera OIDC klient-ID och autentiseringsmetod.');
                if ($c['token_auth'] !== 'none' && $c['client_secret'] === '') throw new RuntimeException('OIDC-klienthemlighet saknas.');
                parse_str(parse_url($c['redirect_uri'], PHP_URL_QUERY) ?? '', $query);
                if (($query['do'] ?? '') !== 'login' || ($query['provider'] ?? '') !== 'oidc' || ($query['callback'] ?? '') !== '1') throw new RuntimeException('Callback måste innehålla ?do=login&provider=oidc&callback=1.');
            } else {
                $p = parse_url($c['url']);
                if (!$p || !in_array($p['scheme'] ?? '', ['ldap', 'ldaps'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || !in_array($p['path'] ?? '', ['', '/'], true)) throw new RuntimeException('Ange en LDAP- eller LDAPS-serveradress utan sökväg.');
                if (!preg_match('/^[a-zA-Z0-9.-]+$/D', $c['domain']) || $c['base_dn'] === '') throw new RuntimeException('AD-domän och Base DN krävs.');
            }
        }
    }

    public static function mappedUser(array $config, string $identity, Auth $auth): string
    {
        $local = $config['users'][$identity] ?? null;
        if (!is_string($local) || !$auth->userExists($local)) throw new RuntimeException('Kontot är inte kopplat till en användare i wikin.');
        return $local;
    }

    public static function adLogin(array $config, string $username, string $password, Auth $auth): string
    {
        if (empty($config['enabled']) || !function_exists('ldap_connect')) throw new RuntimeException('AD är inte tillgängligt. Kontrollera inställningar och PHP-tillägget ldap.');
        if ($password === '' || !preg_match('/^[a-zA-Z0-9._-]+$/D', $username)) throw new RuntimeException('Felaktigt AD-konto eller lösenord.');
        $username = strtolower($username);
        $local = self::mappedUser($config, $username, $auth);
        // Set CA policy before connecting, including implicit TLS connections.
        ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND);
        if ($config['ca_file'] !== '') ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $config['ca_file']);
        $ldap = ldap_connect($config['url']);
        if (!$ldap) throw new RuntimeException('Kunde inte ansluta till AD.');
        try {
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 10);
            if (str_starts_with($config['url'], 'ldap:') && !@ldap_start_tls($ldap)) throw new RuntimeException('AD:s TLS-anslutning kunde inte verifieras.');
            if (!@ldap_bind($ldap, $username . '@' . $config['domain'], $password)) throw new RuntimeException('Felaktigt AD-konto eller lösenord.');
            $filter = '(&(objectCategory=person)(objectClass=user)(sAMAccountName=' . ldap_escape($username, '', LDAP_ESCAPE_FILTER) . ')(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';
            $search = @ldap_search($ldap, $config['base_dn'], $filter, ['samaccountname'], 0, 2, 10);
            if (!$search || ldap_count_entries($ldap, $search) !== 1) throw new RuntimeException('AD-kontot finns inte inom tillåten sökbas eller är inaktiverat.');
            return $local;
        } finally { ldap_unbind($ldap); }
    }
}
