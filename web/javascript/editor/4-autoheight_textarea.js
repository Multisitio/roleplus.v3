function textarea_auto_height(el) {
    if (el) {
        var scrollAncestors = [];
        var parent = el.parentNode;
        while (parent && parent !== document) {
            scrollAncestors.push({
                element: parent,
                scrollTop: parent.scrollTop,
                scrollLeft: parent.scrollLeft
            });
            parent = parent.parentNode;
        }
        var windowScrollX = window.scrollX;
        var windowScrollY = window.scrollY;

        el.style.height = 'auto';
        var style = window.getComputedStyle(el);
        var height = el.scrollHeight;
        if (style.boxSizing === 'border-box') {
            var borderTop = parseFloat(style.borderTopWidth) || 0;
            var borderBottom = parseFloat(style.borderBottomWidth) || 0;
            height += borderTop + borderBottom;
        }
        height = (height < 50) ? 50 : height;
        el.style.height = height + 'px';

        for (var i = 0; i < scrollAncestors.length; i++) {
            scrollAncestors[i].element.scrollTop = scrollAncestors[i].scrollTop;
            scrollAncestors[i].element.scrollLeft = scrollAncestors[i].scrollLeft;
        }
        window.scrollTo(windowScrollX, windowScrollY);
        return;
    }
    var els = document.querySelectorAll('textarea');
    for(var i=0; i<els.length; i++){
        textarea_auto_height(els[i]);
    }
}
textarea_auto_height();

Kumbia.utils.on('keyup', 'textarea', function() {
    textarea_auto_height(this);
});

// Detectar textareas nuevos insertados por AJAX
var textareaObserver = new MutationObserver(function(mutations) {
    var hasTextarea = false;
    for (var i = 0; i < mutations.length; i++) {
        var addedNodes = mutations[i].addedNodes;
        for (var j = 0; j < addedNodes.length; j++) {
            var node = addedNodes[j];
            if (node.nodeType === 1) {
                if (node.tagName === 'TEXTAREA' || node.querySelector('textarea')) {
                    hasTextarea = true;
                    break;
                }
            }
        }
        if (hasTextarea) break;
    }
    if (hasTextarea) {
        requestAnimationFrame(function() {
            textarea_auto_height();
        });
    }
});
textareaObserver.observe(document.body, { childList: true, subtree: true });

/* TAB Y SHIFT+TAB EN UN TEXTAREA */
Kumbia.utils.on('keydown', 'textarea', function(event) {
    if (event.keyCode === 9) {
        var v = this.value,
            s = this.selectionStart,
            e = this.selectionEnd;
        var linesStart = v.lastIndexOf('\n', s - 1) + 1;

        if (event.shiftKey) {
            // Shift+Tab: desindentar
            var selectedText = v.substring(linesStart, e);
            var lines = selectedText.split('\n');
            var removedCount = 0;
            var newText = lines.map(function(line, idx) {
                if (line.charAt(0) === '\t') {
                    if (idx === 0) removedCount = 1;
                    return line.substring(1);
                }
                return line;
            }).join('\n');
            var removed = lines.map(function(l) { return l.charAt(0) === '\t' ? 1 : 0; });
            this.value = v.substring(0, linesStart) + newText + v.substring(e);
            this.selectionStart = Math.max(linesStart, s - removed[0]);
            this.selectionEnd = e - removed.reduce(function(a, b) { return a + b; }, 0);
        } else {
            // Tab: indentar
            if (s === e) {
                this.value = v.substring(0, s) + '\t' + v.substring(e);
                this.selectionStart = this.selectionEnd = s + 1;
            } else {
                var selectedText2 = v.substring(linesStart, e);
                var lines2 = selectedText2.split('\n');
                var newText2 = lines2.map(function(line) { return '\t' + line; }).join('\n');
                this.value = v.substring(0, linesStart) + newText2 + v.substring(e);
                this.selectionStart = s + 1;
                this.selectionEnd = e + lines2.length;
            }
        }
        event.preventDefault();
    }
});

