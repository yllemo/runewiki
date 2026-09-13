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
      const { svg } = await window.mermaid.render(id, source);
      wrap.innerHTML = svg;
      const open = document.createElement('button');
      open.type = 'button';
      open.className = 'mmd-open';
      open.textContent = 'Förstora diagram';
      open.addEventListener('click', () => openDiagram(wrap, open));
      wrap.appendChild(open);
      wrap.onclick = e => { if (e.target.closest('svg')) openDiagram(wrap, open); };
    } catch (err) {
      wrap.innerHTML = '<div class="mmd-err">⚠️ Diagramfel: ' + escapeHtml((err && err.message) || err) + '</div>';
    }
  }

  let viewer;
  function openDiagram(wrap, trigger) {
    const original = wrap.querySelector('svg');
    if (!original) return;
    if (!viewer) viewer = createViewer();
    viewer.open(original, trigger);
  }

  function createViewer() {
    const dialog = document.createElement('dialog');
    dialog.className = 'mmd-viewer';
    dialog.setAttribute('aria-label', 'Diagramvisare');
    dialog.innerHTML = '<div class="mmd-toolbar"><strong>Diagram</strong>' +
      '<button type="button" data-action="out" aria-label="Zooma ut">−</button>' +
      '<output aria-live="polite">100%</output>' +
      '<button type="button" data-action="in" aria-label="Zooma in">+</button>' +
      '<button type="button" data-action="fit">Anpassa</button>' +
      '<button type="button" data-action="close" aria-label="Stäng diagram">Stäng ×</button></div>' +
      '<div class="mmd-viewport" tabindex="0" aria-label="Diagram. Dra för att panorera. Använd plus och minus för zoom, piltangenter för panorering och 0 för att anpassa."><div class="mmd-canvas"></div></div>' +
      '<p class="mmd-help">Dra för att panorera · Scrolla eller använd + / − för zoom · 0 anpassar · Esc stänger</p>';
    document.body.appendChild(dialog);
    const viewport = dialog.querySelector('.mmd-viewport');
    const canvas = dialog.querySelector('.mmd-canvas');
    const output = dialog.querySelector('output');
    let scale = 1, x = 0, y = 0, width = 1, height = 1, opener, oldOverflow;
    let drag = null;
    function paint() {
      canvas.style.transform = 'translate(' + x + 'px,' + y + 'px) scale(' + scale + ')';
      output.textContent = Math.round(scale * 100) + '%';
    }
    function fit() {
      scale = Math.min((viewport.clientWidth - 48) / width, (viewport.clientHeight - 48) / height, 2);
      scale = Math.max(0.01, scale);
      x = (viewport.clientWidth - width * scale) / 2;
      y = (viewport.clientHeight - height * scale) / 2;
      paint();
    }
    function zoom(factor, cx = viewport.clientWidth / 2, cy = viewport.clientHeight / 2) {
      const next = Math.min(20, Math.max(0.01, scale * factor));
      x = cx - (cx - x) * next / scale;
      y = cy - (cy - y) * next / scale;
      scale = next; paint();
    }
    dialog.addEventListener('click', e => {
      const action = e.target.closest('button')?.dataset.action;
      if (action === 'close' || e.target === dialog) dialog.close();
      if (action === 'fit') fit();
      if (action === 'in') zoom(1.25);
      if (action === 'out') zoom(0.8);
    });
    dialog.addEventListener('close', () => {
      document.body.style.overflow = oldOverflow;
      canvas.replaceChildren(); drag = null;
      if (opener?.isConnected) opener.focus();
    });
    viewport.addEventListener('wheel', e => {
      e.preventDefault();
      const rect = viewport.getBoundingClientRect();
      zoom(Math.exp(-Math.max(-100, Math.min(100, e.deltaY)) * 0.005), e.clientX - rect.left, e.clientY - rect.top);
    }, { passive: false });
    viewport.addEventListener('pointerdown', e => {
      if (e.button !== 0 || drag) return;
      viewport.focus(); viewport.setPointerCapture(e.pointerId);
      drag = { id: e.pointerId, x: e.clientX, y: e.clientY };
      viewport.classList.add('is-dragging');
      e.preventDefault();
    });
    viewport.addEventListener('pointermove', e => {
      if (!drag || drag.id !== e.pointerId) return;
      x += e.clientX - drag.x; y += e.clientY - drag.y;
      drag.x = e.clientX; drag.y = e.clientY; paint();
    });
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(type => viewport.addEventListener(type, () => {
      drag = null; viewport.classList.remove('is-dragging');
    }));
    viewport.addEventListener('keydown', e => {
      if (e.key === '+' || e.key === '=') zoom(1.25);
      else if (e.key === '-') zoom(0.8);
      else if (e.key === '0' || e.key === 'Home') fit();
      else if (e.key === 'ArrowLeft') { x += 40; paint(); }
      else if (e.key === 'ArrowRight') { x -= 40; paint(); }
      else if (e.key === 'ArrowUp') { y += 40; paint(); }
      else if (e.key === 'ArrowDown') { y -= 40; paint(); }
      else return;
      e.preventDefault();
    });
    new ResizeObserver(() => { if (dialog.open) fit(); }).observe(viewport);
    return { open(svg, trigger) {
      opener = trigger;
      const clone = svg.cloneNode(true);
      // Keep SVG marker/label IDs unique while both copies are in the document.
      const ids = new Map();
      clone.querySelectorAll('[id]').forEach(el => ids.set(el.id, 'popup-' + el.id));
      if (clone.id) ids.set(clone.id, 'popup-' + clone.id);
      [clone, ...clone.querySelectorAll('*')].forEach(el => {
        for (const attr of [...el.attributes]) {
          let value = attr.value;
          if (attr.name === 'id') value = ids.get(value) || value;
          else ids.forEach((next, old) => {
            value = value.split('url(#' + old + ')').join('url(#' + next + ')');
            if (value === '#' + old) value = '#' + next;
            if (attr.name === 'aria-labelledby' || attr.name === 'aria-describedby') value = value.split(' ').map(id => id === old ? next : id).join(' ');
          });
          el.setAttribute(attr.name, value);
        }
        if (el.tagName.toLowerCase() === 'style') ids.forEach((next, old) => { el.textContent = el.textContent.split('#' + old).join('#' + next); });
      });
      const box = svg.viewBox.baseVal;
      width = box.width || svg.getBoundingClientRect().width || 800;
      height = box.height || svg.getBoundingClientRect().height || 600;
      clone.style.width = width + 'px'; clone.style.height = height + 'px'; clone.style.maxWidth = 'none';
      canvas.replaceChildren(clone);
      oldOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      dialog.showModal(); fit(); viewport.focus();
    } };
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
