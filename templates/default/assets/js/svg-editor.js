(() => {
    const dialog = document.getElementById('svg-editor'); if (!dialog) return;
    const el = name => document.getElementById('svg-' + name), source = el('source'), status = el('status');
    const samples = {
        card: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 160" width="480" height="160">\n  <rect x="2" y="2" width="476" height="156" rx="16" fill="#e8f4fc" stroke="#0077bc" stroke-width="2"/>\n  <text x="28" y="65" font-family="sans-serif" font-size="26" fill="#00446b">Min rubrik</text>\n  <text x="28" y="108" font-family="sans-serif" font-size="18" fill="#333333">Beskrivning eller budskap</text>\n</svg>',
        flow: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 560 160" width="560" height="160">\n  <defs><marker id="arrow" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 Z" fill="#0077bc"/></marker></defs>\n  <rect x="20" y="40" width="180" height="80" rx="10" fill="#e8f4fc" stroke="#0077bc"/>\n  <path d="M200 80 H350" stroke="#0077bc" stroke-width="3" marker-end="url(#arrow)"/>\n  <rect x="360" y="40" width="180" height="80" rx="10" fill="#e8f4fc" stroke="#0077bc"/>\n  <text x="110" y="86" text-anchor="middle" font-family="sans-serif" font-size="20">Start</text>\n  <text x="450" y="86" text-anchor="middle" font-family="sans-serif" font-size="20">Resultat</text>\n</svg>',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="128" height="128">\n  <circle cx="32" cy="32" r="28" fill="#0077bc"/>\n  <path d="M18 32 L28 42 L46 22" fill="none" stroke="#ffffff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>\n</svg>'
    };
    let editor = null, range = null, loadedId = '', revision = '', baseline = '', blobUrl = null, timer, busy = false, sequence = 0, mediaChanged = false;
    const message = text => { status.textContent = text; };
    function setBusy(value) {
        busy = value;
        ['source', 'id', 'existing', 'example', 'local', 'save', 'insert-code', 'insert-file'].forEach(name => { el(name).disabled = value; });
    }
    function preview() {
        clearTimeout(timer);
        if (new Blob([source.value]).size > 1048576) { message('SVG får vara högst 1 MB.'); el('preview').removeAttribute('src'); return false; }
        const parsed = new DOMParser().parseFromString(source.value, 'image/svg+xml');
        if (parsed.querySelector('parsererror') || parsed.documentElement.localName !== 'svg') {
            el('preview').removeAttribute('src'); message('SVG-koden är inte giltig XML. Kontrollera taggar och attribut.'); return false;
        }
        if (blobUrl) URL.revokeObjectURL(blobUrl);
        blobUrl = URL.createObjectURL(new Blob([source.value], { type: 'image/svg+xml' }));
        el('preview').src = blobUrl; message('Förhandsvisning uppdaterad.'); return true;
    }
    function confirmDiscard() { return source.value === baseline || confirm('Stäng utan att spara eller infoga dina SVG-ändringar?'); }
    function close() { if (!busy && confirmDiscard()) dialog.close(); }
    el('close').onclick = close;
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    dialog.addEventListener('close', () => { sequence++; clearTimeout(timer); if (blobUrl) URL.revokeObjectURL(blobUrl); blobUrl = null; if (editor) editor.focus(); else if (mediaChanged) location.reload(); });
    source.oninput = () => { clearTimeout(timer); timer = setTimeout(preview, 350); };
    source.onkeydown = event => {
        if (event.ctrlKey && event.code === 'Space') { event.preventDefault(); el('example').focus(); try { el('example').showPicker?.(); } catch (_) { /* Native select still works with arrows. */ } }
        if (event.key === 'Tab') { event.preventDefault(); source.setRangeText('  ', source.selectionStart, source.selectionEnd, 'end'); source.dispatchEvent(new Event('input')); }
    };
    el('refresh').onclick = preview;
    el('example').onchange = () => {
        const sample = samples[el('example').value];
        if (sample && (source.value === baseline || confirm('Ersätt SVG-koden med exemplet?'))) { source.value = sample; preview(); }
        el('example').value = '';
    };
    el('local').onclick = () => el('file').click();
    el('file').onchange = async () => {
        const file = el('file').files[0]; if (!file) return;
        const current = ++sequence;
        try {
            if (file.size > 1048576) throw new Error('SVG får vara högst 1 MB.');
            const text = await file.text();
            if (current !== sequence) return;
            if (source.value !== baseline && !confirm('Ersätt SVG-koden med filens innehåll?')) return;
            source.value = text; el('id').value = (dialog.dataset.namespace ? dialog.dataset.namespace + ':' : '') + file.name;
            loadedId = ''; revision = ''; preview();
        } catch (error) { message(error.message); }
        el('file').value = '';
    };
    async function api(options) {
        const response = await fetch('/?do=svg' + (options.id ? '&id=' + encodeURIComponent(options.id) : ''), options.body ? { method: 'POST', body: options.body, credentials: 'same-origin' } : { credentials: 'same-origin', cache: 'no-store' });
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'SVG-anropet misslyckades.');
        return data;
    }
    function insert(text) {
        if (!editor) return;
        editor.pushUndoStop(); editor.executeEdits('svg-editor', [{ range: range || editor.getSelection(), text }]); editor.pushUndoStop();
        baseline = source.value; dialog.close();
    }
    async function save(andInsert) {
        if (busy || !preview()) return;
        setBusy(true);
        const id = el('id').value.trim(), text = source.value;
        el('save').disabled = el('insert-file').disabled = true;
        try {
            const body = new FormData(); body.set('id', id); body.set('source', text); body.set('revision', id === loadedId ? revision : ''); body.set('csrf_token', dialog.dataset.csrf);
            const result = await api({ body });
            loadedId = result.id; revision = result.revision; baseline = text;
            mediaChanged = true;
            (window.WIKI_MEDIA ||= []).push(result.id); fillExisting();
            message('Sparad: ' + result.id);
            // Refresh existing thumbnails without changing the stored media URL.
            document.querySelectorAll('.wiki-media img').forEach(img => { if (new URL(img.src, location.href).pathname === result.url) img.src = result.url + '?v=' + Date.now(); });
            if (andInsert) insert('![' + result.id.split(':').pop().replace(/[\[\]]/g, '') + '](' + result.url + ')');
        } catch (error) { message(error.message); }
        finally { setBusy(false); }
    }
    el('save').onclick = () => save(false);
    el('insert-file').onclick = () => save(true);
    el('insert-code').onclick = () => { if (!busy && preview()) insert('\n\n```svg\n' + source.value.trim() + '\n```\n\n'); };
    async function open(id = '', namespace = dialog.dataset.namespace) {
        const current = ++sequence;
        loadedId = ''; revision = ''; range = editor?.getSelection();
        let text = samples.card;
        if (editor) {
            const model = editor.getModel();
            const selected = model.getValueInRange(range).trim();
            if (selected.startsWith('<svg')) text = selected;
            else {
                const offset = model.getOffsetAt(range.getStartPosition());
                for (const match of model.getValue().matchAll(/^```svg[^\n]*\n([\s\S]*?)^```[ \t]*$/gm)) {
                    if (offset >= match.index && offset <= match.index + match[0].length) {
                        const start = model.getPositionAt(match.index), end = model.getPositionAt(match.index + match[0].length);
                        range = { startLineNumber: start.lineNumber, startColumn: start.column, endLineNumber: end.lineNumber, endColumn: end.column };
                        text = match[1].trim(); break;
                    }
                }
            }
        }
        source.value = baseline = text;
        el('id').value = id || (namespace ? namespace + ':' : '') + 'diagram.svg';
        el('insert-code').hidden = el('insert-file').hidden = !editor;
        if (!dialog.open) dialog.showModal(); preview();
        if (id) {
            setBusy(true);
            message('Läser SVG…');
            try {
                const result = await api({ id }); if (current !== sequence) return;
                source.value = baseline = result.source; loadedId = result.id; revision = result.revision; preview();
            } catch (error) { message(error.message); }
            finally { setBusy(false); }
        }
        source.focus();
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-svg-open], [data-svg-new]');
        if (button) open(button.dataset.svgOpen || '', button.dataset.svgNew ?? dialog.dataset.namespace);
    });
    window.WikiSvgEditor = { bindEditor(value) { editor = value; const button = document.getElementById('editor-svg-btn'); if (button) button.disabled = false; } };
    function fillExisting() {
        const ids = new Set([...(window.WIKI_MEDIA || []), ...Array.from(document.querySelectorAll('[data-svg-open]'), button => button.dataset.svgOpen)]);
        el('existing').replaceChildren(new Option('Välj fil…', ''));
        for (const id of ids) if (id.toLowerCase().endsWith('.svg')) el('existing').add(new Option(id, id));
    }
    // The editor publishes WIKI_MEDIA after this shared script has loaded.
    document.addEventListener('DOMContentLoaded', fillExisting);
    el('existing').onchange = () => { const id = el('existing').value; if (id && !busy && confirmDiscard()) open(id); el('existing').value = ''; };
})();
