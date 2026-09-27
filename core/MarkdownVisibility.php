<?php
/** Shared ifAuth handling for rendered pages and raw Markdown/API responses. */
class MarkdownVisibility
{
    public static function filter(string $markdown, bool $authenticated): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        // Match the parser's fenced examples. Preserve them verbatim, even with literal ifAuth markers.
        $protected = [];
        $prefix = "\x02" . bin2hex(random_bytes(12));
        $protect = static function ($m) use (&$protected, $prefix) {
            $token = $prefix . count($protected) . "\x02";
            $protected[$token] = $m[0];
            return $token;
        };
        $markdown = preg_replace_callback('/^ {0,3}(`{3,}|~{3,})([^\n]*)\n(.*?)\n {0,3}\1[ \t]*(?=\n|$)/ms', $protect, $markdown);
        $markdown = preg_replace_callback('/<!--.*?(?:-->|\z)/s', $protect, $markdown);
        $depth = 0;
        $visible = [];
        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^[ \t]*<ifAuth>[ \t]*$/i', $line)) {
                $depth++;
                $visible[] = '';
            } elseif (preg_match('/^[ \t]*<\/ifAuth>[ \t]*$/i', $line)) {
                $depth = max(0, $depth - 1);
                $visible[] = '';
            } elseif ($authenticated || $depth === 0) {
                $visible[] = $line;
            }
        }
        return strtr(implode("\n", $visible), $protected);
    }
}
