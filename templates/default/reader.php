<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= Helpers::e($readerData['title']) ?> – Läs- och exportläge</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='12' fill='%230077bc'/><path d='M9 45V19h7l8 11 8-11h7v26h-7V32l-8 10-8-10v13z' fill='%23fff'/><path d='M46 19h8v13h6L50 45 40 32h6z' fill='%23fff'/></svg>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
<script src="https://www.masswerk.at/mespeak/mespeak.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lamejs@1.2.1/lame.min.js"></script>
<script src="/templates/default/assets/js/html-docx.js"></script>
<style>
:root {
  --gs-blue: #0077bc;
  --bg-color: #FFFFFE;
  --bg-card: #FFFFFE;
  --bg-subtle: #F4F9FC;
  --bg-nav: #F4F9FC;
  --bg-info: #F2F9F9;
  --bg-footer: #F5F5F5;
  --text-color: #333333;
  --text-secondary: #6E6E6E;
  --link-color: #005799;
  --border-color: #979797;
  --border-soft: #d1d9dc;
  --code-bg: #F4F9FC;
  --reader-width: 210mm;
  --reader-font-size: 100%;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

html { background: #e8ecee; }

body {
  font-family: 'Goteborg', Arial, Helvetica, sans-serif;
  font-size: 16px;
  line-height: 1.5;
  color: var(--text-color);
  background: #e8ecee;
  min-height: 100vh;
}

/* ============ Toolbar ============ */
header.toolbar {
  background: var(--gs-blue);
  color: #fff;
  padding: 0.75rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

header.toolbar .brand {
  font-weight: bold;
  font-size: 1.1rem;
  margin-right: auto;
  white-space: nowrap;
}
.btn.mobile-menu-toggle { display: none; }
.toolbar-actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 0.55rem; flex: 1 1 650px; min-width: 0; }

.btn {
  border: none;
  border-radius: 4px;
  padding: 0.5rem 0.9rem;
  font-family: inherit;
  font-size: 0.9rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  transition: filter 0.15s ease, opacity 0.15s ease;
}
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn:not(:disabled):hover { filter: brightness(1.1); }

.btn-light {
  background: #fff;
  color: var(--gs-blue);
  font-weight: bold;
}
.btn-outline {
  background: transparent;
  color: #fff;
  border: 1px solid rgba(255,255,255,0.7);
}

/* Exportmeny */
.export-menu { position: relative; }
.export-menu-panel {
  position: absolute;
  top: calc(100% + 0.55rem);
  right: 0;
  z-index: 190;
  width: 220px;
  padding: 0.35rem;
  background: #fff;
  border: 1px solid var(--border-soft);
  border-radius: 5px;
  box-shadow: 0 6px 22px rgba(0,0,0,0.25);
}
.export-menu-panel[hidden] { display: none; }
.export-menu-panel button {
  display: block;
  width: 100%;
  padding: 0.6rem 0.7rem;
  border: none;
  border-radius: 3px;
  background: transparent;
  color: var(--text-color);
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.export-menu-panel button:hover:not(:disabled) { background: var(--bg-nav); color: var(--gs-blue); }
.export-menu-panel button:disabled { opacity: 0.45; cursor: not-allowed; }

.filename-badge {
  background: rgba(255,255,255,0.15);
  border-radius: 4px;
  padding: 0.35rem 0.7rem;
  font-size: 0.82rem;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: none;
}

/* Lägesväxlare Dokument/Markdown */
/* Inställningspanel */
#settingsPanel {
  position: fixed;
  top: 64px;
  right: 1rem;
  z-index: 150;
  background: var(--bg-color);
  border: 1px solid var(--border-soft);
  border-radius: 4px;
  box-shadow: 0 4px 18px rgba(0,0,0,0.2);
  padding: 1rem 1.2rem;
  width: 300px;
}
#settingsPanel .sp-title {
  font-weight: bold;
  color: var(--gs-blue);
  margin-bottom: 0.7rem;
  font-size: 1rem;
}
#settingsPanel .sp-field {
  display: block;
  font-size: 0.85rem;
  font-weight: bold;
  color: var(--text-color);
  margin-bottom: 0.75rem;
}
#settingsPanel select {
  display: block;
  width: 100%;
  margin-top: 0.3rem;
  padding: 0.4rem 0.5rem;
  font-family: inherit;
  font-size: 0.88rem;
  font-weight: normal;
  color: var(--text-color);
  background: #fff;
  border: 1px solid var(--border-color);
  border-radius: 4px;
}
#settingsPanel .sp-hint {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin: 0.2rem 0 0;
}

/* Dokumentstatistik */
#statsDialog, #ttsDialog {
  border: none;
  border-radius: 6px;
  width: min(440px, calc(100vw - 2rem));
  padding: 0;
  color: var(--text-color);
  box-shadow: 0 12px 40px rgba(0,0,0,0.3);
}
#statsDialog::backdrop { background: rgba(0, 35, 60, 0.48); }
#ttsDialog[open] {
  position: fixed;
  inset: auto 1rem 1rem auto;
  z-index: 180;
  margin: 0;
  max-height: calc(100vh - 2rem);
  overflow: auto;
}
.stats-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.2rem;
  color: #fff;
  background: var(--gs-blue);
}
.stats-head h2 { margin: 0; font-size: 1.05rem; }
.stats-close {
  border: none;
  background: transparent;
  color: #fff;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1px;
  margin: 0;
  background: var(--border-soft);
}
.stats-grid div { padding: 1rem 1.2rem; background: #fff; }
.stats-grid dt {
  color: var(--text-secondary);
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.stats-grid dd {
  margin: 0.2rem 0 0;
  color: var(--gs-blue);
  font-size: 1.45rem;
  font-weight: bold;
}
.tts-body { padding: 1.2rem; }
.tts-field {
  display: block;
  margin-bottom: 1rem;
  font-size: 0.85rem;
  font-weight: bold;
}
.tts-field select {
  display: block;
  width: 100%;
  margin-top: 0.35rem;
  padding: 0.45rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background: #fff;
  font: inherit;
}
.tts-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; }
.tts-actions .btn { background: var(--gs-blue); color: #fff; }
.tts-actions .btn-secondary {
  background: #fff;
  color: var(--gs-blue);
  border: 1px solid var(--gs-blue);
}
.tts-status {
  min-height: 1.5em;
  margin: 1rem 0 0;
  color: var(--text-secondary);
  font-size: 0.85rem;
}
.tts-reading {
  position: relative;
  z-index: 1;
  background: #fff3a6 !important;
  outline: 3px solid #f2c94c;
  outline-offset: 4px;
  border-radius: 2px;
  transition: background 0.2s ease, outline-color 0.2s ease;
  scroll-margin-block: 35vh;
}

/* Checklistor (- [ ] / - [x]) */
#content li.task-item { list-style: none; }
#content ul:has(> li.task-item) { padding-left: 0.5em; }
.task-check {
  display: inline-block;
  width: 0.95em;
  height: 0.95em;
  border: 1.5px solid var(--gs-blue);
  border-radius: 3px;
  margin-right: 0.5em;
  vertical-align: -0.12em;
  position: relative;
  background: #fff;
}
.task-check.checked { background: var(--gs-blue); }
.task-check.checked::after {
  content: '';
  position: absolute;
  left: 0.26em;
  top: 0.05em;
  width: 0.26em;
  height: 0.52em;
  border: solid #fff;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

/* Innehållsförteckning */
#toc {
  margin: 0 0 1.8em;
  background: var(--bg-info);
  border: 1px solid var(--border-soft);
  border-radius: 4px;
  padding: 0.9em 1.1em;
}
#toc .toc-title {
  font-size: 0.72rem;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--gs-blue);
  margin-bottom: 0.5em;
}
#toc ul { list-style: none; margin: 0; padding: 0; }
#toc li { margin: 0.18em 0; font-size: 0.9rem; }
#toc li.toc-h2 { padding-left: 1.2em; }
#toc li.toc-h3 { padding-left: 2.4em; font-size: 0.85rem; }
#toc a { color: var(--link-color); text-decoration: none; }
#toc a:hover { text-decoration: underline; }

/* ============ Dropzone / start ============ */
#dropzone {
  max-width: 820px;
  margin: 3rem auto;
  padding: 3.5rem 2rem;
  border: 2px dashed var(--gs-blue);
  border-radius: 4px;
  background: var(--bg-nav);
  text-align: center;
  cursor: pointer;
  transition: background 0.15s ease;
}
#dropzone.dragover { background: #dceef8; }
#dropzone h2 { color: var(--gs-blue); margin-bottom: 0.75rem; }
#dropzone p { color: var(--text-secondary); margin-bottom: 0.4rem; }
#dropzone .hint { font-size: 0.85rem; }

/* ============ Dokument (A4-känsla) ============ */
#page {
  display: none;
  max-width: var(--reader-width);
  margin: 1.5rem auto 3rem;
  background: var(--bg-color);
  padding: 22mm 20mm;
  box-shadow: 0 2px 14px rgba(0,0,0,0.18);
  border-radius: 2px;
}

/* ============ Markdown-typografi ============ */
#content { word-wrap: break-word; font-size: var(--reader-font-size); }

/* Sökning i dokumentet */
#searchPanel {
  position: fixed;
  top: 72px;
  right: 1rem;
  z-index: 170;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.55rem;
  background: #fff;
  border: 1px solid var(--border-soft);
  border-radius: 5px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.22);
}
#searchPanel[hidden] { display: none; }
#searchInput {
  width: min(280px, 45vw);
  padding: 0.45rem 0.55rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font: inherit;
}
#searchPanel button {
  border: 1px solid var(--gs-blue);
  border-radius: 4px;
  padding: 0.4rem 0.55rem;
  color: var(--gs-blue);
  background: #fff;
  cursor: pointer;
}
#searchCount { min-width: 4.5rem; color: var(--text-secondary); font-size: 0.8rem; text-align: center; }
mark.search-hit { background: #ffe66d; color: inherit; border-radius: 2px; }
mark.search-hit.active { background: #ff9f1c; outline: 2px solid #d87500; }

#content h1, #content h2, #content h3,
#content h4, #content h5, #content h6 {
  color: #1F1F1F;
  line-height: 1.25;
  margin: 1.6em 0 0.6em;
  font-weight: bold;
}
#content > h1:first-child, #content > h2:first-child { margin-top: 0; }

#content h1 {
  font-size: 1.9rem;
  color: var(--gs-blue);
  border-bottom: 3px solid var(--gs-blue);
  padding-bottom: 0.3em;
}
#content h2 {
  font-size: 1.45rem;
  border-bottom: 1px solid var(--border-soft);
  padding-bottom: 0.25em;
}
#content h3 { font-size: 1.2rem; }
#content h4 { font-size: 1.05rem; }
#content h5 { font-size: 0.95rem; }
#content h6 { font-size: 0.9rem; color: var(--text-secondary); }

#content p { margin: 0 0 1em; }
#content a { color: var(--link-color); }

#content ul, #content ol { margin: 0 0 1em; padding-left: 1.8em; }
#content li { margin-bottom: 0.25em; }
#content li > ul, #content li > ol { margin-bottom: 0; margin-top: 0.25em; }

#content blockquote {
  border-left: 4px solid var(--gs-blue);
  background: var(--bg-info);
  padding: 0.7em 1em;
  margin: 0 0 1em;
  color: #444;
  border-radius: 0 4px 4px 0;
}
#content blockquote p:last-child { margin-bottom: 0; }

#content code {
  font-family: 'Consolas', 'Menlo', monospace;
  font-size: 0.88em;
  background: var(--code-bg);
  border: 1px solid var(--border-soft);
  border-radius: 3px;
  padding: 0.1em 0.35em;
}
#content pre {
  background: var(--code-bg);
  border: 1px solid var(--border-soft);
  border-radius: 4px;
  padding: 1em;
  overflow-x: auto;
  margin: 0 0 1em;
}
#content pre code {
  background: none;
  border: none;
  padding: 0;
  font-size: 0.85rem;
  line-height: 1.45;
}

#content table {
  border-collapse: collapse;
  margin: 0 0 1.2em;
  width: 100%;
  font-size: 0.92rem;
}
#content th, #content td {
  border: 1px solid var(--border-soft);
  padding: 0.5em 0.75em;
  text-align: left;
  vertical-align: top;
}
#content th {
  background: var(--gs-blue);
  color: #fff;
  font-weight: bold;
}
#content tr:nth-child(even) td { background: #f7fafc; }

#content hr {
  border: none;
  border-top: 1px solid var(--border-soft);
  margin: 2em 0;
}

#content img { max-width: 100%; height: auto; }

.img-missing {
  border: 2px dashed var(--border-soft);
  border-radius: 4px;
  background: #fafbfc;
  color: var(--text-secondary);
  font-size: 0.85rem;
  padding: 1.2em 1em;
  text-align: center;
  margin: 0 0 1em;
}
.img-missing code { background: none; border: none; }

#content input[type="checkbox"] { margin-right: 0.4em; }

/* Frontmatter (YAML-metadata) */
#frontmatter {
  display: none;
  margin: 0 0 1.8em;
  background: var(--bg-nav);
  border: 1px solid var(--border-soft);
  border-left: 4px solid var(--gs-blue);
  border-radius: 4px;
  padding: 0.9em 1.1em;
}
#frontmatter .fm-heading {
  font-size: 0.72rem;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--gs-blue);
  margin-bottom: 0.5em;
}
#frontmatter table.fm-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
}
#frontmatter td.fm-key {
  color: var(--gs-blue);
  font-weight: bold;
  white-space: nowrap;
  vertical-align: top;
  padding: 0.22em 1.2em 0.22em 0;
  width: 1%;
}
#frontmatter td.fm-val {
  vertical-align: top;
  padding: 0.22em 0;
}
#frontmatter .fm-tag {
  display: inline-block;
  background: #fff;
  border: 1px solid var(--border-soft);
  border-radius: 4px;
  padding: 0.02em 0.55em;
  margin: 0 0.35em 0.25em 0;
  font-size: 0.82rem;
}

/* Mermaid */
.mermaid-wrap {
  margin: 0 0 1.4em;
  text-align: var(--mmd-align, center);
  overflow-x: auto;
}
.mermaid-wrap svg { width: var(--mmd-width, auto); max-width: 100%; height: auto; }
.mermaid-error {
  background: #fdf0ec;
  border: 1px solid #d24723;
  border-radius: 4px;
  color: #83161C;
  padding: 0.7em 1em;
  font-size: 0.85rem;
  margin: 0 0 1em;
}

/* Status-toast */
#toast {
  position: fixed;
  bottom: 1.2rem;
  left: 50%;
  transform: translateX(-50%);
  background: #1F1F1F;
  color: #fff;
  border-radius: 4px;
  padding: 0.6rem 1.2rem;
  font-size: 0.9rem;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.25s ease;
  z-index: 200;
}
#toast.show { opacity: 0.94; }

@media (max-width: 760px) {
  header.toolbar { padding: 0.55rem 0.85rem; gap: 0.65rem; flex-wrap: nowrap; }
  header.toolbar .brand { min-width: 0; overflow: hidden; text-overflow: ellipsis; font-size: 1rem; }
  .btn.mobile-menu-toggle {
    display: inline-flex;
    flex: none;
    justify-content: center;
    width: 44px;
    height: 44px;
    padding: 0;
    font-size: 1.35rem;
    line-height: 1;
  }
  .toolbar-actions {
    display: none;
    position: absolute;
    top: 100%; left: 0; right: 0;
    max-height: calc(100vh - 60px);
    max-height: calc(100dvh - 60px);
    overflow-y: auto;
    padding: 0.75rem;
    background: var(--gs-blue);
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
  }
  .toolbar-actions.is-open { display: grid; gap: 0.5rem; }
  .toolbar-actions > .btn, .toolbar-actions > .export-menu,
  .toolbar-actions .export-menu > .btn { width: 100%; justify-content: flex-start; }
  .toolbar-actions .filename-badge { max-width: none; }
  .export-menu-panel { position: static; width: 100%; margin-top: 0.4rem; box-shadow: none; }
  #page {
    width: 100%; max-width: none; margin: 0 auto;
    padding: 1.35rem 1rem 3rem;
    box-shadow: none; border-radius: 0;
  }
  #settingsPanel {
    top: 62px; left: 0.75rem; right: 0.75rem; width: auto;
    max-height: calc(100dvh - 75px); overflow-y: auto;
  }
  #searchPanel { top: 62px; left: 0.5rem; right: 0.5rem; flex-wrap: wrap; }
  #searchInput { width: 100%; flex: 1 0 100%; }
  #dropzone { margin: 1rem; padding: 2rem 1rem; }
  #content pre, #content table { max-width: 100%; }
}

/* ============ Print ============ */
@page { margin: 18mm 16mm; size: A4; }

@media print {
  html, body { background: #fff; }
  header.toolbar, #dropzone, #toast, #settingsPanel, #searchPanel, #statsDialog, #ttsDialog { display: none !important; }
  #page {
    display: block !important;
    box-shadow: none;
    max-width: none;
    margin: 0;
    padding: 0;
    border-radius: 0;
  }
  #content pre, #content blockquote, #content table,
  .mermaid-wrap, #content img, #frontmatter, #toc {
    break-inside: avoid;
    page-break-inside: avoid;
  }
  #frontmatter {
    background: var(--bg-nav) !important;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }
  #toc {
    background: var(--bg-info) !important;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }
  .task-check {
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }
  #content h1, #content h2, #content h3, #content h4 {
    break-after: avoid;
    page-break-after: avoid;
  }
  #content a { color: var(--link-color); text-decoration: underline; }
  #content tr:nth-child(even) td { background: #f7fafc !important; }
  #content th { background: var(--gs-blue) !important; color: #fff !important;
    -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .tts-reading { background: transparent !important; outline: none !important; }
}
.md-callout { --callout-accent: #526173; margin: 1.25rem 0; padding: 1rem 1.25rem; border-left: 5px solid var(--callout-accent); border-radius: 5px; background: var(--bg-subtle); color: var(--text-color); min-width: 0; }
.md-callout-info { --callout-accent: #2563c1; background: #f0f5fc; }
.md-callout-note { --callout-accent: #4b638b; background: #f3f5f8; }
.md-callout-tip { --callout-accent: #947300; background: #fffbea; }
.md-callout-important, .md-callout-warning { --callout-accent: #b85c00; background: #fff6ed; }
.md-callout-danger, .md-callout-caution { --callout-accent: #c32f41; background: #fff2f4; }
.md-callout-help { --callout-accent: #8045b6; background: #f8f2fc; }
.md-callout-download, .md-callout-success { --callout-accent: #348025; background: #f2fbed; }
.md-callout-todo { --callout-accent: #087f68; background: #edf9f5; }
.md-callout-simple { border-left: 0; background: var(--bg-subtle); }
.md-callout > .md-callout-title { margin: 0 0 .5rem; font-weight: 700; font-size: 1rem; line-height: 1.5; color: var(--callout-accent); }
.md-callout-body > :first-child { margin-top: 0; }
.md-callout-body > :last-child { margin-bottom: 0; }
.md-callout-body > .md-table-scroll { margin-top: .75rem; }


#page { display:block; } #dropzone { display:none; }
</style>
</head>
<body>

<header class="toolbar">
  <div class="brand">Läs- och exportläge</div>
  <button class="btn btn-outline mobile-menu-toggle" id="btnMobileMenu" type="button" aria-label="Öppna meny" aria-controls="toolbarActions" aria-expanded="false">☰</button>
  <div class="toolbar-actions" id="toolbarActions">
  <a class="btn btn-outline" href="<?= Helpers::e($articleUrl) ?>">← Till artikeln</a>
  <button class="btn btn-outline" id="btnFullscreen" type="button">⛶ Fullskärm</button>
  <span class="filename-badge" id="filenameBadge" title=""></span>
  <button class="btn btn-light" id="btnOpen">📂 Öppna .md</button>
  <button class="btn btn-outline" id="btnStats" disabled title="Visa dokumentstatistik">▥ Statistik</button>
  <button class="btn btn-outline" id="btnTts" disabled title="Läs upp dokumentet eller skapa MP3">🔊 Läs upp</button>
  <button class="btn btn-outline" id="btnSearch" disabled title="Sök i dokumentet (Ctrl+F)">🔎 Sök</button>
  <button class="btn btn-outline" id="btnPrint" disabled>🖨️ Skriv ut</button>
  <div class="export-menu" id="exportMenu">
    <button class="btn btn-outline" id="btnExportToggle" disabled aria-haspopup="true" aria-expanded="false">⬇️ Spara som ▾</button>
    <div class="export-menu-panel" id="exportMenuPanel" hidden>
      <button id="btnPdf" disabled>📄 PDF-dokument (.pdf)</button>
      <button id="btnDocx" disabled>📝 Word-dokument (.docx)</button>
      <button id="btnHtml" disabled>🌐 Webbsida (.html)</button>
      <button id="btnSaveMd" disabled>⌨️ Markdown (.md)</button>
      <button id="btnSaveTxt" disabled>📃 Ren text (.txt)</button>
    </div>
  </div>
  <button class="btn btn-outline" id="btnSettings" title="Inställningar">⚙️</button>
  </div>
</header>

<div id="settingsPanel" hidden>
  <div class="sp-title">Inställningar</div>
  <label class="sp-field">Mermaid-version
    <select id="setMermaidVer">
      <option value="latest">@latest – senaste (standard)</option>
      <option value="11">11 – senaste 11.x</option>
      <option value="10">10 – senaste 10.x</option>
    </select>
  </label>
  <label class="sp-field">Diagrambredd
    <select id="setMermaidWidth">
      <option value="auto">Auto – naturlig storlek (standard)</option>
      <option value="100%">100 % av sidbredden</option>
      <option value="75%">75 % av sidbredden</option>
      <option value="50%">50 % av sidbredden</option>
    </select>
  </label>
  <label class="sp-field">Diagramjustering
    <select id="setMermaidAlign">
      <option value="center">Centrerad (standard)</option>
      <option value="left">Vänsterställd</option>
    </select>
  </label>
  <label class="sp-field">Innehållsförteckning
    <select id="setToc">
      <option value="off">Av (standard)</option>
      <option value="on">Visa – rubriknivå 1–3</option>
    </select>
  </label>
  <label class="sp-field">Textstorlek
    <select id="setFontSize">
      <option value="90%">Kompakt</option>
      <option value="100%">Normal (standard)</option>
      <option value="115%">Stor</option>
      <option value="130%">Mycket stor</option>
    </select>
  </label>
  <label class="sp-field">Sidbredd
    <select id="setPageWidth">
      <option value="210mm">A4 (standard)</option>
      <option value="960px">Bred</option>
      <option value="1200px">Mycket bred</option>
    </select>
  </label>
  <p class="sp-hint">Inställningarna sparas automatiskt i webbläsaren.</p>
</div>

<input type="file" id="fileInput" accept=".md,.markdown,.txt,text/markdown" hidden>

<div id="searchPanel" hidden>
  <input id="searchInput" type="search" placeholder="Sök i dokumentet…" aria-label="Sök i dokumentet">
  <span id="searchCount">0 träffar</span>
  <button id="searchPrev" type="button" title="Föregående träff">↑</button>
  <button id="searchNext" type="button" title="Nästa träff">↓</button>
  <button id="searchClose" type="button" title="Stäng sökning">×</button>
</div>

<div id="dropzone">
  <h2>Öppna en Markdown-fil</h2>
  <p>Dra och släpp en <strong>.md</strong>-fil här, eller klicka för att välja fil.</p>
  <p class="hint">Visa och exportera GFM med tabeller, checklistor, kodblock och Mermaid-diagram.</p>
</div>

<article id="page">
  <div id="frontmatter" class="fm-box"></div>
  <nav id="toc" class="toc-box" hidden></nav>
  <div id="content"><?= $readerData['html'] ?></div>
</article>



<dialog id="statsDialog" aria-labelledby="statsTitle">
  <div class="stats-head">
    <h2 id="statsTitle">Dokumentstatistik</h2>
    <button class="stats-close" id="btnStatsClose" type="button" aria-label="Stäng">×</button>
  </div>
  <dl class="stats-grid" id="statsGrid"></dl>
</dialog>

<dialog id="ttsDialog" aria-labelledby="ttsTitle">
  <div class="stats-head">
    <h2 id="ttsTitle">Uppläsning och MP3</h2>
    <button class="stats-close" id="btnTtsClose" type="button" aria-label="Stäng">×</button>
  </div>
  <div class="tts-body">
    <label class="tts-field">Röst
      <select id="ttsVoice" aria-describedby="ttsVoiceHint">
        <option value="">Automatiskt – svensk röst om tillgänglig</option>
      </select>
    </label>
    <p class="tts-status" id="ttsVoiceHint">Röster som webbläsaren har tillgång till. Valet gäller uppläsning; MP3 använder en separat svensk röst.</p>
    <label class="tts-field">Uppläsningshastighet
      <select id="ttsRate">
        <option value="0.8">Långsam</option>
        <option value="1">Normal</option>
        <option value="1.2">Snabb</option>
        <option value="1.5" selected>Mycket snabb (standard)</option>
        <option value="2">2× hastighet</option>
        <option value="3">3× hastighet</option>
        <option value="4">4× hastighet</option>
      </select>
    </label>
    <div class="tts-actions">
      <button class="btn" id="btnTtsPlay" type="button">▶ Läs upp</button>
      <button class="btn btn-secondary" id="btnTtsPause" type="button" disabled>⏸ Pausa</button>
      <button class="btn btn-secondary" id="btnTtsStop" type="button" disabled>■ Stoppa</button>
      <button class="btn" id="btnTtsMp3" type="button">⬇ Skapa MP3</button>
    </div>
    <p class="tts-status" id="ttsStatus" aria-live="polite">Redo.</p>
  </div>
</dialog>

<div id="toast"></div>

<script type="application/json" id="reader-data"><?= json_encode($readerData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<link rel="stylesheet" href="/templates/default/assets/css/viewer.css">
<script src="/templates/default/assets/js/viewer.js"></script>
<script>
(() => {
  const button = document.getElementById('btnMobileMenu');
  const actions = document.getElementById('toolbarActions');
  const close = () => {
    actions.classList.remove('is-open');
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-label', 'Öppna meny');
  };
  button.addEventListener('click', () => {
    const open = actions.classList.toggle('is-open');
    button.setAttribute('aria-expanded', String(open));
    button.setAttribute('aria-label', open ? 'Stäng meny' : 'Öppna meny');
  });
  actions.addEventListener('click', (event) => {
    if (event.target.closest('button, a') && !event.target.closest('#btnExportToggle')) close();
  });
  document.addEventListener('click', (event) => {
    if (!actions.contains(event.target) && event.target !== button) close();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') close();
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 760) close();
  });
})();
</script>
<script type="module" src="/templates/default/assets/js/reader.js"></script>
</body>
</html>
