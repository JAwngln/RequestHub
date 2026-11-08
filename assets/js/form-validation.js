/* ==========================================================================
   PPSS Form validation helpers
   - Shows an inline summary of everything that must be corrected.
   - Marks each invalid field and places a message directly under it.
   - Clears a field's error as soon as the user edits it.
   Loaded once by the dashboard shell; works for dynamically loaded pages too.
   ========================================================================== */

(function () {
    'use strict';

    function toElement(target) {
        if (!target) return null;
        if (typeof target === 'string') return document.getElementById(target);
        return target;
    }

    function normalizeErrors(errors) {
        if (!errors) return [];
        if (!Array.isArray(errors)) errors = [errors];

        return errors.map(function (item) {
            if (typeof item === 'string') {
                return { field: null, message: item };
            }
            return item || null;
        }).filter(function (item) {
            return item && item.message;
        });
    }

    function errorIdFor(field) {
        var base = field.id || field.name || 'field';
        return base + '-error';
    }

    function containerFor(field) {
        if (!field || typeof field.closest !== 'function') return null;
        return field.closest('.form-group, .form-field, .line-row, .fleet-field, .input-group') || field.parentNode;
    }

    function removeDescribedBy(field, id) {
        if (!field || !field.getAttribute) return;
        var ids = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        var next = ids.filter(function (value) { return value !== id; });
        if (next.length) {
            field.setAttribute('aria-describedby', next.join(' '));
        } else {
            field.removeAttribute('aria-describedby');
        }
    }

    function clearField(field) {
        if (!field || !field.classList) return;

        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');

        var errorId = errorIdFor(field);
        var oldError = document.getElementById(errorId);
        if (oldError) oldError.remove();

        removeDescribedBy(field, errorId);

        var container = containerFor(field);
        if (container) container.classList.remove('has-error');
    }

    function showFieldError(field, message) {
        if (!field) return;
        clearField(field);

        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');

        var error = document.createElement('span');
        error.className = 'field-error';
        error.id = errorIdFor(field);
        error.setAttribute('role', 'alert');
        error.textContent = message;

        if (typeof field.insertAdjacentElement === 'function') {
            field.insertAdjacentElement('afterend', error);
        } else if (field.parentNode) {
            field.parentNode.appendChild(error);
        }

        var describedBy = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (describedBy.indexOf(error.id) === -1) describedBy.push(error.id);
        field.setAttribute('aria-describedby', describedBy.join(' '));

        var container = containerFor(field);
        if (container) container.classList.add('has-error');
    }

    function ensureSummary(form) {
        var summary = form.querySelector('.form-error-summary');
        if (!summary) {
            summary = document.createElement('div');
            summary.className = 'form-error-summary';
            summary.setAttribute('role', 'alert');
            summary.setAttribute('aria-live', 'assertive');
            summary.tabIndex = -1;
            form.insertBefore(summary, form.firstChild);
        }
        return summary;
    }

    function hideSummary(form) {
        var summary = form.querySelector('.form-error-summary');
        if (!summary) return;
        summary.classList.remove('is-visible');
        summary.replaceChildren();
    }

    function clear(formOrId) {
        var form = toElement(formOrId);
        if (!form) return;

        var fields = form.querySelectorAll('.is-invalid');
        for (var i = 0; i < fields.length; i++) {
            clearField(fields[i]);
        }

        var errors = form.querySelectorAll('.field-error');
        for (var j = 0; j < errors.length; j++) {
            errors[j].remove();
        }

        hideSummary(form);
    }

    function show(formOrId, errors, options) {
        var form = toElement(formOrId);
        if (!form) return false;

        clear(form);

        var list = normalizeErrors(errors);
        if (!list.length) return true;

        options = options || {};
        var heading = options.heading || 'Please correct the following before submitting:';

        var summary = ensureSummary(form);
        var title = document.createElement('strong');
        title.textContent = heading;
        summary.appendChild(title);

        var ul = document.createElement('ul');
        list.forEach(function (item) {
            if (item.field) {
                showFieldError(toElement(item.field), item.message);
            }
            var li = document.createElement('li');
            li.textContent = item.message;
            ul.appendChild(li);
        });
        summary.appendChild(ul);
        summary.classList.add('is-visible');

        // Bring the first problem into view without stealing focus from a
        // half-filled form more than necessary.
        var firstField = list[0].field ? toElement(list[0].field) : null;
        var focusTarget = firstField || summary;
        if (focusTarget && typeof focusTarget.scrollIntoView === 'function') {
            focusTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        if (focusTarget && typeof focusTarget.focus === 'function') {
            try {
                focusTarget.focus({ preventScroll: true });
            } catch (error) {
                focusTarget.focus();
            }
        }

        return false;
    }

    function handleEdit(event) {
        var field = event.target;
        if (!field || !field.matches || !field.matches('input, select, textarea')) return;

        clearField(field);

        var form = field.form || (typeof field.closest === 'function' ? field.closest('form') : null);
        if (!form) return;

        // Once nothing is invalid, the summary no longer applies.
        if (form.querySelectorAll('.is-invalid').length === 0) {
            hideSummary(form);
        }
    }

    document.addEventListener('input', handleEdit, true);
    document.addEventListener('change', handleEdit, true);

    window.showFormErrors = show;
    window.clearFormErrors = clear;
    window.ppssFormValidation = { show: show, clear: clear };
})();
