/**
 * templates/default/assets/js/mermaid.js
 *
 * Renderar ```mermaid-kodblock till riktiga diagram på vanliga wikisidor.
 * Parser.php (core/Parser.php) escapear ```mermaid-block till vanlig
 * <pre><code class="language-mermaid">...</code></pre> HTML — samma som
 * alla andra kodblock. Detta skript letar upp dem och ersätter dem med
 * renderad SVG via mermaid.js (laddas från CDN, se layout.php, som bara
 * inkluderar båda skripten när sidan faktiskt innehåller ett mermaid-block).
 *
 * Diagrammen ritas om live när man togglar ljust/mörkt läge (utan
 * omladdning) — se lyssnaren på "gbg-theme-change" (dispatchas av
 * assets/js/theme.js) längst ner. Originalkällan sparas i ett
 * data-attribut på wrappern eftersom <pre><code> ersätts vid rendering.
 *
 * Återanvänder samma klassnamn (.mmd-diagram / .mmd-err) och tema-inställningar
 * som /chat/index.php, så diagrammen ser likadana ut överallt.
 */
(function () {
  function escapeHtml(s) {
    return String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
  }

  function isDarkTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark';
  }

  function initMermaid(dark) {
    window.mermaid.initialize({
      startOnLoad: false,
      securityLevel: 'strict',
      theme: dark ? 'dark' : 'base',
      flowchart: { htmlLabels: true, useMaxWidth: true },
    });
  }

  async function renderInto(wrap, source, id) {
    try {
      const { svg } = await window.renderWikiMermaid(source, id, Math.max(960, wrap.clientWidth));
      wrap.innerHTML = svg;
      wrap.tabIndex = 0;
      wrap.setAttribute('role', 'button');
      wrap.setAttribute('aria-label', 'Öppna diagram i popup');
      wrap.onclick = () => openDiagram(wrap, wrap);
      wrap.onkeydown = e => {
        if (e.target === wrap && (e.key === 'Enter' || e.key === ' ')) {
          e.preventDefault();
          openDiagram(wrap, wrap);
        }
      };
    } catch (err) {
      wrap.innerHTML = '<div class="mmd-err">⚠️ Diagramfel: ' + escapeHtml((err && err.message) || err) + '</div>';
    }
  }

  let viewer;
  function openDiagram(wrap, trigger) {
    const original = wrap.querySelector('svg');
    if (!original) return;
    if (!viewer) viewer = window.createWikiViewer();
    viewer.open(original, trigger, { source: wrap.dataset.mermaidSource, securityLevel: 'strict' });
  }

  async function renderAll() {
    if (!window.mermaid) return;
    const blocks = document.querySelectorAll('pre > code.language-mermaid');
    if (!blocks.length) return;

    initMermaid(isDarkTheme());

    let i = 0;
    for (const code of blocks) {
      const pre = code.parentElement;
      const source = code.textContent;
      const wrap = document.createElement('div');
      wrap.className = 'mmd-diagram';
      wrap.dataset.mermaidSource = source;
      pre.replaceWith(wrap);
      await renderInto(wrap, source, 'wiki-mmd-' + (i++));
    }
  }

  /** Ritar om alla redan renderade diagram i det nya läget (ljust/mörkt). */
  async function rerenderAll(theme) {
    if (!window.mermaid) return;
    const wraps = document.querySelectorAll('.mmd-diagram[data-mermaid-source]');
    if (!wraps.length) return;

    initMermaid(theme === 'dark');

    let i = 0;
    for (const wrap of wraps) {
      await renderInto(wrap, wrap.dataset.mermaidSource, 'wiki-mmd-retheme-' + (i++));
    }
  }

  window.addEventListener('gbg-theme-change', function (e) {
    rerenderAll(e.detail && e.detail.theme);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderAll);
  } else {
    renderAll();
  }
})();
