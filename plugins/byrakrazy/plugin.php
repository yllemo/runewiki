<?php
/** Formulär som skapar eller ändrar Markdown-sidor. */
class ByrakrazyPlugin implements PluginInterface
{
    private array $rendered = [];

    public function register(PluginManager $manager, array $options = []): void
    {
        $manager->on('page_markdown', [$this, 'prepare']);
        $manager->on('page_view', [$this, 'render']);
        $manager->on('form_submit', [$this, 'submit']);
    }

    private function blocks(string $body, string $tag): array
    {
        preg_match_all('~<' . $tag . '(?:\s+([^>]*))?>\s*\n?(.*?)</' . $tag . '>~si', $body, $matches, PREG_SET_ORDER);
        return $matches;
    }

    private function definition(string $body): array
    {
        $action = null;
        $fields = [];
        $items = [];
        $submit = 'Skicka';
        foreach (preg_split('/\R/u', $body) as $line) {
            $line = trim($line);
            if (preg_match('/^action\s+(pagemod|template)\s+(.+)$/i', $line, $m)) {
                $action = ['type' => strtolower($m[1]), 'args' => preg_split('/\s+/', trim($m[2]))];
            } elseif (preg_match('/^(textbox|textarea|select|checkbox|number|email|password)\s+"([^"]+)"(.*)$/i', $line, $m)) {
                $fields[] = ['type' => strtolower($m[1]), 'label' => $m[2], 'extra' => trim($m[3])];
                $items[] = ['field' => count($fields) - 1];
            } elseif (preg_match('/^hidden\s+"([^"]+)"\s+"([^"]*)"\s*$/i', $line, $m)) {
                // Värdet kommer från sidans definition, aldrig från POST-data.
                $fields[] = ['type' => 'hidden', 'label' => $m[1], 'extra' => $m[2]];
            } elseif (preg_match('/^fieldset\s+"([^"]*)"/i', $line, $m)) {
                $items[] = ['fieldset' => $m[1]];
            } elseif (preg_match('/^submit\s+"([^"]+)"/i', $line, $m)) {
                $submit = $m[1];
            }
        }
        return ['action' => $action, 'fields' => $fields, 'items' => $items, 'submit' => $submit];
    }

    public function prepare(array $ctx): array
    {
        $id = $ctx['id'];
        $body = $ctx['markdown'];
        $this->rendered[$id] = [];
        foreach ($this->blocks($body, 'pagemod') as $block) {
            $body = str_replace($block[0], '', $body);
        }
        foreach ($this->blocks($body, 'form') as $index => $block) {
            $token = 'BYRAKRAZYFORM' . $index . 'END';
            $this->rendered[$id][$token] = $this->formHtml($id, $index, $this->definition($block[2]));
            $body = str_replace($block[0], "\n\n" . $token . "\n\n", $body);
        }
        $ctx['markdown'] = $body;
        return $ctx;
    }

    public function render(array $ctx): array
    {
        foreach ($this->rendered[$ctx['id']] ?? [] as $token => $html) {
            $ctx['html'] = str_replace('<p>' . $token . '</p>', $html, $ctx['html']);
        }
        return $ctx;
    }

    private function formHtml(string $id, int $index, array $def): string
    {
        if (!$def['action']) return '<p>Formuläret saknar en giltig action.</p>';
        $url = (new PageId($id))->url() . '?do=form';
        $html = '<form class="byrakrazy-form" method="post" action="' . Helpers::e($url) . '">'
            . '<input type="hidden" name="csrf_token" value="' . Helpers::e(Helpers::csrfToken()) . '">'
            . '<input type="hidden" name="byrakrazy_form" value="' . $index . '">';
        $fieldsetOpen = false;
        foreach ($def['items'] as $item) {
            if (array_key_exists('fieldset', $item)) {
                if ($fieldsetOpen) $html .= '</fieldset>';
                $fieldsetOpen = $item['fieldset'] !== '';
                if ($fieldsetOpen) $html .= '<fieldset><legend>' . Helpers::e($item['fieldset']) . '</legend>';
                continue;
            }
            $i = $item['field'];
            $field = $def['fields'][$i];
            $name = 'field_' . $i;
            $label = Helpers::e($field['label']);
            $extra = $field['extra'];
            $optional = (bool) preg_match('/(?:^|\s)!\s*$/', $extra);
            $required = $optional ? '' : ' required';
            $default = preg_match('/"=([^"]*)"/', $extra, $defaultMatch) ? $defaultMatch[1] : '';
            if ($field['type'] === 'checkbox') {
                $html .= '<label><input type="checkbox" name="' . $name . '" value="1"> ' . $label . '</label>';
            } elseif ($field['type'] === 'textarea') {
                $html .= '<label>' . $label . '<textarea name="' . $name . '" rows="5"' . $required . '>' . Helpers::e($default) . '</textarea></label>';
            } elseif ($field['type'] === 'select') {
                $html .= '<label>' . $label . '<select name="' . $name . '">';
                foreach (explode('|', $field['extra']) as $choice) {
                    $choice = trim($choice, " \t\"'");
                    if ($choice !== '') $html .= '<option value="' . Helpers::e($choice) . '">' . Helpers::e($choice) . '</option>';
                }
                $html .= '</select></label>';
            } else {
                $type = in_array($field['type'], ['number', 'email', 'password'], true) ? $field['type'] : 'text';
                $attributes = $type === 'number' ? ' step="any"' : ' maxlength="500"';
                if ($type === 'number') {
                    if (preg_match('/(?:^|\s)>(-?\d+(?:\.\d+)?)(?:\s|$)/', $extra, $minimum)) $attributes .= ' min="' . Helpers::e($minimum[1]) . '"';
                    if (preg_match('/(?:^|\s)<(-?\d+(?:\.\d+)?)(?:\s|$)/', $extra, $maximum)) $attributes .= ' max="' . Helpers::e($maximum[1]) . '"';
                }
                $value = $type === 'password' ? '' : ' value="' . Helpers::e($default) . '"';
                $html .= '<label>' . $label . '<input type="' . $type . '" name="' . $name . '"' . $attributes . $value . $required . '></label>';
            }
        }
        if ($fieldsetOpen) $html .= '</fieldset>';
        return $html . '<button type="submit" class="gbg-btn gbg-btn-primary">' . Helpers::e($def['submit']) . '</button></form>';
    }

    private function fill(string $text, array $values): string
    {
        return preg_replace_callback('/@@([^@]+)@@/', fn ($m) => $values[$m[1]] ?? $m[0], $text);
    }

    /** Hitta den namngivna regeln på målsidan med byteposition i råfilen. */
    private function pagemodRule(string $raw, string $name): ?array
    {
        preg_match_all('~<pagemod(?:\s+([^>]*))?>\s*\n?(.*?)</pagemod>~si', $raw, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            $parts = preg_split('/\s+/', trim($match[1][0] ?? ''));
            if (($parts[0] ?? '') === $name) {
                return [
                    'text' => $match[0][0], 'offset' => $match[0][1],
                    'body' => $match[2][0], 'mode' => $parts[1] ?? 'output_after',
                ];
            }
        }
        return null;
    }

    /** Placera innehåll vid regelblocket; radslutet kommer från regelns innehåll. */
    private function insertFragment(string $raw, string $fragment, int $trailingLines, string $mode, int $offset, int $length): string
    {
        $newline = str_contains($raw, "\r\n") ? "\r\n" : "\n";
        $fragment = str_replace("\n", $newline, str_replace(["\r\n", "\r"], "\n", $fragment));
        $ending = str_repeat($newline, $trailingLines);
        if ($mode === 'output_before') {
            $before = substr($raw, 0, $offset);
            return $before . $fragment . $ending . substr($raw, $offset);
        }
        $end = $offset + $length;
        $after = substr($raw, $end);
        preg_match('/\A(?:[ \t]*(?:\r\n|\r|\n))*/', $after, $leading);
        $beforeFragment = $leading[0] !== '' ? $leading[0] : $newline;
        return substr($raw, 0, $end) . $beforeFragment . $fragment . $ending
            . substr($after, strlen($leading[0]));
    }

    public function submit(array $ctx): array
    {
        $post = $ctx['post'];
        $index = filter_var($post['byrakrazy_form'] ?? null, FILTER_VALIDATE_INT);
        $forms = $this->blocks($ctx['page']['body'], 'form');
        if ($index === false || $index === null || !isset($forms[$index])) {
            $ctx['form_result'] = ['error' => 'Okänt formulär.'];
            return $ctx;
        }
        $def = $this->definition($forms[$index][2]);
        $action = $def['action'];
        if (!$action) {
            $ctx['form_result'] = ['error' => 'Formuläret saknar action.'];
            return $ctx;
        }
        $values = [];
        foreach ($def['fields'] as $i => $field) {
            if ($field['type'] === 'hidden') {
                $values[$field['label']] = $field['extra'];
                continue;
            }
            $value = $post['field_' . $i] ?? '';
            if (!is_string($value) || strlen($value) > 10000) {
                $ctx['form_result'] = ['error' => 'Ogiltigt fältvärde.'];
                return $ctx;
            }
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            if (in_array($field['type'], ['textbox', 'number', 'email', 'password'], true)) $value = trim(str_replace("\n", ' ', $value));
            if ($field['type'] === 'checkbox') $value = $value === '1' ? '1' : '';
            if ($field['type'] === 'select' && !in_array($value, array_map(fn ($s) => trim($s, " \t\"'"), explode('|', $field['extra'])), true)) {
                $ctx['form_result'] = ['error' => 'Ogiltigt val.'];
                return $ctx;
            }
            $optional = (bool) preg_match('/(?:^|\s)!\s*$/', $field['extra']);
            if (!$optional && $value === '' && !in_array($field['type'], ['checkbox', 'select'], true)) {
                $ctx['form_result'] = ['error' => 'Fyll i ' . $field['label'] . '.'];
                return $ctx;
            }
            if ($field['type'] === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $ctx['form_result'] = ['error' => 'Ange en giltig e-postadress i ' . $field['label'] . '.'];
                return $ctx;
            }
            if ($field['type'] === 'number' && $value !== '') {
                if (!is_numeric($value)
                    || (preg_match('/(?:^|\s)>(-?\d+(?:\.\d+)?)(?:\s|$)/', $field['extra'], $minimum) && (float) $value <= (float) $minimum[1])
                    || (preg_match('/(?:^|\s)<(-?\d+(?:\.\d+)?)(?:\s|$)/', $field['extra'], $maximum) && (float) $value >= (float) $maximum[1])) {
                    $ctx['form_result'] = ['error' => 'Ogiltigt tal i ' . $field['label'] . '.'];
                    return $ctx;
                }
            }
            $values[$field['label']] = $value;
        }
        $pages = $ctx['pages'];
        if ($action['type'] === 'pagemod') {
            [$targetName, $ruleName] = array_pad($action['args'], 2, '');
            $filledTarget = $this->fill($targetName, $values);
            if ($filledTarget === '' || str_contains($filledTarget, '@@')) {
                $ctx['form_result'] = ['error' => 'Ogiltig målsida.'];
                return $ctx;
            }
            $target = $targetName === '.' ? new PageId($ctx['source_id']) : new PageId($filledTarget);
            $page = ($ctx['can_read'])($target) ? $pages->load($target) : null;
            if (!$page || !$ruleName) $result = ['error' => 'Målsidan eller pagemod-regeln saknas.'];
            else {
                $rule = $this->pagemodRule($page['raw'], $ruleName);
                $filledBody = $rule ? $this->fill($rule['body'], $values) : '';
                preg_match('/((?:[ \t]*(?:\r\n|\r|\n))+[ \t]*)$/', $filledBody, $trailing);
                $trailingLines = isset($trailing[1]) ? preg_match_all('/\r\n|\r|\n/', $trailing[1]) : 0;
                $fragment = isset($trailing[1]) ? substr($filledBody, 0, -strlen($trailing[1])) : $filledBody;
                if (!$rule) $result = ['error' => 'Pagemod-regeln finns inte på målsidan.'];
                elseif (!in_array($rule['mode'], ['output_before', 'output_after'], true)) $result = ['error' => 'Ogiltig placering i pagemod-regeln.'];
                elseif (trim($fragment) === '') $result = ['error' => 'Pagemod-regeln ger inget innehåll.'];
                else $result = [
                    'target' => $target->id(),
                    'raw' => $this->insertFragment($page['raw'], $fragment, $trailingLines, $rule['mode'], $rule['offset'], strlen($rule['text'])),
                    'create' => false,
                ];
            }
        } else {
            [$templateName, $targetName] = array_pad($action['args'], 2, '');
            if ($templateName === '' || $targetName === '') $result = ['error' => 'Ange mall och målsida.'];
            else {
                $templateId = new PageId($templateName);
                $template = ($ctx['can_read'])($templateId) ? $pages->load($templateId) : null;
                $filledTarget = $this->fill($targetName, $values);
                $target = new PageId($filledTarget);
                $result = ($filledTarget === '' || str_contains($filledTarget, '@@')) ? ['error' => 'Ogiltig målsida.'] : (!$template ? ['error' => 'Mallen finns inte.'] : [
                    'target' => $target->id(), 'raw' => $this->fill($template['raw'], $values), 'create' => true,
                ]);
            }
        }
        if (isset($result['raw']) && strlen($result['raw']) > 524288) $result = ['error' => 'Sidan blir för stor (max 512 KB).'];
        $ctx['form_result'] = $result;
        return $ctx;
    }
}
