/* Document conversion stays in the browser; only the normal Save submits content. */
window.WikiEditorTools = function (editor) {
    const el = name => document.getElementById('editor-' + name);
    const form = document.getElementById('edit-form');
    const dialog = el('import-dialog'), preview = el('import-preview'), status = el('import-status');
    const insert = el('import-insert'), replace = el('import-replace'), fileInput = el('import-file');
    let generation = 0;
    function fullscreen(active) {
        form.classList.toggle('is-fullscreen', active);
        document.body.classList.toggle('gbg-editor-fullscreen', active);
        el('fullscreen-btn').setAttribute('aria-pressed', String(active));
        el('fullscreen-btn').textContent = active ? 'Avsluta helskärm' : 'Helskärm';
        requestAnimationFrame(() => { editor.layout(); editor.focus(); });
    }
    el('fullscreen-btn').disabled = false;
    el('fullscreen-btn').onclick = () => fullscreen(!form.classList.contains('is-fullscreen'));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !document.querySelector('dialog[open], .gbg-media-picker-modal:not([hidden])') && form.classList.contains('is-fullscreen')) fullscreen(false);
    }, true);
    el('import-btn').disabled = false;
    el('import-btn').onclick = () => dialog.showModal();
    el('import-close').onclick = () => dialog.close();
    dialog.addEventListener('close', () => {
        generation++;
        fileInput.value = '';
        if (preview.disabled) { preview.disabled = false; status.textContent = 'Importen avbröts. Välj en fil för att försöka igen.'; }
        editor.focus();
    });
    function validPreview() { insert.disabled = replace.disabled = !preview.value.trim() || preview.value.length > 2000000; }
    preview.addEventListener('input', validPreview);
    const libraries = new Map();
    function amd(url) {
        if (!libraries.has(url)) libraries.set(url, new Promise((resolve, reject) => window.require([url], resolve, reject)).catch(error => { libraries.delete(url); throw error; }));
        return libraries.get(url);
    }
    async function htmlToMarkdown(html) {
        const Turndown = await amd('https://cdn.jsdelivr.net/npm/turndown@7.2.5/lib/turndown.browser.umd.js');
        // Template content is inert: imported markup is never inserted into the page.
        const template = document.createElement('template');
        template.innerHTML = html;
        template.content.querySelectorAll('script,style,iframe,object,embed,form,link,meta,svg,math').forEach(n => n.remove());
        template.content.querySelectorAll('img').forEach(n => n.replaceWith(document.createTextNode(n.alt ? '[Bild: ' + n.alt + ']' : '')));
        template.content.querySelectorAll('a').forEach(n => {
            const href = n.getAttribute('href') || '';
            if (/^[\s\S]*?:/.test(href) && !/^(https?:|mailto:)/i.test(href.trim())) n.removeAttribute('href');
        });
        const converter = new Turndown({ headingStyle: 'atx', codeBlockStyle: 'fenced', bulletListMarker: '-' });
        converter.addRule('markdownTable', {
            filter: 'table',
            replacement: (_, table) => {
                const rows = Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.textContent.trim().replace(/\s+/g, ' ').replace(/\|/g, '\\|')));
                if (!rows.length) return '';
                const columns = Math.max(...rows.map(row => row.length));
                const line = row => '| ' + Array.from({ length: columns }, (_, i) => row[i] || '').join(' | ') + ' |';
                return '\n\n' + [line(rows[0]), line(Array(columns).fill('---')), ...rows.slice(1).map(line)].join('\n') + '\n\n';
            }
        });
        return converter.turndown(template.innerHTML);
    }
    async function convert(file, report) {
        const extension = file.name.split('.').pop().toLowerCase();
        if (!['md', 'markdown', 'txt', 'html', 'htm', 'docx', 'pdf'].includes(extension)) throw new Error('Filformatet stöds inte. Välj .md, .txt, .html, .docx eller .pdf.');
        if (file.size > 25 * 1024 * 1024) throw new Error('Filen är för stor. Maximal storlek är 25 MB.');
        if (['md', 'markdown', 'txt'].includes(extension)) return { text: (await file.text()).replace(/^\uFEFF/, ''), note: '' };
        if (['html', 'htm'].includes(extension)) return { text: await htmlToMarkdown(await file.text()), note: 'Kontrollera tabeller och länkar. Bilder importeras inte.' };
        if (extension === 'docx') {
            const mammoth = await amd('https://cdn.jsdelivr.net/npm/mammoth@1.13.0/mammoth.browser.min.js');
            const result = await mammoth.convertToHtml({ arrayBuffer: await file.arrayBuffer() }, { externalFileAccess: false, convertImage: () => [] });
            return { text: await htmlToMarkdown(result.value), note: 'Bilder importeras inte.' + (result.messages.length ? ' Dokumentet innehåller formatering som kan behöva justeras.' : '') };
        }
        const pdfjs = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@6.5.438/build/pdf.min.mjs');
        pdfjs.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@6.5.438/build/pdf.worker.min.mjs';
        const task = pdfjs.getDocument({ data: new Uint8Array(await file.arrayBuffer()), isEvalSupported: false });
        try {
            const pdf = await task.promise;
            if (pdf.numPages > 200) throw new Error('PDF-filen har fler än 200 sidor. Dela upp den före import.');
            const pages = [];
            let size = 0, empty = 0;
            for (let number = 1; number <= pdf.numPages; number++) {
                report('Läser PDF-sida ' + number + ' av ' + pdf.numPages + '…');
                const page = await pdf.getPage(number), content = await page.getTextContent();
                let text = '', y = null;
                for (const item of content.items) {
                    if (!('str' in item)) continue;
                    if (y !== null && Math.abs(item.transform[5] - y) > 3 && !text.endsWith('\n')) text += '\n';
                    text += item.str + (item.hasEOL ? '\n' : ' ');
                    y = item.transform[5];
                }
                text = text.trim();
                if (!text) empty++;
                size += text.length;
                if (size > 2000000) throw new Error('Den konverterade texten är för stor. Dela upp dokumentet.');
                // Escape Markdown control characters: PDF provides plain text, not markup.
                pages.push(text.replace(/([\\`*_{}\[\]<>#|])/g, '\\$1'));
                page.cleanup();
            }
            if (empty === pdf.numPages) throw new Error('PDF-filen saknar läsbar text. Skannade dokument behöver OCR före import.');
            return { text: pages.filter(Boolean).join('\n\n---\n\n'), note: 'PDF importerad som text. Kontrollera läsordning, tabeller och spalter.' + (empty ? ' ' + empty + ' sidor saknade läsbar text och hoppades över.' : '') };
        } finally { await task.destroy(); }
    }
    fileInput.onchange = async () => {
        const file = fileInput.files[0]; if (!file) return;
        const current = ++generation;
        preview.value = ''; preview.disabled = true; insert.disabled = replace.disabled = true;
        status.textContent = 'Konverterar ' + file.name + '…';
        try {
            const result = await convert(file, message => { if (current === generation) status.textContent = message; });
            if (current !== generation) return;
            if (!result.text.trim()) throw new Error('Filen innehåller ingen text att importera.');
            if (result.text.length > 2000000) throw new Error('Den konverterade texten är för stor. Dela upp dokumentet.');
            preview.value = result.text;
            status.textContent = 'Redo att infoga. ' + result.note;
            validPreview();
        } catch (error) {
            if (current === generation) status.textContent = 'Kunde inte importera filen: ' + error.message;
        } finally {
            if (current === generation) { preview.disabled = false; fileInput.value = ''; }
        }
    };
    function apply(all) {
        if (!preview.value.trim() || preview.value.length > 2000000) return;
        if (all && !confirm('Ersätt hela innehållet i editorn, inklusive eventuell YAML-metadata? Du kan ångra med Ctrl+Z.')) return;
        const range = all ? editor.getModel().getFullModelRange() : editor.getSelection();
        editor.pushUndoStop();
        editor.executeEdits('document-import', [{ range, text: preview.value }]);
        editor.pushUndoStop();
        dialog.close();
    }
    insert.onclick = () => apply(false);
    replace.onclick = () => apply(true);
};
