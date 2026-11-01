<?php
/** Authorization Code + PKCE (S256), RS256 ID tokens from a configured issuer. */
class OidcAuth
{
    public function __construct(private array $config) {}

    private static function b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
    private static function decode(string $s): string
    {
        if ($s === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $s)) throw new RuntimeException('Ogiltig OIDC-token.');
        $value = base64_decode(strtr($s, '-_', '+/'), true);
        if ($value === false) throw new RuntimeException('Ogiltig OIDC-token.');
        return $value;
    }

    private function request(string $url, ?array $form = null, ?string $basic = null): array
    {
        ExternalAuth::httpsUrl($url);
        if (!function_exists('curl_init')) throw new RuntimeException('PHP-tillägget curl krävs för OIDC.');
        $ch = curl_init($url); $body = '';
        $options = [CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > 1024 * 1024) return 0;
                $body .= $chunk; return strlen($chunk);
            }];
        if ($form !== null) { $options[CURLOPT_POST] = true; $options[CURLOPT_POSTFIELDS] = http_build_query($form, '', '&', PHP_QUERY_RFC3986); }
        if ($basic !== null) { $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC; $options[CURLOPT_USERPWD] = $basic; }
        curl_setopt_array($ch, $options);
        $ok = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($ok === false || $status !== 200) throw new RuntimeException('Identitetsleverantören kunde inte kontaktas eller avvisade anropet.');
        $json = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($json)) throw new RuntimeException('Ogiltigt svar från identitetsleverantören.');
        return $json;
    }

    private function discovery(): array
    {
        $d = $this->request(rtrim($this->config['issuer'], '/') . '/.well-known/openid-configuration');
        if (($d['issuer'] ?? '') !== $this->config['issuer']) throw new RuntimeException('OIDC issuer stämmer inte med konfigurationen.');
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) ExternalAuth::httpsUrl($d[$key] ?? '');
        return $d;
    }

    public function begin(string $redirect): string
    {
        $d = $this->discovery();
        $state = self::b64(random_bytes(32)); $nonce = self::b64(random_bytes(32)); $verifier = self::b64(random_bytes(48));
        $_SESSION['runewiki_oidc'] = ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'time' => time(),
            'redirect' => $redirect, 'config' => hash('sha256', serialize($this->config))];
        $params = ['response_type' => 'code', 'scope' => 'openid', 'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'], 'state' => $state, 'nonce' => $nonce,
            'code_challenge' => self::b64(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256'];
        return $d['authorization_endpoint'] . (str_contains($d['authorization_endpoint'], '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function finish(array $query): array
    {
        $flow = $_SESSION['runewiki_oidc'] ?? null;
        unset($_SESSION['runewiki_oidc']); // Single-use, including failed callbacks.
        if (!is_array($flow) || !is_string($query['state'] ?? null) || !hash_equals($flow['state'], $query['state'])
            || time() - $flow['time'] > 600 || $flow['config'] !== hash('sha256', serialize($this->config))) throw new RuntimeException('Inloggningen har gått ut eller kunde inte verifieras. Försök igen.');
        if (!is_string($query['code'] ?? null) || $query['code'] === '' || isset($query['error'])) throw new RuntimeException('Inloggningen avbröts av identitetsleverantören.');
        if (isset($query['iss']) && $query['iss'] !== $this->config['issuer']) throw new RuntimeException('Fel issuer i callback.');
        $d = $this->discovery();
        $form = ['grant_type' => 'authorization_code', 'code' => $query['code'], 'redirect_uri' => $this->config['redirect_uri'],
            'client_id' => $this->config['client_id'], 'code_verifier' => $flow['verifier']];
        $basic = null;
        if ($this->config['token_auth'] === 'client_secret_post') $form['client_secret'] = $this->config['client_secret'];
        if ($this->config['token_auth'] === 'client_secret_basic') $basic = urlencode($this->config['client_id']) . ':' . urlencode($this->config['client_secret']);
        $tokens = $this->request($d['token_endpoint'], $form, $basic);
        $keys = $this->request($d['jwks_uri']);
        $claims = self::verifyIdToken($tokens['id_token'] ?? '', $keys['keys'] ?? [], $this->config['issuer'], $this->config['client_id'], $flow['nonce']);
        return ['sub' => $claims['sub'], 'redirect' => $flow['redirect']];
    }

    public static function verifyIdToken(string $jwt, array $keys, string $issuer, string $audience, string $nonce): array
    {
        if (strlen($jwt) > 65536) throw new RuntimeException('OIDC-token är för stor.');
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) throw new RuntimeException('Ogiltig OIDC-token.');
        $header = json_decode(self::decode($parts[0]), true, 16, JSON_THROW_ON_ERROR);
        $claims = json_decode(self::decode($parts[1]), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || isset($header['crit'])) throw new RuntimeException('OIDC kräver en RS256-signerad ID-token.');
        $candidates = array_values(array_filter($keys, static fn ($key) => is_array($key) && ($key['kty'] ?? '') === 'RSA'
            && (!isset($header['kid']) || ($key['kid'] ?? null) === $header['kid'])
            && (!isset($key['use']) || $key['use'] === 'sig') && (!isset($key['alg']) || $key['alg'] === 'RS256')
            && (!isset($key['key_ops']) || in_array('verify', $key['key_ops'], true))));
        if (count($candidates) !== 1) throw new RuntimeException('OIDC-signaturnyckeln kunde inte väljas.');
        $key = $candidates[0];
        $n = self::decode($key['n'] ?? ''); $e = self::decode($key['e'] ?? '');
        if (strlen($n) < 256 || strlen($n) > 1024 || strlen($e) > 8) throw new RuntimeException('Ogiltig RSA-nyckel.');
        $integer = static fn ($s) => self::der(2, "\0" . ltrim($s, "\0"));
        $rsa = self::der(48, $integer($n) . $integer($e));
        $spki = self::der(48, hex2bin('300d06092a864886f70d0101010500') . self::der(3, "\0" . $rsa));
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
        if (openssl_verify($parts[0] . '.' . $parts[1], self::decode($parts[2]), $pem, OPENSSL_ALGO_SHA256) !== 1) throw new RuntimeException('OIDC-signaturen är ogiltig.');
        $aud = $claims['aud'] ?? null;
        if (($claims['iss'] ?? null) !== $issuer || !(is_string($aud) ? $aud === $audience : (is_array($aud) && in_array($audience, $aud, true)))
            || (isset($claims['azp']) && $claims['azp'] !== $audience) || (is_array($aud) && count($aud) > 1 && ($claims['azp'] ?? null) !== $audience)
            || !is_int($claims['exp'] ?? null) || $claims['exp'] < time() - 60
            || !is_int($claims['iat'] ?? null) || $claims['iat'] > time() + 60
            || (isset($claims['nbf']) && (!is_int($claims['nbf']) || $claims['nbf'] > time() + 60))
            || !is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])
            || !is_string($claims['sub'] ?? null) || $claims['sub'] === '') throw new RuntimeException('OIDC-tokenens identitet, mottagare eller giltighet kunde inte verifieras.');
        return $claims;
    }

    private static function der(int $tag, string $value): string
    {
        $length = strlen($value);
        if ($length < 128) return chr($tag) . chr($length) . $value;
        $bytes = ltrim(pack('N', $length), "\0");
        return chr($tag) . chr(128 | strlen($bytes)) . $bytes . $value;
    }
}
