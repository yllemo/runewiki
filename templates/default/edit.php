<?php
/**
 * templates/default/edit.php
 * Monaco Editor med fyra IntelliSense-providers:
 *   1. YAML-frontmatter (nycklar + värden inuti ---block)
 *   2. Markdown-snippets (rubriker, block, formatering, tabeller)
 *   3. Wiki-interlinks [[...]] med befintliga sid-ID:n
 *   4. Media-embeds {{...}} med befintliga uppladdade filer
 * Klistrar man in (Ctrl+V) eller drar-och-släpper en bild laddas den upp
 * till /images (samma namespace som sidan som redigeras) och ![alt](url)
 * skrivs in vid markören.
 */
$allPagesJson  = json_encode($allPages ?? [], JSON_UNESCAPED_UNICODE);
$allMediaJson  = json_encode($allMedia ?? [], JSON_UNESCAPED_UNICODE);
$mediaNsJson   = json_encode($pageId->namespace(), JSON_UNESCAPED_UNICODE);
?>
<article class="wiki-page gbg-edit">
    <h1><?= $isNew ? 'Skapa sida' : 'Redigera sida' ?>: <code><?= Helpers::e($pageId->id()) ?></code></h1>
    <form method="post" action="<?= Helpers::e($pageId->url()) ?>?do=save" id="edit-form">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <div class="gbg-editor-toolbar">
            <label for="monaco-container">Innehåll (Markdown)</label>
            <div class="gbg-editor-toolbar-actions">
                <span id="media-upload-status" class="gbg-upload-inline-status" aria-live="polite"></span>
                <button type="button" id="media-picker-btn" class="gbg-btn gbg-btn-outline">🖼 Bläddra i media</button>
                <button type="submit" class="gbg-btn gbg-btn-primary">Spara</button>
            </div>
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
                dra och släpp eller tryck <kbd>Ctrl+V</kbd> med en bild i urklipp för att ladda upp och infoga <code>![beskrivning](bildadress)</code> &mdash;
                Tryck <kbd>Ctrl+Space</kbd> för förslag
            </span>
        </div>
    </form>
</article>

<script>
window.WIKI_PAGES     = <?= $allPagesJson ?>;
window.WIKI_MEDIA     = <?= $allMediaJson ?>;
window.WIKI_MEDIA_NS  = <?= $mediaNsJson ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.0/min/vs/loader.js"></script>
<script>
(function () {
    var container = document.getElementById('monaco-container');
    var bodyField  = document.getElementById('body');
    var form       = document.getElementById('edit-form');
    if (!container || !bodyField) return;

    var allPages = window.WIKI_PAGES || [];
    var allMedia = window.WIKI_MEDIA || [];
    var mediaNs  = window.WIKI_MEDIA_NS || '';

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

        /** Är markören inuti [[... eller {{...? */
        function inWikiLink(model, pos) {
            var before = lineBefore(model, pos);
            return /\[\[([^\][]*)$/.test(before) || /\{\{([^{}|]*)$/.test(before);
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

                // Editorn auto-stänger hakparenteser — när man skriver "[["
                // har den redan lagt till "]]" direkt efter markören. Vår
                // egen insertText nedan lägger själv till "]]", så utan att
                // utöka ersättningsintervallet hit skulle den autostängda
                // "]]" bli kvar OCH vår egen läggas till, vilket ger fyra
                // "]" istället för två. Sträcker vi ut endColumn över den
                // redan befintliga "]]" (om den finns) ersätts den istället.
                var afterCursor = model.getLineContent(pos.lineNumber).substring(pos.column - 1);
                var extraClose  = afterCursor.match(/^\]{1,2}/);
                var endColumn   = pos.column + (extraClose ? extraClose[0].length : 0);

                var range = {
                    startLineNumber: pos.lineNumber, endLineNumber: pos.lineNumber,
                    startColumn: pos.column - partial.length, endColumn: endColumn,
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

        // ── Provider 3: Media-embeds {{...}} ─────────────────────────────

        monaco.languages.registerCompletionItemProvider('markdown', {
            // Samma mönster som wiki-interlink-providern ovan, fast för "{{"
            // och befintliga uppladdade mediefiler istället för sidor.
            triggerCharacters: ['{'],
            provideCompletionItems: function (model, pos) {
                var before = lineBefore(model, pos);
                var match  = before.match(/\{\{([^{}|]*)$/);
                if (!match) return { suggestions: [] };

                var partial = match[1];

                // Samma logik som för [[...]]: ersätt en redan autostängd
                // "}}" istället för att lägga till en egen ovanpå den.
                var afterCursor = model.getLineContent(pos.lineNumber).substring(pos.column - 1);
                var extraClose  = afterCursor.match(/^\}{1,2}/);
                var endColumn   = pos.column + (extraClose ? extraClose[0].length : 0);

                var range = {
                    startLineNumber: pos.lineNumber, endLineNumber: pos.lineNumber,
                    startColumn: pos.column - partial.length, endColumn: endColumn,
                };
                var lower = partial.toLowerCase();

                return {
                    suggestions: allMedia
                        .filter(function (m) { return m.toLowerCase().indexOf(lower) !== -1; })
                        .map(function (media) {
                            return {
                                label: media,
                                kind: Kind.File,
                                insertText: media + '}}',
                                range: range,
                                detail: 'Media',
                                documentation: { value: '`{{' + media + '}}`' },
                                sortText: (media.toLowerCase().startsWith(lower) ? '0' : '1') + media,
                            };
                        })
                };
            },
        });

        // ── Provider 4: Markdown-snippets ───────────────────────────────

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
            { label: 'yaml frontmatter',   ins: '---\ntitle: ${1:Titel}\ntags: [${2}]\n---\n\n${0}', detail: 'YAML-frontmatter med titel och tom tagglista', frontmatter: true },
            { label: 'mermaid flödesschema', ins: '```mermaid\nflowchart LR\n    A[${1:Start}] --> B[${2:Slut}]\n```\n${0}', detail: 'Mermaid: flödesschema' },
            { label: 'mermaid sekvensdiagram', ins: '```mermaid\nsequenceDiagram\n    participant A as ${1:Användare}\n    participant B as ${2:System}\n    A->>B: ${3:Förfrågan}\n    B-->>A: ${4:Svar}\n```\n${0}', detail: 'Mermaid: sekvensdiagram' },
            { label: 'mermaid klassdiagram', ins: '```mermaid\nclassDiagram\n    class ${1:Exempel} {\n        +String ${2:namn}\n        +${3:metod}()\n    }\n```\n${0}', detail: 'Mermaid: klassdiagram' },
        ];

        [
            ['simple', 'Enkel box'], ['info', 'Information'], ['note', 'Notering'],
            ['tip', 'Tips'], ['important', 'Viktigt'], ['warning', 'Varning'],
            ['danger', 'Fara'], ['help', 'Hjälp'], ['download', 'Nedladdning'],
            ['todo', 'Att göra'], ['success', 'Klart'],
        ].forEach(function (box) {
            MD.push({ label: 'box ' + box[0] + ' — ' + box[1],
                ins: '::: ' + box[0] + ' ${1:' + box[1] + '}\n${2:Innehåll med **Markdown**}\n:::\n${0}',
                detail: 'Färgad Markdown-box: ' + box[1] });
        });
        MD.push({ label: 'box GitHub alert', ins: '> [!${1|NOTE,TIP,IMPORTANT,WARNING,CAUTION|}]\n> ${2:Innehåll}\n${0}', detail: 'GitHub-kompatibel informationsruta' });

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
                    suggestions: MD.filter(function (s) {
                        // Frontmatter belongs at the start and must not be duplicated.
                        return !s.frontmatter || (pos.lineNumber === 1 && model.getLineContent(1).trim() !== '---');
                    }).map(function (s) {
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

        // ── Klistra in (Ctrl+V) eller dra-och-släpp en bild → ladda upp till
        // /images, skriv in ![alt](url) — två oberoende sätt att trigga samma
        // uppladdningsfunktion, så det ena fungerar även om det andra av
        // någon anledning inte gör det (t.ex. urklipps-behörighet i
        // webbläsaren, eller att OS/skärmdumpsverktyget inte lägger en
        // bild på urklipp som webbläsaren känner igen).

        var uploadStatusEl    = document.getElementById('media-upload-status');
        var uploadStatusTimer = null;
        /** Synlig statustext bredvid "Bläddra i media" — INTE bara alert()/console, så man ser något händer även utan devtools öppna. */
        function setUploadStatus(text, isError) {
            if (!uploadStatusEl) return;
            uploadStatusEl.textContent = text || '';
            uploadStatusEl.classList.toggle('is-error', !!isError);
            clearTimeout(uploadStatusTimer);
            if (text && !isError && !pendingUploads) {
                uploadStatusTimer = setTimeout(function () { uploadStatusEl.textContent = ''; }, 5000);
            }
        }

        var pendingUploads = 0;
        function updateUploadState(delta) {
            pendingUploads += delta;
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = pendingUploads > 0;
            });
        }

        /** Upload a clipboard/drop image and insert the URL returned by the server. */
        async function uploadImageFile(file) {
            var model = editor.getModel();
            var selection = editor.getSelection();
            if (!model || !selection) return;
            var originalText = model.getValueInRange(selection);
            var placeholderText = '![Laddar upp bild…]()';
            editor.pushUndoStop();
            editor.executeEdits('image-upload', [{ range: selection, text: placeholderText }]);
            var placeholderRange = new monaco.Range(
                selection.startLineNumber, selection.startColumn,
                selection.startLineNumber, selection.startColumn + placeholderText.length
            );
            editor.setPosition({ lineNumber: placeholderRange.endLineNumber, column: placeholderRange.endColumn });
            var decorationIds = model.deltaDecorations([], [{
                range: placeholderRange,
                options: { stickiness: monaco.editor.TrackedRangeStickiness.NeverGrowsWhenTypingAtEdges },
            }]);
            editor.pushUndoStop();

            function replacePlaceholder(text) {
                if (model.isDisposed() || editor.getModel() !== model) return false;
                var liveRange = model.getDecorationRange(decorationIds[0]);
                // Never replace unrelated text if the user deleted/edited/undid the placeholder.
                if (!liveRange || model.getValueInRange(liveRange) !== placeholderText) return false;
                editor.pushUndoStop();
                editor.executeEdits('image-upload', [{ range: liveRange, text: text }]);
                editor.pushUndoStop();
                return true;
            }

            updateUploadState(1);
            setUploadStatus('Laddar upp bild…');
            try {
                var csrfInput = form.querySelector('input[name="csrf_token"]');
                var mimeExtensions = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/gif': 'gif',
                    'image/webp': 'webp', 'image/svg+xml': 'svg' };
                var ext = mimeExtensions[file.type] || (file.name || '').split('.').pop().toLowerCase();
                if (!/^(png|jpe?g|gif|webp|svg)$/.test(ext)) throw new Error('Bildformatet stöds inte. Använd PNG, JPG, GIF, WEBP eller SVG.');
                var unique = Array.from(crypto.getRandomValues(new Uint8Array(12)), function (b) {
                    return b.toString(16).padStart(2, '0');
                }).join('');
                var filename = 'bild-' + unique + '.' + ext;
                var fd = new FormData();
                fd.append('csrf_token', csrfInput ? csrfInput.value : '');
                fd.append('ajax', '1');
                fd.append('upload', file, filename);
                var uploadUrl = mediaNs
                    ? '/images/' + mediaNs.split(':').map(encodeURIComponent).join('/') + '/?do=upload'
                    : '/images/?do=upload';
                var response = await fetch(uploadUrl, {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                var data;
                try { data = await response.json(); }
                catch (_) { throw new Error('Servern svarade inte med uppladdningsdata (HTTP ' + response.status + ').'); }
                if (!response.ok || !data.ok) throw new Error(data.error || 'Uppladdningen misslyckades.');
                if (typeof data.url !== 'string' || !data.url.startsWith('/images/')) throw new Error('Servern returnerade ingen giltig bildadress.');
                var markdown = '![Beskrivning av bilden](' + data.url.replace(/[()]/g, function (ch) {
                    return '%' + ch.charCodeAt(0).toString(16);
                }) + ')';
                var inserted = replacePlaceholder(markdown);
                if (allMedia.indexOf(data.id) === -1) allMedia.push(data.id);
                setUploadStatus(inserted ? '✓ Bild uppladdad och infogad.'
                    : 'Bilden laddades upp till ' + data.url + ', men infogningsplatsen har ändrats.', !inserted);
            } catch (err) {
                replacePlaceholder(originalText);
                setUploadStatus('Kunde inte ladda upp bilden: ' + err.message, true);
            } finally {
                if (!model.isDisposed()) model.deltaDecorations(decorationIds, []);
                updateUploadState(-1);
            }
        }

        // ── Dra-och-släpp ────────────────────────────────────────────────
        // Byggs dynamiskt (inte statisk HTML i mallen) och läggs till EFTER
        // att Monaco redan initierats — monaco.editor.create() äger sin
        // container och kan tömma/skriva över befintligt innehåll i den
        // vid start, vilket annars skulle riskera att radera ett statiskt
        // dropzone-element innan vi ens hunnit koppla in lyssnare på det.
        var dropzone = document.createElement('div');
        dropzone.id = 'media-dropzone';
        dropzone.className = 'gbg-editor-dropzone';
        dropzone.hidden = true;
        dropzone.innerHTML = '<span>📎 Släpp bilden här för att ladda upp</span>';
        container.appendChild(dropzone);

        function eventHasFiles(e) {
            return !!(e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1);
        }
        var dragDepth = 0;
        container.addEventListener('dragenter', function (e) {
            if (!eventHasFiles(e)) return;
            e.preventDefault();
            dragDepth++;
            dropzone.hidden = false;
        }, true);
        container.addEventListener('dragover', function (e) {
            if (!eventHasFiles(e)) return;
            e.preventDefault(); // krävs för att webbläsaren ska tillåta ett drop-event alls
            e.dataTransfer.dropEffect = 'copy';
        }, true);
        container.addEventListener('dragleave', function (e) {
            if (!eventHasFiles(e)) return;
            dragDepth = Math.max(0, dragDepth - 1);
            if (dragDepth === 0) dropzone.hidden = true;
        }, true);
        container.addEventListener('drop', function (e) {
            if (!eventHasFiles(e)) return;
            e.preventDefault();
            e.stopPropagation(); // hindra Monaco från att själv försöka hantera droppet
            dragDepth = 0;
            dropzone.hidden = true;
            var files  = Array.prototype.slice.call(e.dataTransfer.files || []);
            var images = files.filter(function (f) { return f.type && f.type.indexOf('image/') === 0; });
            console.log('[RuneWiki] Fil(er) släppta:', files.map(function (f) { return f.name + ' (' + f.type + ')'; }));
            if (!images.length) {
                setUploadStatus('✗ Släppt fil är ingen bild.', true);
                return;
            }
            images.forEach(uploadImageFile);
        }, true);

        // Capture at document level, before Monaco's container-level paste controller.
        // Text-only pastes and pastes outside this editor remain untouched.
        function pasteImages(e) {
            if (!editor.hasTextFocus() || !container.contains(e.target)) return;
            var cd = e.clipboardData;
            if (!cd) return;
            var images = Array.from(cd.files || []).filter(function (file) {
                return file.type.indexOf('image/') === 0;
            });
            if (!images.length) {
                images = Array.from(cd.items || []).filter(function (item) {
                    return item.kind === 'file' && item.type.indexOf('image/') === 0;
                }).map(function (item) { return item.getAsFile(); }).filter(Boolean);
            }
            if (!images.length) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            images.forEach(uploadImageFile);
        }
        document.addEventListener('paste', pasteImages, true);
        editor.onDidDispose(function () { document.removeEventListener('paste', pasteImages, true); });

        // ── "Bläddra i media" — inbäddad bildväljare (modal) ──────────────
        // Ett mindre, centrerat dialogfönster (inte en popup/nytt fönster)
        // byggt direkt från $allMedia — visar bara bildfiler (samma
        // filändelse-lista som MediaId::isImage() i PHP). Val infogar
        // {{namespace:fil.png}} — den relativa embed-syntax Parser.php
        // löser upp till /images/... (flyttar sig alltså inte om sajten
        // byter domän). Markörens position kommer ihåg (samma sticky-
        // decoration-teknik som klistra-in-bild ovan) så infogningen
        // hamnar rätt även om man hunnit klicka någon annanstans i
        // editorn medan dialogen var öppen.
        var IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'bmp', 'ico'];
        function isImageMediaId(id) {
            var m = /\.([a-z0-9]+)$/i.exec(id);
            return !!m && IMAGE_EXTENSIONS.indexOf(m[1].toLowerCase()) !== -1;
        }
        /** "ns:fil.png" -> "/images/ns/fil.png", "fil.png" -> "/images/fil.png". */
        function mediaThumbUrl(id) {
            var i = id.indexOf(':');
            if (i === -1) return '/images/' + encodeURIComponent(id);
            return '/images/' + id.slice(0, i).split(':').map(encodeURIComponent).join('/')
                + '/' + encodeURIComponent(id.slice(i + 1));
        }

        var pickerModal = document.createElement('div');
        pickerModal.className = 'gbg-media-picker-modal';
        pickerModal.hidden = true;
        pickerModal.innerHTML =
            '<div class="gbg-media-picker-dialog" role="dialog" aria-modal="true" aria-label="Välj en bild">' +
              '<div class="gbg-media-picker-header">' +
                '<input type="search" class="gbg-media-picker-search" placeholder="Sök bland bilder…">' +
                '<button type="button" class="gbg-media-picker-close" aria-label="Stäng">' +
                  '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>' +
                '</button>' +
              '</div>' +
              '<div class="gbg-media-picker-grid"></div>' +
            '</div>';
        document.body.appendChild(pickerModal);

        var pickerGrid    = pickerModal.querySelector('.gbg-media-picker-grid');
        var pickerSearch  = pickerModal.querySelector('.gbg-media-picker-search');
        var pickerRangeIds = null;

        function escapeAttr(s) { return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;'); }

        function renderPickerGrid(filter) {
            var lower  = (filter || '').toLowerCase();
            var images = allMedia.filter(isImageMediaId);
            var shown  = lower ? images.filter(function (id) { return id.toLowerCase().indexOf(lower) !== -1; }) : images;

            if (!images.length) {
                pickerGrid.innerHTML = '<p class="gbg-media-picker-empty">Inga bilder uppladdade ännu. Ladda upp via <a href="/images" target="_blank" rel="noopener">Mediahanterare</a>.</p>';
            } else if (!shown.length) {
                pickerGrid.innerHTML = '<p class="gbg-media-picker-empty">Inga bilder matchar sökningen.</p>';
            } else {
                pickerGrid.innerHTML = shown.map(function (id) {
                    return '<button type="button" class="gbg-media-picker-item" data-id="' + escapeAttr(id) + '" title="' + escapeAttr(id) + '">'
                        + '<img src="' + mediaThumbUrl(id) + '" alt="" loading="lazy">'
                        + '</button>';
                }).join('');
            }
        }

        /** "ns:skarmavbild-2024.png" -> "Skarmavbild 2024" — en rimlig startpunkt för alt-texten. */
        function altTextFromId(id) {
            var filename = id.indexOf(':') === -1 ? id : id.slice(id.lastIndexOf(':') + 1);
            var base = filename.replace(/\.[a-z0-9]+$/i, '').replace(/[_-]+/g, ' ').trim();
            return base ? base.charAt(0).toUpperCase() + base.slice(1) : 'bild';
        }

        function insertMediaId(id) {
            var model = editor.getModel();
            var range = (pickerRangeIds && model.getDecorationRange(pickerRangeIds[0])) || editor.getSelection();

            // Alt-text skrivs alltid med — dels för tillgänglighet, dels så
            // AI:n som läser sidans innehåll (t.ex. /chat) faktiskt vet vad
            // bilden föreställer, inte bara filnamnet. Bildväljaren använder
            // wiki-syntax; urklippsuppladdningar använder ![alt](url).
            var alt  = altTextFromId(id);
            var text = '{{' + id + '|' + alt + '}}';
            editor.executeEdits('media-picker', [{ range: range, text: text }]);
            if (pickerRangeIds) { model.deltaDecorations(pickerRangeIds, []); pickerRangeIds = null; }
            pickerModal.hidden = true;
            editor.focus();

            // Markerar den infogade alt-texten direkt så man kan skriva
            // över den med en riktig beskrivning utan att behöva leta upp
            // och markera den för hand.
            var altStartCol = range.startColumn + ('{{' + id + '|').length;
            editor.setSelection(new monaco.Range(
                range.startLineNumber, altStartCol,
                range.startLineNumber, altStartCol + alt.length
            ));
        }

        function openPickerModal() {
            var selection = editor.getSelection();
            var model     = editor.getModel();
            pickerRangeIds = model.deltaDecorations(pickerRangeIds || [], [{
                range: selection,
                options: { stickiness: monaco.editor.TrackedRangeStickiness.NeverGrowsWhenTypingAtEdges },
            }]);
            pickerSearch.value = '';
            renderPickerGrid('');
            pickerModal.hidden = false;
            pickerSearch.focus();
        }

        var mediaPickerBtn = document.getElementById('media-picker-btn');
        if (mediaPickerBtn) mediaPickerBtn.addEventListener('click', openPickerModal);

        pickerSearch.addEventListener('input', function () { renderPickerGrid(pickerSearch.value); });
        pickerModal.addEventListener('click', function (e) {
            var item = e.target.closest('.gbg-media-picker-item');
            if (item) { insertMediaId(item.dataset.id); return; }
            if (e.target === pickerModal || e.target.closest('.gbg-media-picker-close')) {
                pickerModal.hidden = true;
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !pickerModal.hidden) pickerModal.hidden = true;
        });

        // ── Form + tema-sync ────────────────────────────────────────────
        form.addEventListener('submit', function (e) {
            if (pendingUploads > 0) {
                e.preventDefault();
                setUploadStatus('Vänta tills bilduppladdningen är klar.');
                return;
            }
            bodyField.value = editor.getValue();
        });

        new MutationObserver(function () {
            monaco.editor.setTheme(getTheme());
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    });
})();
</script>
