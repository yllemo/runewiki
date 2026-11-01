<?php
spl_autoload_register(static function ($class) { $path = __DIR__ . '/../core/' . $class . '.php'; if (is_file($path)) require_once $path; });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function rejects(callable $fn, string $message): void {
    $failed = false;
    try { $fn(); } catch (Throwable $e) { $failed = true; }
    check($failed, $message);
}
function b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
$options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
if (getenv('OPENSSL_CONF')) $options['config'] = getenv('OPENSSL_CONF');
$key = openssl_pkey_new($options);
check($key !== false, 'Generate test signing key');
$details = openssl_pkey_get_details($key);
$jwk = ['kty' => 'RSA', 'kid' => 'test', 'alg' => 'RS256', 'n' => b64($details['rsa']['n']), 'e' => b64($details['rsa']['e'])];
$claims = ['iss' => 'https://id.example', 'aud' => 'wiki', 'sub' => 'subject-123', 'nonce' => 'nonce-test', 'exp' => time() + 300, 'iat' => time()];
$sign = static function (array $claims, string $alg = 'RS256') use ($key): string {
    $data = b64(json_encode(['alg' => $alg, 'kid' => 'test'])) . '.' . b64(json_encode($claims));
    openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
    return $data . '.' . b64($signature);
};
$verify = static fn ($token, $keys = null) => OidcAuth::verifyIdToken($token, $keys ?? [$jwk], 'https://id.example', 'wiki', 'nonce-test');
check($verify($sign($claims))['sub'] === 'subject-123', 'Signed identity validated');
foreach (['iss' => 'https://evil.example', 'aud' => 'other', 'nonce' => 'wrong', 'exp' => time() - 300, 'iat' => time() + 600, 'sub' => '', 'azp' => 'other'] as $field => $bad) {
    rejects(fn () => $verify($sign(array_replace($claims, [$field => $bad]))), 'Reject invalid ' . $field);
}
rejects(fn () => $verify($sign($claims, 'none')), 'Reject algorithm substitution');
$token = $sign($claims); $parts = explode('.', $token); $parts[1] = b64(json_encode(array_replace($claims, ['sub' => 'admin'])));
rejects(fn () => $verify(implode('.', $parts)), 'Reject tampering');
rejects(fn () => $verify($token, []), 'Reject unknown signing key');
rejects(fn () => $verify($token, [$jwk, $jwk]), 'Reject ambiguous signing keys');
rejects(fn () => $verify($sign(array_replace($claims, ['aud' => ['wiki', 'other']]))), 'Multiple audiences require azp');
check($verify($sign(array_replace($claims, ['aud' => ['wiki', 'other'], 'azp' => 'wiki'])))['sub'] === 'subject-123', 'Validate authorized party');
$root = sys_get_temp_dir() . '/wiki-auth-' . bin2hex(random_bytes(8)); mkdir($root);
try {
    $auth = new Auth($root . '/users.php', true, 30, false);
    $auth->setPassword('anna', 'local-test-password'); $auth->setUserGroups('anna', ['reader']);
    $config = ExternalAuth::settings(dirname(__DIR__));
    $config['oidc']['users'] = ['subject-123' => 'anna'];
    check(ExternalAuth::mappedUser($config['oidc'], 'subject-123', $auth) === 'anna', 'Explicit subject mapping');
    rejects(fn () => ExternalAuth::mappedUser($config['oidc'], 'anna', $auth), 'No implicit username linking');
    $auth->establishSession('anna');
    check($auth->currentUser() === 'anna' && $auth->currentUserGroups() === ['reader'], 'Mapped login retains local ACL groups');
    check($auth->login('anna', 'local-test-password'), 'Local login retained');
    $config['oidc'] = array_replace($config['oidc'], ['enabled' => true, 'issuer' => 'https://id.example', 'client_id' => 'wiki', 'client_secret' => 'secret', 'redirect_uri' => 'https://wiki.example/?do=login&provider=oidc&callback=1']);
    ExternalAuth::validate($config, $auth);
    $bad = $config; $bad['oidc']['issuer'] = 'http://id.example';
    rejects(fn () => ExternalAuth::validate($bad, $auth), 'Reject unencrypted issuer');
    $oidc = new OidcAuth($config['oidc']);
    $_SESSION['runewiki_oidc'] = ['state' => 'correct'];
    rejects(fn () => $oidc->finish(['state' => 'wrong', 'code' => 'code']), 'Reject mismatched state before network');
    check(!isset($_SESSION['runewiki_oidc']), 'Consume callback state');
    $config['ad']['enabled'] = true;
    rejects(fn () => ExternalAuth::adLogin($config['ad'], 'anna', '', $auth), 'Reject empty LDAP password');
    $auth->logout(); check($auth->currentUser() === null, 'Logout clears mapped login');
    echo "PASS: OIDC signatures, claims, state, account mapping, local fallback and LDAP empty-password rejection\n";
} finally { if (is_file($root . '/users.php')) unlink($root . '/users.php'); rmdir($root); }
