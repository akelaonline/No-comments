/**
 * NO Comments — admin interactions (Delete Comments tab).
 * Vanilla JS, no dependencies. Strings localized via noCommentsAdmin (i18n).
 */
(function () {
	'use strict';

	function init() {
		var wrap = document.getElementById('no-comments-admin');
		if (!wrap) {
			return;
		}

		var i18n = window.noCommentsAdmin || {};

		var live = wrap.querySelector('#nc-live');

		function announce(message) {
			if (!live) {
				return;
			}
			live.textContent = '';
			setTimeout(function () {
				live.textContent = message;
			}, 30);
		}

		function format(template, value) {
			return template.replace('%s', value);
		}

		function selectScope(value) {
			var radio = wrap.querySelector('input[name="delete_scope"][value="' + value + '"]');
			if (!radio) {
				return;
			}
			radio.checked = true;
			announce(format(i18n.scopeSelected || 'Ámbito seleccionado: %s', value));
		}

		// Clickable counters (click or Enter/Space).
		var counters = wrap.querySelectorAll('.nc-stats [data-scope]');
		Array.prototype.forEach.call(counters, function (el) {
			function activate(event) {
				event.preventDefault();
				selectScope(el.getAttribute('data-scope'));
			}
			el.addEventListener('click', activate);
			el.addEventListener('keydown', function (event) {
				if ('Enter' === event.key || ' ' === event.key) {
					activate(event);
				}
			});
		});

		// Delete form guard. Scoped by ID so the settings import form is untouched.
		var form = document.getElementById('nc-delete-form');
		if (!form) {
			return;
		}

		var errorBox = form.querySelector('.nc-confirm-error');

		form.addEventListener('submit', function (event) {
			var dry = form.querySelector('input[name="dry_run"]');
			if (dry && dry.checked) {
				return;
			}

			var confirmInput = form.querySelector('input[name="confirm"]');
			if (!confirmInput || 'DELETE' !== confirmInput.value) {
				event.preventDefault();
				if (errorBox) {
					errorBox.hidden = false;
				}
				if (confirmInput) {
					confirmInput.focus();
				}
				return false;
			}

			var scope = form.querySelector('input[name="delete_scope"]:checked');
			var strategy = form.querySelector('input[name="delete_strategy"]:checked');
			var reversible = strategy && 'trash' === strategy.value && (!scope || 'trash' !== scope.value);
			var message = reversible
				? (i18n.confirmTrash || '¿Mover los comentarios seleccionados a la Papelera? Podrás restaurarlos después.')
				: format(i18n.confirmDelete || '¿Seguro que deseas ejecutar la limpieza (%s)? Esta acción no se puede deshacer.', scope ? scope.value : '?');

			if (!window.confirm(message)) {
				event.preventDefault();
				return false;
			}

			var submit = form.querySelector('button[type="submit"], input[type="submit"]');
			if (submit) {
				submit.disabled = true;
				submit.classList.add('is-busy');
			}
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
