function abpUpdateRangeFilter(filter) {
	if (!filter) {
		return;
	}

	var minInput = filter.querySelector('.abp-range-input-min');
	var maxInput = filter.querySelector('.abp-range-input-max');
	if (!minInput || !maxInput) {
		return;
	}

	var minValue = parseFloat(minInput.value);
	var maxValue = parseFloat(maxInput.value);
	var unit = filter.getAttribute('data-unit') || '';
	var minBound = parseFloat(minInput.min);
	var maxBound = parseFloat(minInput.max);
	var span = maxBound - minBound;
	var sliders = filter.querySelector('.abp-range-sliders');

	filter.querySelector('.abp-range-storage-min').value = minValue;
	filter.querySelector('.abp-range-storage-max').value = maxValue;
	filter.querySelector('.abp-range-min-label').textContent = minValue + ' ' + unit;
	filter.querySelector('.abp-range-max-label').textContent = maxValue + ' ' + unit;

	if (sliders && span > 0) {
		var start = ((minValue - minBound) / span) * 100;
		var end = ((maxValue - minBound) / span) * 100;

		sliders.style.setProperty('--abp-range-start', start + '%');
		sliders.style.setProperty('--abp-range-end', end + '%');
	}

	minInput.style.zIndex = minValue >= maxBound - 1 ? '4' : '3';
	maxInput.style.zIndex = '2';
}

function abpSubmitFiltersForm(form) {
	if (!form || form.dataset.abpSubmitting === '1') {
		return;
	}

	form.dataset.abpSubmitting = '1';

	if (typeof form.requestSubmit === 'function') {
		form.requestSubmit();
		return;
	}

	form.submit();
}

function abpUpdateMultiselectLabel(wrapper) {
	var label = wrapper.querySelector('[data-abp-multiselect-label]');
	var checked = Array.prototype.slice.call(wrapper.querySelectorAll('input[type="checkbox"]:checked'));
	var names;

	if (!label) {
		return;
	}

	if (!checked.length) {
		label.textContent = label.dataset.defaultLabel || 'Any';
		return;
	}

	names = checked.map(function (input) {
		return input.getAttribute('data-label') || input.value;
	});

	label.textContent = names.slice(0, 2).join(', ') + (names.length > 2 ? ' +' + (names.length - 2) : '');
}

document.addEventListener('click', function (event) {
	var toggle = event.target.closest('.abp-multiselect-toggle');
	if (toggle) {
		var wrapper = toggle.closest('[data-abp-multiselect]');
		var isOpen = wrapper.classList.toggle('is-open');

		toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		return;
	}

	if (!event.target.closest('[data-abp-multiselect]')) {
		document.querySelectorAll('[data-abp-multiselect].is-open').forEach(function (wrapper) {
			wrapper.classList.remove('is-open');
			var button = wrapper.querySelector('.abp-multiselect-toggle');
			if (button) {
				button.setAttribute('aria-expanded', 'false');
			}
		});
	}

	var button = event.target.closest('.abp-thumb');
	if (!button) {
		return;
	}

	var gallery = button.closest('.abp-single-gallery');
	if (!gallery) {
		return;
	}

	var target = gallery.querySelector('[data-abp-main-image]');
	if (!target) {
		return;
	}

	var newSrc = button.getAttribute('data-image');
	if (!newSrc) {
		return;
	}

	target.setAttribute('src', newSrc);
	target.removeAttribute('srcset');
	target.removeAttribute('sizes');

	gallery.querySelectorAll('.abp-thumb').forEach(function (thumb) {
		thumb.classList.remove('is-active');
	});

	button.classList.add('is-active');
});

document.addEventListener('input', function (event) {
	var search = event.target.closest('.abp-multiselect-search');
	if (search) {
		var wrapper = search.closest('[data-abp-multiselect]');
		var query = search.value.trim().toLowerCase();

		wrapper.querySelectorAll('[data-abp-option-label]').forEach(function (option) {
			option.hidden = query && option.getAttribute('data-abp-option-label').indexOf(query) === -1;
		});
		return;
	}

	var input = event.target.closest('.abp-range-input');
	if (!input) {
		return;
	}

	var filter = input.closest('.abp-range-filter');
	if (!filter) {
		return;
	}

	var minInput = filter.querySelector('.abp-range-input-min');
	var maxInput = filter.querySelector('.abp-range-input-max');
	var minValue = parseFloat(minInput.value);
	var maxValue = parseFloat(maxInput.value);

	if (minValue > maxValue) {
		if (input.classList.contains('abp-range-input-min')) {
			maxInput.value = minValue;
		} else {
			minInput.value = maxValue;
		}
	}

	abpUpdateRangeFilter(filter);
});

document.addEventListener('change', function (event) {
	var field = event.target.closest('.abp-filters-form input, .abp-filters-form select');
	if (!field) {
		return;
	}

	var rangeFilter = field.closest('.abp-range-filter');
	if (rangeFilter) {
		abpUpdateRangeFilter(rangeFilter);
	}

	var multiselect = field.closest('[data-abp-multiselect]');
	if (multiselect) {
		abpUpdateMultiselectLabel(multiselect);
	}

	abpSubmitFiltersForm(field.closest('.abp-filters-form'));
});

document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.abp-range-filter').forEach(function (filter) {
		abpUpdateRangeFilter(filter);
	});

	document.querySelectorAll('[data-abp-multiselect]').forEach(function (wrapper) {
		var label = wrapper.querySelector('[data-abp-multiselect-label]');
		if (label) {
			label.dataset.defaultLabel = label.textContent;
		}
		abpUpdateMultiselectLabel(wrapper);
	});
});
