// /javascript/wysiwyg.js
(function(global) {
    const defaultTools = [
      'b','i','u','p','div','h1','h2','h3','h4','h5','h6','a','img','ul','ol','table','row','col','toggleHTML'
    ];
  
    const commands = {
      b: wrap('strong'),
      i: wrap('em'),
      u: wrap('u'),
      p: wrapBlock('p'),
      div: wrapBlock('div'),
      h1: wrap('h1'), h2: wrap('h2'), h3: wrap('h3'), h4: wrap('h4'), h5: wrap('h5'), h6: wrap('h6'),
      a(range) {
        const url = prompt('URL del enlace (ancla interno):', '#');
        if (!url) return;
        const aEl = document.createElement('a'); aEl.href = url;
        wrapRange(aEl, range);
      },
      img(range) {
        const src = prompt('URL de la imagen:' ); if (!src) return;
        const imgEl = document.createElement('img'); imgEl.src = src;
        insertNode(imgEl, range);
      },
      ul: wrapBlock.bind(null, 'ul'),
      ol: wrapBlock.bind(null, 'ol'),
      table(range) {
        // crea tabla initial 1x1 con texto dummy o envuelve selección
        if (!range || range.collapsed) {
          const table = document.createElement('table');
          const tr = document.createElement('tr');
          const td = document.createElement('td');
          td.textContent = 'Texto';
          tr.appendChild(td); table.appendChild(tr);
          insertNode(table, range);
        } else {
          const content = range.extractContents();
          const table = document.createElement('table');
          const tr = document.createElement('tr'); const td = document.createElement('td');
          td.appendChild(content); tr.appendChild(td); table.appendChild(tr);
          range.insertNode(table);
        }
      },
      row() {
        // añade fila tras la fila actual
        if (!currentRange) return;
        const cell = currentRange.startContainer.closest('td,th');
        if (!cell) return;
        const tr = cell.closest('tr'); const table = tr.closest('table');
        const newTr = document.createElement('tr');
        tr.querySelectorAll('td,th').forEach(() => {
          const newCell = document.createElement('td'); newCell.textContent = 'Texto';
          newTr.appendChild(newCell);
        });
        tr.parentNode.insertBefore(newTr, tr.nextSibling);
      },
      col() {
        // añade columna tras la columna actual
        if (!currentRange) return;
        const cell = currentRange.startContainer.closest('td,th');
        if (!cell) return;
        const table = cell.closest('table');
        const idx = Array.prototype.indexOf.call(cell.parentNode.children, cell);
        table.querySelectorAll('tr').forEach(row => {
          const newCell = document.createElement('td'); newCell.textContent = 'Texto';
          row.insertBefore(newCell, row.children[idx + 1] || null);
        });
      },
      toggleHTML() {
        // alterna vista HTML / WYSIWYG
        if (!htmlView) return;
        if (htmlView.style.display === 'none') {
          htmlView.value = editorEl.innerHTML;
          editorEl.style.display = 'none'; htmlView.style.display = 'block';
          toggleBtn.textContent = 'Ver Editor';
        } else {
          editorEl.innerHTML = htmlView.value;
          htmlView.style.display = 'none'; editorEl.style.display = 'block';
          toggleBtn.textContent = 'Ver HTML';
        }
      }
    };
  
    // Helpers
    function wrap(tag) {
      return range => {
        if (!range || range.collapsed) return;
        const el = document.createElement(tag);
        wrapRange(el, range);
      };
    }
    function wrapBlock(tag, range) {
      const el = document.createElement(tag);
      if (!range || range.collapsed) {
        el.textContent = 'Texto';
        insertNode(el, range);
      } else {
        wrapRange(el, range);
      }
    }
    function wrapRange(node, range) {
      const content = range.extractContents(); node.appendChild(content); range.insertNode(node);
    }
    function insertNode(node, range) {
      range.deleteContents(); range.insertNode(node);
    }
  
    // Estado
    let toolbar, currentRange, editorEl, htmlView, toggleBtn;
  
    // Toolbar flotante
    function createToolbar(tools) {
      toolbar = document.createElement('div'); toolbar.className = 'wysiwyg-toolbar';
      Object.assign(toolbar.style, {
        position: 'absolute', display: 'none', background: '#fff',
        border: '1px solid #ccc', padding: '4px', borderRadius: '4px',
        zIndex: 9999, boxShadow: '0 2px 6px rgba(0,0,0,0.2)'
      });
      tools.forEach(tool => {
        const btn = document.createElement('button');
        btn.type = 'button'; btn.textContent = tool; btn.dataset.tool = tool;
        btn.addEventListener('click', onClick); toolbar.appendChild(btn);
      }); document.body.appendChild(toolbar);
    }
    function onClick(e) {
      const t = e.currentTarget.dataset.tool;
      if (commands[t]) commands[t](currentRange?.cloneRange());
      hideToolbar();
    }
  
    // Mostrar toolbar
    function showAtSelection() {
      const sel = window.getSelection(); if (!sel.rangeCount) return hideToolbar();
      const range = sel.getRangeAt(0);
      if (!sel.toString().trim() || !editorEl.contains(range.commonAncestorContainer)) return hideToolbar();
      currentRange = range.cloneRange(); positionToolbar(range.getBoundingClientRect());
    }
    function showAtMouse(x,y) {
      positionToolbar({ top: y, left: x, bottom: y, height: 0 });
    }
    function positionToolbar(rect) {
      toolbar.style.display = 'block'; toolbar.style.visibility = 'hidden';
      const h = toolbar.offsetHeight; let top = rect.top + window.scrollY - h - 8;
      if (top < window.scrollY) top = rect.bottom + window.scrollY + 8;
      toolbar.style.top = top + 'px'; toolbar.style.left = (rect.left + window.scrollX) + 'px';
      toolbar.style.visibility = 'visible';
    }
    function hideToolbar() {
      if (toolbar) toolbar.style.display = 'none';
    }
  
    // Init y manejo de Enter
    function init(selector, options={}) {
      editorEl = document.querySelector(selector); if (!editorEl) throw new Error('Contenedor no encontrado');
      editorEl.contentEditable = true;
      // botón toggle y área HTML
      toggleBtn = document.createElement('button'); toggleBtn.textContent = 'Ver HTML'; toggleBtn.className = 'wysiwyg-toggle-btn';
      editorEl.parentNode.insertBefore(toggleBtn, editorEl);
      htmlView = document.createElement('textarea'); htmlView.style.display = 'none'; htmlView.style.width = '100%'; htmlView.style.height = '300px';
      editorEl.parentNode.insertBefore(htmlView, editorEl.nextSibling);
      toggleBtn.addEventListener('click', commands.toggleHTML);
      createToolbar(options.tools || defaultTools);
      editorEl.addEventListener('mouseup', () => setTimeout(showAtSelection, 0));
      editorEl.addEventListener('keyup', e => {
        if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key)) setTimeout(showAtSelection, 0);
      });
      editorEl.addEventListener('contextmenu', e => { e.preventDefault(); showAtMouse(e.clientX, e.clientY); });
      // Enter solo <br>
      editorEl.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
          e.preventDefault();
          const sel = window.getSelection();
          if (sel.rangeCount) {
            const range = sel.getRangeAt(0);
            const br = document.createElement('br');
            range.deleteContents(); range.insertNode(br);
            range.setStartAfter(br); range.setEndAfter(br);
            sel.removeAllRanges(); sel.addRange(range);
          }
        }
      });
      document.addEventListener('click', e => {
        if (!toolbar.contains(e.target) && !editorEl.contains(e.target) && e.target !== toggleBtn) hideToolbar();
      });
    }
  
    document.addEventListener('DOMContentLoaded', () => init('#editor'));
    global.wysiwyg = { init, getHTML: () => editorEl.innerHTML };
  })(window);