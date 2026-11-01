<?php
// External identities map to existing local accounts; their wiki groups still apply.
return [
    'oidc' => [
        'enabled' => false,
        'label' => 'Logga in med organisationens konto',
        'issuer' => '',
        'client_id' => '',
        'client_secret' => '', // Or getenv('RUNEWIKI_OIDC_SECRET') ?: ''
        'redirect_uri' => '', // https://wiki.example/?do=login&provider=oidc&callback=1
        'token_auth' => 'client_secret_basic', // client_secret_post or none (public PKCE client)
        'users' => [], // 'exact OIDC sub claim' => 'local wiki username'
    ],
    'ad' => [
        'enabled' => false,
        'label' => 'Logga in med AD',
        'url' => '', // ldaps://dc.example.local:636 or ldap://dc.example.local:389 (StartTLS)
        'domain' => '', // UPN suffix, e.g. example.local
        'base_dn' => '', // DC=example,DC=local
        'ca_file' => '', // Optional absolute path to trusted CA PEM file on server
        'users' => [], // 'samaccountname' (lowercase) => 'local wiki username'
    ],
];
