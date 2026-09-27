<?php
return [
    'log_requests' => false,
    // Exact trusted browser origins (scheme + host + optional port), no wildcards.
    // Native MCP clients without an Origin header do not need an entry.
    'allowed_origins' => [],
    // Development only: both loopback remote address AND an exact host must match.
    'allow_local_http' => false,
    'local_hosts' => ['localhost', '127.0.0.1', '[::1]'],
];
