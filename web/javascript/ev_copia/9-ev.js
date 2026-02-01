$(window).resize(function () {
	// Hook opcional responsive.
});

/* ==========================
 * LOG controlado
 * ========================== */
var __RP_DEBUG__ = false;
try {
	__RP_DEBUG__ = (localStorage.getItem('rp_debug') === '1');
} catch (_) {}

function rpLog() {
	if (!__RP_DEBUG__) return;
	try {
		console.log.apply(console, arguments);
	} catch (_) {}
}

/* ==========================
 * UI: ocultar barra/nav al hacer scroll
 * ========================== */
var prevScrollpos = window.pageYOffset;
var stopScrollAnim = 0;

window.onscroll = function () {
	var y = window.pageYOffset;

	if (y === 0) {
		$('nav, .scroll-down-hide').fadeIn();
		return;
	}

	if (stopScrollAnim !== 0) return;
	stopScrollAnim = 1;

	if (prevScrollpos > y) {
		$('nav, .scroll-down-hide').fadeIn(function () {
			stopScrollAnim = 0;
		});
	} else {
		$('aside.left, nav, .scroll-down-hide').fadeOut(function () {
			stopScrollAnim = 0;
		});
	}

	prevScrollpos = y;
};

/* ==========================
 * Compartir / copiar
 * ========================== */
$('body').on('click', '.share', function (ev) {
	ev.preventDefault();
	var url = $(this).attr('href');
	if (navigator.share) {
		navigator.share({ title: '', text: '', url: url });
	} else {
		copiarAlPortapapeles(url);
	}
});

/* ==========================
 * DRAG & DROP flotantes
 * ==========================
 *
 * Reglas:
 * ⮞ Cada .draggable puede tener:
 *    data-url="/ruta/base"
 *    data-autosavednd        ⮞ atributo presente ⇒ auto-guardar al soltar
 *
 * ⮞ Si NO existe data-autosavednd, no se hace petición de guardado.
 *
 * Seguridad:
 * ⮞ El backend debe validar coords recibidas en data-url/left/top.
 */

var rpDragTopZ = 1000;

/* ¿esta caja debe autoguardar coords al soltar? */
function shouldAutoSave($box) {
	// guardamos SI y SOLO SI el atributo existe en el DOM,
	// da igual su valor (puede venir como data-autosavednd="" o solo presente).
	return $box.is('[data-autosavednd]');
}

/* Llama al backend con la nueva posición */
function saveDraggablePosition($box) {
	if (!shouldAutoSave($box)) {
		rpLog('[drag:auto-save] skip (sin data-autosavednd) en', $box.get(0));
		return;
	}

	var left = $box.position().left;
	var top = $box.position().top;
	var base = $box.data('url');

	if (!base) {
		rpLog('[drag:auto-save] sin data-url en', $box.get(0));
		return;
	}

	var url = base + '/' + left + '/' + top;

	// usamos el contenedor AJAX oculto existente
	$('.ajax.hide').load(url);

	rpLog('[drag:auto-save] guardado', url);
}

/* Inicializa draggable en un elemento */
function makeDraggable($box) {
	// destruye instancia previa si la hay
	try {
		$box.draggable('destroy');
	} catch (_) {}

	$box.draggable({
		grid: [4, 4],
		handle: 'header',
		snap: true,
		start: function () {
			// eleva visualmente al empezar a arrastrar
			$(this).trigger('click');
		},
		stop: function () {
			// al soltar, si procede, guardar coords
			saveDraggablePosition($(this));
		}
	});
}

/* Busca y engancha todos los .draggable dentro de root */
function initDraggables(root) {
	var $scope = root ? $(root) : $(document);
	var $items = $scope.find('.draggable');

	if ($scope.is && $scope.is('.draggable')) {
		$items = $items.add($scope);
	}

	if ($items.length === 0) {
		rpLog('[drag:init] no hay .draggable en', (root === document) ? 'document' : root);
		return;
	}

	$items.each(function () {
		makeDraggable($(this));
	});

	rpLog('[drag:init] hecho en', $items.length, 'elementos');
}

/* Exponemos para otros scripts */
window.initDraggables = initDraggables;

/* Primer arranque */
$(function () {
	initDraggables(document);
});

/* Re-init manual si Kumbia notifica nueva inyección */
try {
	document.addEventListener('kumbia:inject', function (ev) {
		var into = (ev && ev.detail && ev.detail.into) ? ev.detail.into : document;
		rpLog('[kumbia:inject]', ev && ev.detail && ev.detail.mode);
		initDraggables(into);
	});
} catch (_) {}

/* Fallback universal:
 * MutationObserver para pillar .draggable que aparezcan sin avisar
 */
try {
	var rpDragObserver = new MutationObserver(function (muts) {
		for (var i = 0; i < muts.length; i++) {
			var nodes = muts[i].addedNodes;
			for (var j = 0; j < nodes.length; j++) {
				var n = nodes[j];
				if (!n || n.nodeType !== 1) continue;

				if (n.matches && n.matches('.draggable')) {
					initDraggables(n);
					continue;
				}

				if (n.querySelectorAll) {
					var inner = n.querySelectorAll('.draggable');
					if (inner && inner.length) {
						initDraggables(n);
					}
				}
			}
		}
	});
	rpDragObserver.observe(document.body, { childList: true, subtree: true });
} catch (eObs) {
	rpLog('[observer:error]', eObs);
}

/* Elevar z-index del activo sin escanear todo cada vez */
$('body').on('click', '.draggable', function () {
	rpDragTopZ++;
	$(this).css('z-index', rpDragTopZ);
});

/* ==========================
 * Navegación de apartados
 * ========================== */
$('body').on('click', '.apartados a', function () {
	var destino = $(this).attr('href');
	rpLog('[apartados:navigate]', destino);

	if (destino === '#tapiz') {
		$('.post-it, .jitsi').hide();
	} else if (destino === '#notas') {
		$('.post-it').show();
		$('.jitsi').hide();
	} else if (destino === '#jitsi') {
		$('.jitsi').show();
		$('.post-it').hide();
	}
});

/* ==========================
 * Textareas
 * ========================== */

function textarea_auto_height() {
	var els = document.querySelectorAll('textarea');
	els.forEach(function (el) {
		var height = el.scrollTop + el.scrollHeight;
		if (height < 99) height = 99;
		el.style.height = height + 'px';
	});
}
textarea_auto_height();

$('body').on('click keyup', 'textarea', function () {
	textarea_auto_height();
});

/* insertar tab literal (\t) en textareas */
$('body').on('keydown', 'textarea', function (event) {
	if (event.keyCode === 9) {
		var v = this.value;
		var s = this.selectionStart;
		var e = this.selectionEnd;

		this.value = v.substring(0, s) + '\t' + v.substring(e);
		this.selectionStart = this.selectionEnd = s + 1;
		return false;
	}
});
