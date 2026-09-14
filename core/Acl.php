<?php
/**
 * core/Acl.php
 *
 * Ren behörighetslogik: avgör om en uppsättning grupper (se
 * Auth::groupsFor()) ger läs- eller redigeringsrätt till ett givet
 * namespace, utifrån grupperna i config/acl.php. Vet ingenting om
 * inloggning eller sessioner — det sköter Auth. Kombineras med
 * namespace-nivåns 'acl'-läge (public/login/private, config/namespaces.php)
 * i Wiki::canReadNamespace()/canEditNamespace().
 *
 * Rättighetssträngar i config/acl.php, en per grupp:
 *   "*"              — full åtkomst (läs + redigera) till ALLA namespaces
 *   "<namespace>"     — samma som "<namespace>:*" (läs + redigera det namespacet)
 *   "<namespace>:read" — bara läsrätt till det namespacet
 *   "<namespace>:edit" — läs- OCH redigeringsrätt till det namespacet (redigera ger alltid läsrätt)
 *   "*:read" / "*:edit" — samma rättighet men för ALLA namespaces
 *
 * "<namespace>" är namespacets exakta namn (motsvarar mappen under
 * /content, t.ex. "projekt") — inte punktseparerade filnamnsdelar. Roten
 * (sidor utan eget namespace) har namespace "" (tom sträng).
 */
class Acl
{
    /** @param array<string, array<int, string>> $groups Grupp-namn => lista rättighetssträngar (config/acl.php:s 'groups') */
    public function __construct(private array $groups)
    {
    }

    /**
     * True om NÅGON av $userGroups ger $right ("read" eller "edit") till $namespace.
     * @param array<int, string> $userGroups
     */
    public function can(array $userGroups, string $namespace, string $right): bool
    {
        foreach ($userGroups as $group) {
            foreach ($this->groups[$group] ?? [] as $permission) {
                if ($this->permissionGrants((string) $permission, $namespace, $right)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function permissionGrants(string $permission, string $namespace, string $right): bool
    {
        $parts = explode(':', $permission, 2);
        $permissionNamespace = $parts[0];
        $permissionRight     = $parts[1] ?? '*';

        if ($permissionNamespace !== '*' && $permissionNamespace !== $namespace) {
            return false;
        }
        // "*" eller "edit" ger även läsrätt — man kan inte redigera utan att läsa.
        return $permissionRight === '*' || $permissionRight === 'edit' || $permissionRight === $right;
    }
}
