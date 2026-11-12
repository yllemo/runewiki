(() => {
    const dialog = document.getElementById('svg-editor'); if (!dialog) return;
    const el = name => document.getElementById('svg-' + name), source = el('source'), status = el('status');
    const samples = {
        "card": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 840 320\" width=\"840\" height=\"320\">\n  <!-- Ändra texter och färger. Flytta former med x/y och anpassa viewBox vid behov. -->\n  <rect width=\"840\" height=\"320\" rx=\"16\" fill=\"#ffffff\"/>\n  <g font-family=\"sans-serif\">\n  <text x=\"28\" y=\"44\" font-size=\"26\" fill=\"#0f172a\">Kort sammanfattning</text>\n  <rect x=\"24\" y=\"68\" width=\"792\" height=\"224\" rx=\"12\" fill=\"#eff6ff\" stroke=\"#cbd5e1\"/>\n  <text x=\"48\" y=\"106\" font-size=\"20\" fill=\"#1d4ed8\">Syfte</text>\n  <text x=\"48\" y=\"138\" font-size=\"18\" fill=\"#334155\">Samla teamets rutiner på en gemensam plats.</text>\n  <text x=\"48\" y=\"184\" font-size=\"20\" fill=\"#1d4ed8\">Det här behöver du veta</text>\n  <text x=\"48\" y=\"216\" font-size=\"18\" fill=\"#334155\">1. Börja med checklistan för din roll.</text>\n  <text x=\"48\" y=\"248\" font-size=\"18\" fill=\"#334155\">2. Kontakta sidans ansvariga om något saknas.</text>\n  <text x=\"48\" y=\"278\" font-size=\"14\" fill=\"#334155\">Ansvarig: Kunskapsteamet</text>\n  </g>\n</svg>",
        "flow": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 840 320\" width=\"840\" height=\"320\">\n  <!-- Ändra texter och färger. Flytta former med x/y och anpassa viewBox vid behov. -->\n  <rect width=\"840\" height=\"320\" rx=\"16\" fill=\"#ffffff\"/>\n  <g font-family=\"sans-serif\">\n  <text x=\"28\" y=\"44\" font-size=\"26\" fill=\"#0f172a\">Från utkast till publicerad artikel</text>\n  <rect x=\"24\" y=\"100\" width=\"248\" height=\"170\" rx=\"12\" fill=\"#eff6ff\" stroke=\"#cbd5e1\"/>\n  <text x=\"42\" y=\"140\" font-size=\"22\" fill=\"#1d4ed8\">1. Förbered</text>\n  <text x=\"42\" y=\"182\" font-size=\"17\" fill=\"#334155\">Samla underlag</text>\n  <text x=\"42\" y=\"236\" font-size=\"15\" fill=\"#334155\">Ansvar: Författare</text>\n  <path d=\"M276 185 h16 m-6 -6 l6 6 -6 6\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"2\"/>\n  <rect x=\"296\" y=\"100\" width=\"248\" height=\"170\" rx=\"12\" fill=\"#eff6ff\" stroke=\"#cbd5e1\"/>\n  <text x=\"314\" y=\"140\" font-size=\"22\" fill=\"#1d4ed8\">2. Granska</text>\n  <text x=\"314\" y=\"182\" font-size=\"17\" fill=\"#334155\">Kontrollera innehållet</text>\n  <text x=\"314\" y=\"236\" font-size=\"15\" fill=\"#334155\">Ansvar: Sakägare</text>\n  <path d=\"M548 185 h16 m-6 -6 l6 6 -6 6\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"2\"/>\n  <rect x=\"568\" y=\"100\" width=\"248\" height=\"170\" rx=\"12\" fill=\"#eff6ff\" stroke=\"#cbd5e1\"/>\n  <text x=\"586\" y=\"140\" font-size=\"22\" fill=\"#1d4ed8\">3. Publicera</text>\n  <text x=\"586\" y=\"182\" font-size=\"17\" fill=\"#334155\">Dela i wikin</text>\n  <text x=\"586\" y=\"236\" font-size=\"15\" fill=\"#334155\">Ansvar: Redaktör</text>\n  </g>\n</svg>",
        "architecture": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 840 320\" width=\"840\" height=\"320\">\n  <!-- Ändra texter och färger. Flytta former med x/y och anpassa viewBox vid behov. -->\n  <rect width=\"840\" height=\"320\" rx=\"16\" fill=\"#ffffff\"/>\n  <g font-family=\"sans-serif\">\n  <text x=\"28\" y=\"44\" font-size=\"26\" fill=\"#0f172a\">Systemöversikt</text>\n  <rect x=\"24\" y=\"70\" width=\"792\" height=\"64\" rx=\"12\" fill=\"#fef3c7\" stroke=\"#cbd5e1\"/>\n  <text x=\"44\" y=\"98\" font-size=\"19\" fill=\"#0f172a\">Användare</text>\n  <text x=\"250\" y=\"109\" font-size=\"18\" fill=\"#334155\">Medarbetare och redaktörer</text>\n  <path d=\"M420 134 v14\" stroke=\"#64748b\" stroke-width=\"2\"/>\n  <rect x=\"24\" y=\"148\" width=\"792\" height=\"64\" rx=\"12\" fill=\"#dbeafe\" stroke=\"#cbd5e1\"/>\n  <text x=\"44\" y=\"176\" font-size=\"19\" fill=\"#0f172a\">Applikation</text>\n  <text x=\"250\" y=\"187\" font-size=\"18\" fill=\"#334155\">RuneWiki · artiklar, sökning och media</text>\n  <path d=\"M420 212 v14\" stroke=\"#64748b\" stroke-width=\"2\"/>\n  <rect x=\"24\" y=\"226\" width=\"792\" height=\"64\" rx=\"12\" fill=\"#dcfce7\" stroke=\"#cbd5e1\"/>\n  <text x=\"44\" y=\"254\" font-size=\"19\" fill=\"#0f172a\">Lagring</text>\n  <text x=\"250\" y=\"265\" font-size=\"18\" fill=\"#334155\">Markdown-filer · bilder · versionshistorik</text>\n  </g>\n</svg>",
        "timeline": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 840 320\" width=\"840\" height=\"320\">\n  <!-- Ändra texter och färger. Flytta former med x/y och anpassa viewBox vid behov. -->\n  <rect width=\"840\" height=\"320\" rx=\"16\" fill=\"#ffffff\"/>\n  <g font-family=\"sans-serif\">\n  <text x=\"28\" y=\"44\" font-size=\"26\" fill=\"#0f172a\">Plan för dokumentationen</text>\n  <path d=\"M72 160 H768\" stroke=\"#94a3b8\" stroke-width=\"4\"/>\n  <circle cx=\"72\" cy=\"160\" r=\"9\" fill=\"#2563eb\"/>\n  <g text-anchor=\"middle\">\n  <text x=\"72\" y=\"126\" font-size=\"17\" fill=\"#1d4ed8\">Vecka 1</text>\n  <text x=\"72\" y=\"208\" font-size=\"20\" fill=\"#334155\">Planera</text>\n  <text x=\"72\" y=\"238\" font-size=\"13\" fill=\"#334155\">Mål och omfattning</text>\n  </g>\n  <circle cx=\"304\" cy=\"160\" r=\"9\" fill=\"#2563eb\"/>\n  <g text-anchor=\"middle\">\n  <text x=\"304\" y=\"126\" font-size=\"17\" fill=\"#1d4ed8\">Vecka 2</text>\n  <text x=\"304\" y=\"208\" font-size=\"20\" fill=\"#334155\">Skriv</text>\n  <text x=\"304\" y=\"238\" font-size=\"13\" fill=\"#334155\">Första utkast</text>\n  </g>\n  <circle cx=\"536\" cy=\"160\" r=\"9\" fill=\"#2563eb\"/>\n  <g text-anchor=\"middle\">\n  <text x=\"536\" y=\"126\" font-size=\"17\" fill=\"#1d4ed8\">Vecka 3</text>\n  <text x=\"536\" y=\"208\" font-size=\"20\" fill=\"#334155\">Granska</text>\n  <text x=\"536\" y=\"238\" font-size=\"13\" fill=\"#334155\">Feedback från teamet</text>\n  </g>\n  <circle cx=\"768\" cy=\"160\" r=\"9\" fill=\"#2563eb\"/>\n  <g text-anchor=\"middle\">\n  <text x=\"768\" y=\"126\" font-size=\"17\" fill=\"#1d4ed8\">Vecka 4</text>\n  <text x=\"768\" y=\"208\" font-size=\"20\" fill=\"#334155\">Lansera</text>\n  <text x=\"768\" y=\"238\" font-size=\"13\" fill=\"#334155\">Publicering</text>\n  </g>\n  </g>\n</svg>",
        "roles": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 840 320\" width=\"840\" height=\"320\">\n  <!-- Ändra texter och färger. Flytta former med x/y och anpassa viewBox vid behov. -->\n  <rect width=\"840\" height=\"320\" rx=\"16\" fill=\"#ffffff\"/>\n  <g font-family=\"sans-serif\">\n  <text x=\"28\" y=\"44\" font-size=\"26\" fill=\"#0f172a\">Vem gör vad?</text>\n  <rect x=\"24\" y=\"68\" width=\"792\" height=\"44\" rx=\"12\" fill=\"#dbeafe\" stroke=\"#cbd5e1\"/>\n  <text x=\"44\" y=\"97\" font-size=\"18\" fill=\"#0f172a\">Aktivitet</text>\n  <text x=\"380\" y=\"97\" font-size=\"18\" fill=\"#0f172a\">Ansvarig</text>\n  <text x=\"610\" y=\"97\" font-size=\"18\" fill=\"#0f172a\">Resultat</text>\n  <rect x=\"24\" y=\"122\" width=\"792\" height=\"50\" rx=\"12\" fill=\"#f8fafc\" stroke=\"#cbd5e1\"/>\n  <text x=\"44\" y=\"154\" font-size=\"18\" fill=\"#334155\">Uppdatera artikel</text>\n  <text x=\"380\" y=\"154\" font-size=\"18\" fill=\"#334155\">Författare</text>\n  <text x=\"610\" y=\"154\" font-size=\"18\" fill=\"#334155\">Utkast</text>\n  <rect x=\"24\" y=\"180\" width=\"792\" height=\"50\" rx=\"12\" fill=\"#f8fafc\" stroke=\"#cbd5e1\"/>\n  <text x=\"44\" y=\"212\" font-size=\"18\" fill=\"#334155\">Kontrollera fakta</text>\n  <text x=\"380\" y=\"212\" font-size=\"18\" fill=\"#334155\">Sakägare</text>\n  <text x=\"610\" y=\"212\" font-size=\"18\" fill=\"#334155\">Godkänt innehåll</text>\n  <rect x=\"24\" y=\"238\" width=\"792\" height=\"50\" rx=\"12\" fill=\"#f8fafc\" stroke=\"#cbd5e1\"/>\n  <text x=\"44\" y=\"270\" font-size=\"18\" fill=\"#334155\">Publicera och informera</text>\n  <text x=\"380\" y=\"270\" font-size=\"18\" fill=\"#334155\">Redaktör</text>\n  <text x=\"610\" y=\"270\" font-size=\"18\" fill=\"#334155\">Aktuell wiki</text>\n  </g>\n</svg>"
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
    window.WikiSvgEditor = { open, bindEditor(value) { editor = value; } };
    function fillExisting() {
        const ids = new Set([...(window.WIKI_MEDIA || []), ...Array.from(document.querySelectorAll('[data-svg-open]'), button => button.dataset.svgOpen)]);
        el('existing').replaceChildren(new Option('Välj fil…', ''));
        for (const id of ids) if (id.toLowerCase().endsWith('.svg')) el('existing').add(new Option(id, id));
    }
    // The editor publishes WIKI_MEDIA after this shared script has loaded.
    document.addEventListener('DOMContentLoaded', fillExisting);
    el('existing').onchange = () => { const id = el('existing').value; if (id && !busy && confirmDiscard()) open(id); el('existing').value = ''; };
})();
