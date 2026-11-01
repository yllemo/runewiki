# Alternativ inloggning

[README](README.md) · [Flytta till en annan installation](readme-import.md) · [MCP](readme-mcp.md)

RuneWiki stöder lokala konton, OAuth 2.0-inloggning via **OpenID Connect (OIDC)**
och lokalt **Active Directory via LDAPS eller LDAP med StartTLS**. Båda externa
metoderna är avstängda som standard. Lokal inloggning finns alltid kvar som reserv.

Inställningar finns under **Admin → Inloggning** eller i **config/auth.php**.
GUI:t kräver CSRF-token och det inloggade kontots lokala wikilösenord för att spara.
Hela konfigurationen skrivs som PHP-array när GUI:t sparar; kommentarer och
eventuella `getenv()`-uttryck ersätts då av aktuella värden. Använd endast
filkonfiguration om hemligheter ska hämtas dynamiskt från miljövariabler.
Skydda konfigurationsfil och säkerhetskopior. Sparad klienthemlighet visas aldrig
i HTML; ett tomt lösenordsfält behåller den. En separat kryssruta raderar den.

## Konton och behörigheter

Skapa först ett lokalt wikikonto under **Admin → Användare** och välj dess grupper.
Koppla sedan den externa identiteten till kontot, exempelvis:

```php
'users' => ['extern-identitet' => 'anna'],
```

GUI:t använder motsvarande JSON: `{"extern-identitet":"anna"}`.
OIDC använder leverantörens exakta `sub`-värde, inte e-post eller visningsnamn.
AD använder `samAccountName` i gemener. Inga konton skapas automatiskt, och inga
grupper från leverantören importeras. Det lokala kontots ACL och ifAuth gäller.
Koppla bara till ett befintligt konto som den externa användaren ska få tillgång till.
Adminpanelen har samma befintliga åtkomstmodell: alla inloggade wikikonton når admin.

Aktivera `auth_enabled` under **Webbplats** om autentisering ska krävas för
redigering. Namespace-regler avgör vilka sidor som kräver inloggning för läsning.
Extern inloggning kräver HTTPS till wikin. Vid TLS-terminerande proxy måste
webbservern säkert sätta PHP:s HTTPS-status; godtyckliga klientheaders används inte.

## OpenID Connect / OAuth 2.0

1. Registrera en webbklient hos din OIDC-leverantör, t.ex. Entra ID, Keycloak eller AD FS.
2. Aktivera Authorization Code och PKCE S256. ID-token måste signeras med RS256.
3. Registrera exakt callback-URL: `https://din-wiki/?do=login&provider=oidc&callback=1`.
4. Ange exakt **issuer**, klient-ID, klienthemlighet och callback-URL i RuneWiki.
   Issuer måste matcha discovery-dokumentet. För Entra används en tenantspecifik issuer,
   inte en generell `common`-adress.
5. Välj leverantörens tokenautentisering: `client_secret_basic`, `client_secret_post`
   eller `none` för en publik klient med PKCE. Endast `openid` scope begärs.
6. Lägg in `sub` → wikikonto, aktivera och spara. Testa i en annan webbläsarsession
   medan administratörens lokala session finns kvar.

PHP behöver **curl** och **openssl**, giltiga CA-certifikat och nätåtkomst till
discovery-, token- och JWKS-endpoints. Endpoints måste använda HTTPS; HTTP-redirects
följs inte. Flödet verifierar state, nonce, PKCE, signatur, issuer, audience, azp
och tokenens tider. Callback är engångs och giltig i tio minuter.
Access-/refresh-token sparas inte. Ren OAuth utan OIDC-ID-token stöds inte.

## Lokalt Active Directory

PHP behöver **ldap** och nätåtkomst till domänkontrollanten.

```php
'ad' => [
    'enabled' => true,
    'label' => 'Logga in med AD',
    'url' => 'ldaps://dc.example.local:636',
    'domain' => 'example.local',
    'base_dn' => 'DC=example,DC=local',
    'ca_file' => '/etc/ssl/certs/ad-ca.pem',
    'users' => ['anna.ad' => 'anna'],
],
```

`domain` är UPN-suffixet. Användaren anger bara `samAccountName` och sitt AD-lösenord.
RuneWiki binder direkt som användaren och söker efter exakt ett aktivt användarkonto
inom Base DN. Användaren måste ha normal läsrätt där; inget tjänstekonto behövs.
Tomma lösenord avvisas. LDAP-filtervärden escapes och referrals följs inte.
`ldap://` kräver lyckad StartTLS före bind. Certifikatkontrollen kan inte stängas av;
installera organisationens CA eller ange `ca_file`. AD-lösenord sparas inte.
Detta är LDAP-inloggning, inte integrerad Windows-inloggning/Kerberos.

## Sessioner och drift

Efter inloggning används den befintliga wikisessionen och session-ID byts.
Utloggning avslutar wikisessionen, inte leverantörens SSO-session.
En befintlig wikisession avslutas inte automatiskt när ett AD-/OIDC-konto spärras;
leverantören kontrolleras vid nästa externa inloggning. Anpassa sessionstiden under
Webbplats. MCP-nycklar och lösenordsbyte behåller befintlig lokal lösenordskontroll.

## Verifiering

`php tests/external-auth.php` testar signerade ID-token, avvisning av ogiltiga claims,
state, kontokoppling och lokal reservinloggning. Kräver openssl (på Windows kan
`OPENSSL_CONF` behöva peka på PHP:s `extras/ssl/openssl.cnf`).
`php tests/mcp-http.php` testar också adminformulärets skydd och sparning.
Riktig OIDC- och AD-anslutning måste verifieras med organisationens server och konton.

## Felsökning

| Symptom | Kontrollera |
|---|---|
| Extern knapp saknas | Aktivera metoden och spara under Admin → Inloggning. |
| Callback/redirect avvisas | Samma exakta HTTPS callback-URL ska vara registrerad hos leverantören och i config/auth.php. |
| Kontot är inte kopplat | Kontrollera OIDC `sub` eller AD samAccountName och att målkontot finns under Användare. |
| OIDC issuer/signatur avvisas | Använd exakt issuer från discovery och RS256 som ID-tokenalgoritm. Kontrollera även serverklockan. |
| AD TLS misslyckas | Certifikatets namn måste matcha serveradressen och CA-kedjan måste vara betrodd av LDAP-klienten. |
| AD-kontot hittas inte | Kontrollera UPN-suffix, Base DN, läsrättigheter och att kontot är aktivt. |
| Det går inte att spara i admin | Ange det lokala wikilösenordet och kontrollera skrivrättigheter till config/. |

Vid felaktig konfiguration: logga in med lokalt konto och stäng av berörd extern
metod, eller sätt dess `enabled` till `false` direkt i `config/auth.php`.
Stäng inte av certifikatkontroll som lösning på anslutningsfel.

Referenser: [OpenID Connect Core](https://openid.net/specs/openid-connect-core-1_0.html),
[PHP LDAP TLS](https://www.php.net/manual/en/function.ldap-start-tls.php),
[PHP LDAP bind](https://www.php.net/manual/en/function.ldap-bind.php).
