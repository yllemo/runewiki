<?php
/**
 * templates/default/edit.php
 * Monaco Editor med tre IntelliSense-providers:
 *   1. YAML-frontmatter (nycklar + värden inuti ---block)
 *   2. Markdown-snippets (rubriker, block, formatering, tabeller)
 *   3. Wiki-interlinks [[...]] med befintliga sid-ID:n
 */
$allPagesJson = json_encode($allPages ?? [], JSON_UNESCAPED_UNICODE);
?>
<article class="wiki-page gbg-edit">
    <h1><?= $isNew ? 'Skapa sida' : 'Redigera sida' ?>: <code><?= Helpers::e($pageId->id()) ?></code></h1>
    <form method="post" action="<?= Helpers::e($pageId->url()) ?>?do=save" id="edit-form">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <div class="gbg-editor-toolbar">
            <label for="monaco-container">Innehåll (Markdown)</label>
            <button type="submit" class="gbg-btn gbg-btn-primary">Spara</button>
        </div>
        <textarea id="body" name="body" style="display:none"><?= Helpers::e($body) ?></textarea>
        <div id="monaco-container" class="gbg-monaco-container"></div>

        <div class="gbg-edit-actions">
            <button type="submit" class="gbg-btn gbg-btn-primary">Spara</button>
            <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e($pageId->url()) ?>">Avbryt</a>
            <span class="gbg-hint">
                <code>[[sida]]</code> wiki-länk &mdash;
                <code>[[wp&gt;Artikel]]</code> interwiki &mdash;
                <code>{{ns:bild.png}}</code> media &mdash;
                Tryck <kbd>Ctrl+Space</kbd> för förslag
            </span>
        </div>
    </form>
</article>

<script>window.WIKI_PAGES = <?= $allPagesJson ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.0/min/vs/loader.js"></script>
<script>
(function () {
    var container = document.getElementById('monaco-container');
    var bodyField  = document.getElementById('body');
    var form       = document.getElementById('edit-form');
    if (!container || !bodyField) return;

    var allPages = window.WIKI_PAGES || [];

    function getTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs';
    }

    require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.0/min/vs' } });

    require(['vs/editor/editor.main'], function () {

        // ── Editor ──────────────────────────────────────────────────────
        var editor = monaco.editor.create(container, {
            value: bodyField.value,
            language: 'markdown',
            theme: getTheme(),
            wordWrap: 'on',
            minimap: { enabled: false },
            lineNumbers: 'on',
            scrollBeyondLastLine: false,
            automaticLayout: true,
            fontSize: 14,
            fontFamily: "'Cascadia Code', 'Fira Code', 'Courier New', monospace",
            // Inga automatiska förslag medan man skriver löpande text — bara
            // på Ctrl+Space (manuellt) eller när wiki-länk-providern nedan
            // triggas av "[[" (se dess egna triggerCharacters). Annars poppar
            // hela snippet-menyn upp för varje mellanslag/bindestreck/etc.
            quickSuggestions: false,
        });

        var Snippet = monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet;
        var Kind    = monaco.languages.CompletionItemKind;

        // ── Helpers ─────────────────────────────────────────────────────

        /** Text från radstart till markören. */
        function lineBefore(model, pos) {
            return model.getLineContent(pos.lineNumber).substring(0, pos.column - 1);
        }

        /** Är markören inuti ---frontmatter---blocket längst upp? */
        function inFrontmatter(model, pos) {
            if (model.getLineContent(1).trim() !== '---') return false;
            for (var i = 2; i < pos.lineNumber; i++) {
                if (model.getLineContent(i).trim() === '---') return false;
            }
            return pos.lineNumber > 1;
        }

        /** Nycklar som redan finns i frontmatter (för att undvika dubletter). */
        function existingFmKeys(model) {
            var keys = [];
            for (var i = 2; i <= model.getLineCount(); i++) {
                var line = model.getLineContent(i).trim();
                if (line === '---') break;
                var m = line.match(/^(\w+)\s*:/);
                if (m) keys.push(m[1]);
            }
            return keys;
        }

        /** Är markören inuti [[...? */
        function inWikiLink(model, pos) {
            return /\[\[([^\][]*)$/.test(lineBefore(model, pos));
        }

        /**
         * Ersättningsintervall: från senaste icke-blanksteg tillbaka till markören.
         * Gör att snippet-insättning ersätter det som användaren nyss skrivit.
         */
        function tokenRange(model, pos) {
            var before = lineBefore(model, pos);
            var m = before.match(/(\S+)$/);
            return {
                startLineNumber: pos.lineNumber, endLineNumber: pos.lineNumber,
                startColumn: m ? pos.column - m[1].length : pos.column,
                endColumn: pos.column,
            };
        }

        /** Konverterar snippet-text till läsbar förhandsvisning för tooltip. */
        function preview(text) {
            return text
                .replace(/\$\{\d+\|([^,|]+)[^}]*\}/g, '$1')
                .replace(/\$\{\d+:([^}]*)\}/g, '$1')
                .replace(/\$\{\d+\}/g, '…')
                .replace(/\$\d+/g, '');
        }

        // ── Provider 1: YAML-frontmatter ────────────────────────────────

        var FM_KEYS = [
            { key: 'title',       text: 'title: ',                                   detail: 'Sidans titel' },
            { key: 'date',        text: 'date: ${1:ÅÅÅÅ-MM-DD}',                     detail: 'Datum (ÅÅÅÅ-MM-DD)',      snip: true },
            { key: 'updated',     text: 'updated: ${1:ÅÅÅÅ-MM-DD}',                  detail: 'Senast uppdaterad',       snip: true },
            { key: 'author',      text: 'author: ',                                   detail: 'Författare' },
            { key: 'description', text: 'description: ',                              detail: 'Kort beskrivning' },
            { key: 'tags',        text: 'tags: [${1}]',                               detail: 'Taggar  [a, b, c]',       snip: true },
            { key: 'status',      text: 'status: ${1|Publicerad,Utkast,Arkiverad|}',  detail: 'Publiceringsstatus',      snip: true },
            { key: 'draft',       text: 'draft: ${1|false,true|}',                    detail: 'Markera som utkast',      snip: true },
            { key: 'template',    text: 'template: ',                                  detail: 'Mall/tema (default)' },
        ];

        var FM_VALUES = {
            status: ['Publicerad', 'Utkast', 'Arkiverad'],
            draft:  ['false', 'true'],
        };

        monaco.languages.registerCompletionItemProvider('markdown', {
            provideCompletionItems: function (model, pos) {
                if (!inFrontmatter(model, pos)) return { suggestions: [] };

                var before = lineBefore(model, pos);

                // Värdeförslag: "nyckel: del"
                var vCtx = before.match(/^(\w+)\s*:\s*(\S*)$/);
                if (vCtx && FM_VALUES[vCtx[1]]) {
                    var partial = vCtx[2];
                    var range = {
                        startLineNumber: pos.lineNumber, endLineNumber: pos.lineNumber,
                        startColumn: pos.column - partial.length, endColumn: pos.column,
                    };
                    return {
                        suggestions: FM_VALUES[vCtx[1]].map(function (v) {
                            return { label: v, kind: Kind.Value, insertText: v, range: range };
                        })
                    };
                }

                // Nyckelförslag (undvik dubletter)
                var range   = tokenRange(model, pos);
                var current = existingFmKeys(model);
                return {
                    suggestions: FM_KEYS
                        .filter(function (k) { return current.indexOf(k.key) === -1; })
                        .map(function (k) {
                            return {
                                label: k.key,
                                kind: Kind.Property,
                                insertText: k.text,
                                insertTextRules: k.snip ? Snippet : undefined,
                                detail: k.detail,
                                documentation: { value: '`' + preview(k.text) + '`' },
                                sortText: '0' + k.key,
                                range: range,
                            };
                        })
                };
            },
        });

        // ── Provider 2: Wiki-interlinks [[...]] ─────────────────────────

        monaco.languages.registerCompletionItemProvider('markdown', {
            // Öppnar automatiskt bara på "[" (dvs. när man skriver "[["
            // — provideCompletionItems nedan returnerar ändå inga förslag
            // förrän det verkligen är en dubbel hakparentes). Widgeten
            // fortsätter sedan uppdatera sig live medan man skriver vidare
            // (t.ex. efter ":"), oavsett triggerCharacters.
            triggerCharacters: ['['],
            provideCompletionItems: function (model, pos) {
                var before = lineBefore(model, pos);
                var match  = before.match(/\[\[([^\][]*)$/);
                if (!match) return { suggestions: [] };

                var partial = match[1];
                var range = {
                    startLineNumber: pos.lineNumber, endLineNumber: pos.lineNumber,
                    startColumn: pos.column - partial.length, endColumn: pos.column,
                };
                var lower = partial.toLowerCase();

                return {
                    suggestions: allPages
                        .filter(function (p) { return p.toLowerCase().indexOf(lower) !== -1; })
                        .map(function (page) {
                            return {
                                label: page,
                                kind: Kind.File,
                                insertText: page + ']]',
                                range: range,
                                detail: 'Wiki-sida',
                                documentation: { value: '`[[' + page + ']]`' },
                                sortText: (page.toLowerCase().startsWith(lower) ? '0' : '1') + page,
                            };
                        })
                };
            },
        });

        // ── Provider 3: Markdown-snippets ───────────────────────────────

        var MD = [
            // Rubriker
            { label: '# H1',               ins: '# ${1:Rubrik}',                                                     detail: 'Rubrik nivå 1' },
            { label: '## H2',              ins: '## ${1:Rubrik}',                                                    detail: 'Rubrik nivå 2' },
            { label: '### H3',             ins: '### ${1:Rubrik}',                                                   detail: 'Rubrik nivå 3' },
            { label: '#### H4',            ins: '#### ${1:Rubrik}',                                                  detail: 'Rubrik nivå 4' },
            // Inline-formatering
            { label: '**fet**',            ins: '**${1:text}**',                                                     detail: 'Fet text' },
            { label: '*kursiv*',           ins: '*${1:text}*',                                                       detail: 'Kursiv text' },
            { label: '`inlinekod`',        ins: '`${1:kod}`',                                                        detail: 'Inlinekod' },
            // Block
            { label: '```kodblock',        ins: '```${1:language}\n${2:kod}\n```',                                  detail: 'Kodblock med språkval' },
            { label: '> citat',            ins: '> ${1:text}',                                                       detail: 'Blockcitat' },
            { label: '---',                ins: '\n---\n',                                                            detail: 'Horisontell linje' },
            // Listor
            { label: '- lista',            ins: '- ${1:objekt}',                                                     detail: 'Oordnad lista' },
            { label: '1. numrerad',        ins: '1. ${1:objekt}',                                                    detail: 'Ordnad lista' },
            { label: '- [ ] uppgift',      ins: '- [ ] ${1:uppgift}',                                               detail: 'Checklista' },
            // Länkar och media
            { label: '[länk](url)',        ins: '[${1:text}](${2:https://})',                                        detail: 'Extern Markdown-länk' },
            { label: '![bild](url)',       ins: '![${1:alt-text}](${2:url})',                                        detail: 'Extern bild (Markdown)' },
            // Wiki-syntax
            { label: '[[wiki-länk]]',      ins: '[[${1:namespace:sida}]]',                                           detail: 'Wiki-intern länk' },
            { label: '[[länk|etikett]]',   ins: '[[${1:namespace:sida}|${2:Etikett}]]',                             detail: 'Wiki-länk med etikett' },
            { label: '[[wp>Wikipedia]]',   ins: '[[wp>${1:Artikel}]]',                                               detail: 'Wikipedia interwiki' },
            { label: '{{media}}',          ins: '{{${1:namespace:fil.png}}}',                                        detail: 'Media-embed (bild/fil)' },
            { label: '{{media|alt}}',      ins: '{{${1:namespace:fil.png}|${2:alt-text}}}',                         detail: 'Media-embed med alt-text' },
            // Tabell
            { label: 'tabell',             ins: '| ${1:Kolumn 1} | ${2:Kolumn 2} |\n|---|---|\n| ${3:cell} | ${4:cell} |', detail: 'Markdown-tabell (2×2)' },
            // Frontmatter-startblock
            { label: '--- frontmatter',    ins: '---\ntitle: ${1:Titel}\ndate: ${2:ÅÅÅÅ-MM-DD}\ntags: [${3}]\n---\n\n# ${1:Titel}\n\n${4}', detail: 'YAML-frontmatter + H1' },
        ];

        monaco.languages.registerCompletionItemProvider('markdown', {
            // Inga triggerCharacters — den här listan (rubriker, fetstil,
            // kodblock m.m.) ska bara visas när man uttryckligen ber om det
            // med Ctrl+Space, inte poppa upp för varje mellanslag/tecken
            // man skriver i löpande text.
            provideCompletionItems: function (model, pos) {
                if (inFrontmatter(model, pos)) return { suggestions: [] };
                if (inWikiLink(model, pos))    return { suggestions: [] };

                var range = tokenRange(model, pos);
                return {
                    suggestions: MD.map(function (s) {
                        return {
                            label: s.label,
                            kind: Kind.Snippet,
                            insertText: s.ins,
                            insertTextRules: Snippet,
                            detail: s.detail,
                            documentation: { value: '```markdown\n' + preview(s.ins) + '\n```' },
                            range: range,
                        };
                    })
                };
            },
        });

        // ── Form + tema-sync ────────────────────────────────────────────
        form.addEventListener('submit', function () { bodyField.value = editor.getValue(); });

        new MutationObserver(function () {
            monaco.editor.setTheme(getTheme());
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    });
})();
</script>
