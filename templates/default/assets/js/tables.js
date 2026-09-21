// Innehållsstyrd förstakolumn, lika fördelning av resterande tabellbredd.
(function () {
  const selector = '.wiki-body table, .bubble table, #content table';
  const pending = new Set();
  const widths = new WeakMap();
  let frame;
  function schedule(table) {
    if (!table.isConnected || table.closest('[data-table-probe]')) return;
    pending.add(table);
    if (!frame) frame = requestAnimationFrame(() => {
      frame = null;
      pending.forEach(layout);
      pending.clear();
    });
  }
  const resize = new ResizeObserver(entries => entries.forEach(({target, contentRect}) => {
    if (widths.get(target) !== contentRect.width) {
      widths.set(target, contentRect.width);
      schedule(target);
    }
  }));
  function layout(table) {
    if (!table.isConnected || !table.rows.length) return;
    const rows = [...table.rows];
    const columns = rows[0].cells.length;
    // Låt sammanslagna celler och egna kolumndefinitioner behålla sin layout.
    if (!columns || rows.some(row => [...row.cells].some(cell => cell.colSpan !== 1 || cell.rowSpan !== 1))) return;
    if (table.querySelector('colgroup:not([data-auto-columns])')) return;
    const parentStyle = getComputedStyle(table.parentElement);
    const available = table.parentElement.clientWidth - parseFloat(parentStyle.paddingLeft) - parseFloat(parentStyle.paddingRight);
    if (!available) return;
    const probe = table.cloneNode(true);
    probe.dataset.tableProbe = '';
    probe.setAttribute('aria-hidden', 'true');
    probe.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
    probe.removeAttribute('id');
    probe.querySelectorAll('colgroup, caption').forEach(el => el.remove());
    probe.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;left:0;top:0;width:max-content;max-width:none;min-width:0;table-layout:auto;margin:0';
    [...probe.rows].forEach(row => {
      [...row.cells].slice(1).forEach(cell => cell.remove());
      if (row.cells[0]) Object.assign(row.cells[0].style, {width:'auto', minWidth:'0', maxWidth:'none', whiteSpace:'nowrap', overflowWrap:'normal'});
    });
    table.after(probe);
    const natural = Math.ceil(probe.getBoundingClientRect().width) + 2;
    probe.remove();
    const first = columns === 1 ? 100 : Math.min(40, natural / available * 100);
    let group = table.querySelector('colgroup[data-auto-columns]');
    if (!group) {
      group = document.createElement('colgroup');
      group.dataset.autoColumns = '';
      for (let i = 0; i < columns; i++) group.append(document.createElement('col'));
      table.prepend(group);
    }
    if (group.children.length !== columns) {
      group.replaceChildren(...Array.from({length:columns}, () => document.createElement('col')));
    }
    [...group.children].forEach((col, i) => { col.style.width = (i ? (100-first)/(columns-1) : first) + '%'; });
    table.style.tableLayout = 'fixed';
    table.style.width = '100%';
    rows.forEach(row => [...row.cells].forEach((cell, i) => {
      Object.assign(cell.style, {minWidth:'0', whiteSpace:i === 0 && natural <= available * first / 100 ? 'nowrap' : 'normal', overflowWrap:'anywhere'});
    }));
  }
  function discover(root) {
    if (root.nodeType !== 1 && root.nodeType !== 9) return;
    if (root.closest?.('[data-table-probe], [data-auto-columns]')) return;
    const tables = [...root.querySelectorAll(selector)];
    if (root.matches?.(selector)) tables.push(root);
    const parent = root.closest?.('table');
    if (parent?.matches(selector)) tables.push(parent);
    tables.forEach(table => { resize.observe(table); schedule(table); });
  }
  discover(document);
  new MutationObserver(records => records.forEach(record => {
    const nodes = [...record.addedNodes, ...record.removedNodes];
    if (nodes.length && nodes.every(node => node.nodeType === 1 && (node.hasAttribute('data-table-probe') || node.hasAttribute('data-auto-columns')))) return;
    discover(record.target.nodeType === 3 ? record.target.parentElement : record.target);
    record.addedNodes.forEach(discover);
    record.removedNodes.forEach(node => {
      if (node.nodeType !== 1 || node.isConnected) return;
      if (node.matches('table')) resize.unobserve(node);
      node.querySelectorAll('table').forEach(table => resize.unobserve(table));
    });
  })).observe(document.body, {childList:true, subtree:true, characterData:true});
  document.fonts?.ready.then(() => discover(document));
  new MutationObserver(() => discover(document)).observe(document.documentElement, {
    attributes:true, attributeFilter:['style', 'class', 'data-theme']
  });
  window.addEventListener('resize', () => discover(document));
  window.addEventListener('beforeprint', () => document.querySelectorAll(selector).forEach(layout));
  document.addEventListener('load', event => {
    if (event.target.tagName === 'IMG') discover(event.target);
  }, true);
})();
