<?php
/**
 * core/NamespaceResolver.php
 *
 * Namespace-upplösning: läser config/namespaces.php och ger tillbaka
 * inställningar (tema, ACL) för ett givet namespace, med fallback till
 * root-inställningarna ('').
 */

class NamespaceResolver
{
    public function __construct(private array $namespaceConfig)
    {
    }

    /** Independent of auth_enabled; a root restriction applies to every namespace. */
    public function requiresLogin(string $namespace): bool
    {
        return !empty($this->namespaceConfig['']['require_login'])
            || !empty($this->namespaceConfig[$namespace]['require_login']);
    }

    public function settingsFor(string $namespace): array
    {
        $root = $this->namespaceConfig[''] ?? [];
        $specific = $this->namespaceConfig[$namespace] ?? [];
        return array_replace($root, $specific);
    }
}
