<?php
/** OKF metadata policy. Display and editor choices never delete stored fields. */
class Metadata
{
    public static function fields(): array
    {
        return [
            'name' => ['label' => 'Namn', 'snippet' => 'name: ${1:Namn}'],
            'title' => ['label' => 'Titel', 'snippet' => 'title: ${1:Titel}'],
            'tags' => ['label' => 'Taggar', 'snippet' => 'tags: [${1}]'],
            'type' => ['label' => 'OKF: begreppstyp', 'snippet' => 'type: ${1|Reference,Playbook,API Endpoint,Metric,Attested Computation|}'],
            'description' => ['label' => 'Beskrivning', 'snippet' => 'description: ${1:Kort sammanfattning}'],
            'resource' => ['label' => 'OKF: resurs', 'snippet' => 'resource: ${1:https://example.com}'],
            'generated' => ['label' => 'OKF: skapad/ändrad av och tid', 'snippet' => "generated:\n  by: \${1:human:namn}\n  at: '\${2:__NOW__}'"],
            'verified' => ['label' => 'OKF: verifieringar', 'snippet' => "verified:\n  - by: \${1:human:namn}\n    at: '\${2:__NOW__}'"],
            'sources' => ['label' => 'OKF: källor', 'snippet' => "sources:\n  - id: \${1:kalla}\n    resource: \${2:https://example.com}\n    title: \${3:Källans namn}"],
            'usage_window' => ['label' => 'OKF: användningsperiod', 'snippet' => "usage_window:\n  from: '\${1:__NOW__}'\n  to: '\${2:__NOW__}'"],
            'status' => ['label' => 'Status (OKF och befintliga värden)', 'snippet' => 'status: ${1|draft,stable,deprecated,Publicerad,Utkast,Arkiverad|}'],
            'stale_after' => ['label' => 'OKF: inaktuell efter', 'snippet' => "stale_after: '\${1:__NOW__}'"],
            'runtime' => ['label' => 'OKF: beräkningsmiljö', 'snippet' => 'runtime: ${1|bigquery,postgres,dbt,python,Looker|}'],
            'parameters' => ['label' => 'OKF: parametrar', 'snippet' => "parameters:\n  - name: \${1:parameter}\n    type: \${2:string}\n    required: \${3|true,false|}"],
            'computation' => ['label' => 'OKF: beräkning', 'snippet' => 'computation: ${1:references/query.sql}'],
            'executor' => ['label' => 'OKF: exekvering', 'snippet' => "executor:\n  resource: \${1:references/run.md}\n  receipt: [\${2:job_id, result}]"],
            'attester' => ['label' => 'OKF: attestering', 'snippet' => "attester:\n  resource: \${1:references/attester.py}"],
            'okf_version' => ['label' => 'OKF-version (bundle-index)', 'snippet' => 'okf_version: "0.2"'],
            'date' => ['label' => 'Datum (befintligt fält)', 'snippet' => 'date: ${1:ÅÅÅÅ-MM-DD}'],
            'updated' => ['label' => 'Uppdaterad (befintligt fält)', 'snippet' => 'updated: ${1:ÅÅÅÅ-MM-DD}'],
            'author' => ['label' => 'Författare', 'snippet' => 'author: ${1:Namn}'],
            'draft' => ['label' => 'Utkast', 'snippet' => 'draft: ${1|false,true|}'],
            'template' => ['label' => 'Mall', 'snippet' => 'template: ${1:default}'],
        ];
    }

    public static function settings(string $root): array
    {
        return array_replace(['auto_timestamp' => true, 'show_on_page' => false, 'visible_fields' => array_keys(self::fields()),
            'editor_fields' => array_keys(self::fields()), 'show_custom_fields' => true], Helpers::loadConfig($root . '/config/metadata.php'));
    }

    public static function visible(array $meta, array $settings): array
    {
        return array_filter($meta, static fn ($key) => in_array($key, $settings['visible_fields'], true)
            || (!array_key_exists($key, self::fields()) && $settings['show_custom_fields']), ARRAY_FILTER_USE_KEY);
    }

    public static function snippets(array $settings): array
    {
        $result = [];
        foreach (self::fields() as $key => $field) if (in_array($key, $settings['editor_fields'], true)) {
            $result[] = ['key' => $key, 'text' => str_replace('__NOW__', gmdate('Y-m-d\TH:i:s\Z'), $field['snippet']), 'detail' => $field['label'], 'snip' => true];
        }
        return $result;
    }

    public static function frontmatterSnippet(array $settings): string
    {
        $lines = ['---'];
        $i = 1;
        foreach (['type' => 'Reference', 'title' => 'Titel', 'tags' => '', 'description' => 'Kort sammanfattning'] as $key => $default) {
            if (!in_array($key, $settings['editor_fields'], true)) continue;
            $value = '${' . $i++ . ':' . $default . '}';
            $lines[] = $key . ': ' . ($key === 'tags' ? '[' . $value . ']' : $value);
        }
        return implode("\n", $lines) . "\n---\n\n\${0}";
    }

    public static function stamp(array $meta, string $body, ?array $previous, ?string $user, ?string $now = null): array
    {
        if (!isset($meta['type']) || $meta['type'] === '') $meta['type'] = 'Reference';
        $compare = static function (array $value): array { unset($value['generated'], $value['verified'], $value['stale_after']); return $value; };
        $changed = $previous === null || trim($body) !== trim($previous['body']) || $compare($meta) != $compare($previous['meta']);
        if (!$changed && isset($previous['meta']['generated'])) {
            $meta['generated'] = $previous['meta']['generated'];
        } else {
            $generated = is_array($meta['generated'] ?? null) ? $meta['generated'] : [];
            $meta['generated'] = array_replace($generated, ['by' => $user !== null ? 'human:' . $user : 'process:runewiki', 'at' => $now ?? gmdate('Y-m-d\TH:i:s\Z')]);
        }
        return $meta;
    }
}
