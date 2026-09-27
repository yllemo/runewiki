# Bundled YAML components

These MIT-licensed Symfony components are included so RuneWiki remains deployable
without running Composer. Licenses are included in each package directory.

- symfony/yaml v6.4.45, commit a778aba2d7130eba6150cc80cb692988a361ee4b
- symfony/deprecation-contracts v3.6.0, commit 63afe740e99a13ba87ec199bb07bbdee937a5b62
- symfony/polyfill-ctype v1.33.0, commit a3cc8b044a6ea513310cbd48ef7333b384945638

Source archives: the packages' GitHub distribution URLs in Packagist.
YAML 6.4 supports PHP 8.1 and includes bounded parsing depth/alias expansion.
Object deserialization, PHP constants and custom tags are not enabled.

Local compatibility patch: `Yaml::PARSE_DATE_AS_STRING` (16384), checked in
`Inline` before timestamp conversion, preserves the original text of dates and
OKF timestamps. RuneWiki's previous frontmatter parser returned date strings,
not Unix integers or PHP objects. Preserve this patch when updating the package.
