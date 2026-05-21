(function ($) {
	'use strict';

	function slugify(text) {
		return String(text || '')
			.toLowerCase()
			.trim()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');
	}

	function parseTableValue(value) {
		if (!value) {
			return [];
		}

		return String(value)
			.split(/\r?\n/)
			.filter(function (line) {
				return line.trim() !== '';
			})
			.map(function (line) {
				return line.split('|').map(function (cell) {
					return cell.trim();
				});
			});
	}

	function serializeTable($builder) {
		var lines = [];

		$builder.find('.abp-table-grid-row').each(function () {
			var values = [];
			$(this).find('.abp-table-grid-cell').each(function () {
				values.push($(this).val().trim());
			});
			lines.push(values.join('|'));
		});

		$builder.find('.abp-table-storage').val(lines.join('\n'));
	}

	function renderTableGrid($builder, rows, cols, matrix) {
		var $grid = $builder.find('.abp-table-grid');
		$grid.empty();

		rows = Math.max(0, parseInt(rows, 10) || 0);
		cols = Math.max(0, parseInt(cols, 10) || 0);

		var totalRows = rows > 0 || cols > 0 ? rows + 1 : 0;
		var totalCols = rows > 0 || cols > 0 ? cols + 1 : 0;

		for (var row = 0; row < totalRows; row += 1) {
			var $row = $('<div class="abp-table-grid-row"></div>').css('grid-template-columns', 'repeat(' + totalCols + ', minmax(0, 1fr))');

			for (var col = 0; col < totalCols; col += 1) {
				var value = matrix[row] && typeof matrix[row][col] !== 'undefined' ? matrix[row][col] : '';
				var placeholder = athsbpAdmin.cellPlaceholder;
				var $cell = $('<input type="text" class="abp-table-grid-cell">');

				if (row === 0 && col === 0) {
					$cell.addClass('abp-is-corner abp-is-header abp-is-row-title');
					placeholder = athsbpAdmin.cellPlaceholder;
					$cell.val(value);
				} else if (row === 0) {
					$cell.addClass('abp-is-header');
					placeholder = athsbpAdmin.columnTitlePlaceholder;
					$cell.val(value);
				} else if (col === 0) {
					$cell.addClass('abp-is-row-title');
					placeholder = athsbpAdmin.rowTitlePlaceholder;
					$cell.val(value);
				} else {
					$cell.val(value);
				}

				$cell.attr('placeholder', placeholder);

				$row.append($cell);
			}

			$grid.append($row);
		}

		serializeTable($builder);
	}

	function syncTableBuilder($builder) {
		var matrix = parseTableValue($builder.find('.abp-table-storage').val());
		var rows = $builder.find('.abp-table-rows').val();
		var cols = $builder.find('.abp-table-cols').val();

		if (!rows) {
			rows = Math.max(0, matrix.length - (matrix.length ? 1 : 0));
		}

		if (!cols) {
			matrix.forEach(function (row) {
				cols = Math.max(parseInt(cols || 0, 10), Math.max(0, row.length - (row.length ? 1 : 0)));
			});
		}

		rows = parseInt(rows, 10) || 0;
		cols = parseInt(cols, 10) || 0;

		$builder.find('.abp-table-rows').val(rows);
		$builder.find('.abp-table-cols').val(cols);
		renderTableGrid($builder, rows, cols, matrix);
	}

	function renderGalleryPreview(container, ids) {
		var preview = container.find('.abp-gallery-preview');
		preview.empty();

		if (!ids.length || typeof wp === 'undefined' || !wp.media) {
			return;
		}

		ids.forEach(function (id) {
			var attachment = wp.media.attachment(id);
			attachment.fetch().then(function () {
				var url = attachment.get('sizes') && attachment.get('sizes').thumbnail ? attachment.get('sizes').thumbnail.url : attachment.get('url');
				if (url) {
					preview.append($('<img>', { src: url, alt: '' }));
				}
			});
		});
	}

	function syncFilterOrderRows() {
		$('.abp-sortable-filter-row').each(function () {
			var $row = $(this);
			var slug = $row.find('.abp-filter-slug').val() || $row.attr('data-filter-slug') || '';

			$row.attr('data-filter-slug', slug);
			$row.find('.abp-filter-order-input').val(slug);
		});
	}

	function initFilterSorting() {
		$('.abp-sortable-filter-row')
			.removeAttr('draggable')
			.addClass('abp-native-draggable');
	}

	var pointerDrag = {
		active: false,
		row: null,
		list: null
	};

	function startFilterRowDrag(handle, originalEvent) {
		var row = $(handle).closest('.abp-sortable-filter-row')[0];
		var list = $(handle).closest('.abp-sortable-filter-list')[0];

		if (!row || !list) {
			return false;
		}

		pointerDrag.active = true;
		pointerDrag.row = row;
		pointerDrag.list = list;
		row.classList.add('abp-is-dragging');
		document.body.classList.add('abp-filter-dragging');

		if (handle.setPointerCapture && originalEvent.pointerId) {
			handle.setPointerCapture(originalEvent.pointerId);
		}

		return true;
	}

	function moveFilterRowDrag(clientX, clientY) {
		var target;
		var targetRow;
		var box;

		if (!pointerDrag.active || !pointerDrag.row || !pointerDrag.list) {
			return;
		}

		target = document.elementFromPoint(clientX, clientY);

		if (!target) {
			return;
		}

		targetRow = $(target).closest('.abp-sortable-filter-row')[0];
		if (!targetRow || targetRow === pointerDrag.row || targetRow.parentNode !== pointerDrag.list) {
			return;
		}

		box = targetRow.getBoundingClientRect();
		if (clientY < box.top + box.height / 2) {
			pointerDrag.list.insertBefore(pointerDrag.row, targetRow);
		} else {
			pointerDrag.list.insertBefore(pointerDrag.row, targetRow.nextSibling);
		}
	}

	function endFilterRowDrag() {
		if (!pointerDrag.active) {
			return;
		}

		if (pointerDrag.row) {
			pointerDrag.row.classList.remove('abp-is-dragging');
		}

		document.body.classList.remove('abp-filter-dragging');
		pointerDrag.active = false;
		pointerDrag.row = null;
		pointerDrag.list = null;
		syncFilterOrderRows();
	}

	$(function () {
		$('.abp-table-builder').each(function () {
			syncTableBuilder($(this));
		});

		initFilterSorting();
		syncFilterOrderRows();
	});

	$(document).on('pointerdown', '.abp-drag-handle', function (event) {
		event.preventDefault();
		startFilterRowDrag(this, event.originalEvent);
	});

	$(document).on('pointermove', function (event) {
		if (!pointerDrag.active || !pointerDrag.row || !pointerDrag.list) {
			return;
		}

		event.preventDefault();
		moveFilterRowDrag(event.originalEvent.clientX, event.originalEvent.clientY);
	});

	$(document).on('pointerup pointercancel', function () {
		endFilterRowDrag();
	});

	$(document).on('mousedown', '.abp-drag-handle', function (event) {
		if (pointerDrag.active) {
			return;
		}

		event.preventDefault();
		startFilterRowDrag(this, event);
	});

	$(document).on('mousemove', function (event) {
		if (!pointerDrag.active || !pointerDrag.row || !pointerDrag.list) {
			return;
		}

		event.preventDefault();
		moveFilterRowDrag(event.clientX, event.clientY);
	});

	$(document).on('mouseup', function () {
		endFilterRowDrag();
	});

	$(document).on('touchstart', '.abp-drag-handle', function (event) {
		if (pointerDrag.active) {
			return;
		}

		event.preventDefault();
		startFilterRowDrag(this, event.originalEvent.touches[0] || event.originalEvent);
	});

	$(document).on('touchmove', function (event) {
		var touch = event.originalEvent.touches[0];

		if (!touch || !pointerDrag.active || !pointerDrag.row || !pointerDrag.list) {
			return;
		}

		event.preventDefault();
		moveFilterRowDrag(touch.clientX, touch.clientY);
	});

	$(document).on('touchend touchcancel', function () {
		endFilterRowDrag();
	});

	$(document).on('click', '.abp-add-filter-group', function () {
		var wrapper = $(this).closest('.abp-filter-groups');
		var list = wrapper.find('.abp-filter-group-list');
		var index = parseInt(wrapper.attr('data-next-index'), 10) || 0;
		var template = wp.template('abp-filter-group-row');

		list.append(template({ index: index }));
		wrapper.attr('data-next-index', index + 1);
		initFilterSorting();
		syncFilterOrderRows();
	});

	$(document).on('input', '.abp-filter-label', function () {
		var $row = $(this).closest('.abp-filter-group-row');
		var $slug = $row.find('.abp-filter-slug');

		if ($slug.val().trim() === '' || $slug.data('auto') !== false) {
			$slug.val(slugify($(this).val()));
			$slug.data('auto', true);
			syncFilterOrderRows();
		}
	});

	$(document).on('input', '.abp-filter-slug', function () {
		$(this).data('auto', false);
		syncFilterOrderRows();
	});

	$(document).on('click', '.abp-remove-filter-group', function () {
		$(this).closest('.abp-filter-group-row').remove();
	});

	$(document).on('click', '.athsbp-settings-tab', function () {
		var target = $(this).data('athsbp-settings-tab');

		$('.athsbp-settings-tab').removeClass('is-active');
		$(this).addClass('is-active');

		$('.athsbp-settings-panel').removeClass('is-active');
		$('.athsbp-settings-panel[data-athsbp-settings-panel="' + target + '"]').addClass('is-active');
	});

	$(document).on('click', '.abp-select-gallery', function (event) {
		event.preventDefault();
		var container = $(this).closest('.abp-field');
		var input = container.find('.abp-gallery-input');
		var frame = wp.media({
			title: athsbpAdmin.galleryTitle,
			button: { text: athsbpAdmin.galleryButton },
			multiple: true
		});

		frame.on('select', function () {
			var ids = frame.state().get('selection').map(function (attachment) {
				return attachment.toJSON().id;
			});
			input.val(ids.join(','));
			renderGalleryPreview(container, ids);
		});

		frame.open();
	});

	$(document).on('click', '.abp-clear-gallery', function (event) {
		event.preventDefault();
		var container = $(this).closest('.abp-field');
		container.find('.abp-gallery-input').val('');
		container.find('.abp-gallery-preview').empty();
	});

	$(document).on('click', '.abp-build-table', function () {
		var $builder = $(this).closest('.abp-table-builder');
		var rows = $builder.find('.abp-table-rows').val();
		var cols = $builder.find('.abp-table-cols').val();
		var current = parseTableValue($builder.find('.abp-table-storage').val());

		renderTableGrid($builder, rows, cols, current);
	});

	$(document).on('input', '.abp-table-grid-cell', function () {
		serializeTable($(this).closest('.abp-table-builder'));
	});

	$(document).on('click', '.abp-add-table', function (event) {
		event.preventDefault();

		var $wrapper = $(this).closest('.abp-field').find('.abp-table-builders');
		var $source = $wrapper.find('.abp-table-builder').first();
		var $builder = $source.clone();
		var nextIndex = parseInt($wrapper.attr('data-next-index'), 10) || $wrapper.find('.abp-table-builder').length;

		$builder.attr('data-table-index', nextIndex);
		$builder.find('.abp-table-rows').val(0);
		$builder.find('.abp-table-cols').val(0);
		$builder.find('.abp-table-storage').val('');
		$builder.find('.abp-table-grid').empty();

		$wrapper.append($builder);
		$wrapper.attr('data-next-index', nextIndex + 1);
		syncTableBuilder($builder);
	});

	$(document).on('click', '.abp-remove-table', function (event) {
		event.preventDefault();

		var $wrapper = $(this).closest('.abp-table-builders');
		var $builder = $(this).closest('.abp-table-builder');

		if ($wrapper.find('.abp-table-builder').length <= 1) {
			$builder.find('.abp-table-rows').val(0);
			$builder.find('.abp-table-cols').val(0);
			$builder.find('.abp-table-storage').val('');
			$builder.find('.abp-table-grid').empty();
			syncTableBuilder($builder);
			return;
		}

		$builder.remove();
	});

	$(document).on('click', '.abp-select-pdf', function (event) {
		event.preventDefault();

		var container = $(this).closest('.abp-pdf-field');
		var input = container.find('.abp-pdf-input');
		var preview = container.find('.abp-pdf-preview');
		var frame = wp.media({
			title: athsbpAdmin.pdfTitle,
			button: { text: athsbpAdmin.pdfButton },
			library: { type: 'application/pdf' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			input.val(attachment.id);
			preview.html($('<a>', {
				href: attachment.url,
				target: '_blank',
				rel: 'noopener',
				text: attachment.title || attachment.filename || attachment.url
			}));
		});

		frame.open();
	});

	$(document).on('click', '.abp-clear-pdf', function (event) {
		event.preventDefault();

		var container = $(this).closest('.abp-pdf-field');
		container.find('.abp-pdf-input').val('');
		container.find('.abp-pdf-preview').empty();
	});
})(jQuery);
