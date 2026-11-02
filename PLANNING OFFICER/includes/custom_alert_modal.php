<!-- Shared Custom Alert / Confirm Modal (Planning Officer) -->
<script>
(function () {
    if (window.__poAlertLoaded) return;
    window.__poAlertLoaded = true;

    var CSS = ''
        + '.po-modal-overlay{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.55);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px);z-index:100000;opacity:0;visibility:hidden;transition:opacity .2s ease,visibility .2s ease;box-sizing:border-box;}'
        + '.po-modal-overlay.show{opacity:1;visibility:visible;}'
        + '.po-modal-card{--po-accent:#1c6b2f;--po-accent-soft:#e8f5e9;width:100%;max-width:420px;max-height:90vh;overflow-y:auto;background:#fff;border-radius:16px;padding:28px 26px 22px;box-sizing:border-box;text-align:center;box-shadow:0 24px 60px rgba(15,23,42,.28);transform:translateY(14px) scale(.97);opacity:0;transition:transform .24s cubic-bezier(.2,.9,.3,1.15),opacity .2s ease;font-family:"Poppins","Segoe UI",Arial,sans-serif;}'
        + '.po-modal-overlay.show .po-modal-card{transform:translateY(0) scale(1);opacity:1;}'
        + '.po-modal-card[data-type="success"]{--po-accent:#1c6b2f;--po-accent-soft:#e8f5e9;}'
        + '.po-modal-card[data-type="error"]{--po-accent:#c62828;--po-accent-soft:#fdecea;}'
        + '.po-modal-card[data-type="warning"]{--po-accent:#b26a00;--po-accent-soft:#fff4e5;}'
        + '.po-modal-card[data-type="info"]{--po-accent:#1a56db;--po-accent-soft:#e8f0fe;}'
        + '.po-modal-card[data-type="confirm"]{--po-accent:#1c6b2f;--po-accent-soft:#e8f5e9;}'
        + '.po-modal-card[data-type="danger"]{--po-accent:#c62828;--po-accent-soft:#fdecea;}'
        + '.po-modal-icon{width:58px;height:58px;margin:0 auto 16px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--po-accent-soft);color:var(--po-accent);}'
        + '.po-modal-icon svg{width:28px;height:28px;display:block;}'
        + '.po-modal-title{margin:0 0 8px;font-size:19px;font-weight:600;color:#1f2937;line-height:1.3;}'
        + '.po-modal-message{margin:0 0 22px;font-size:14px;line-height:1.65;color:#5b6472;white-space:pre-line;word-break:break-word;}'
        + '.po-modal-field{display:none;margin:0 0 18px;text-align:left;}'
        + '.po-modal-input{width:100%;padding:12px 14px;border:1px solid #d5dae1;border-radius:9px;font-family:inherit;font-size:14px;color:#1f2937;box-sizing:border-box;transition:border-color .2s ease,box-shadow .2s ease;}'
        + '.po-modal-input:focus{outline:none;border-color:var(--po-accent);box-shadow:0 0 0 3px rgba(28,107,47,.15);}'
        + '.po-modal-field.has-error .po-modal-input{border-color:#c62828;box-shadow:0 0 0 3px rgba(198,40,40,.12);}'
        + '.po-modal-error{display:none;margin:6px 0 0;font-size:12px;line-height:1.4;color:#c62828;}'
        + '.po-modal-field.has-error .po-modal-error{display:block;}'
        + '.po-modal-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}'
        + '.po-modal-btn{display:inline-flex;align-items:center;justify-content:center;flex:1 1 0;min-width:120px;padding:11px 20px;border:1px solid transparent;border-radius:9px;font-family:inherit;font-size:14px;font-weight:600;text-align:center;cursor:pointer;transition:transform .15s ease,background .2s ease,box-shadow .2s ease;}'
        + '.po-modal-btn:active{transform:scale(.98);}'
        + '.po-modal-btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(28,107,47,.22);}'
        + '.po-modal-ok{background:var(--po-accent);color:#fff;box-shadow:0 6px 16px rgba(15,23,42,.16);}'
        + '.po-modal-ok:hover{filter:brightness(.92);}'
        + '.po-modal-cancel{background:#f1f3f5;color:#495057;border-color:#e2e6ea;}'
        + '.po-modal-cancel:hover{background:#e6e9ed;}'
        + '@media (max-width:480px){.po-modal-card{padding:24px 18px 18px;border-radius:14px;}.po-modal-title{font-size:17px;}.po-modal-message{font-size:13px;}.po-modal-btn{padding:10px 14px;font-size:13px;min-width:0;}}';

    var ICONS = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        confirm: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        danger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
    };

    var state = window.__poAlertState = {
        onOk: null,
        onCancel: null,
        isConfirm: false,
        inputMode: false,
        validate: null,
        lastFocus: null
    };

    function injectStyles() {
        if (document.getElementById('po-alert-styles')) return;
        var style = document.createElement('style');
        style.id = 'po-alert-styles';
        style.textContent = CSS;
        (document.head || document.documentElement).appendChild(style);
    }

    function buildModal() {
        if (document.getElementById('poAlertModal')) return;
        if (!document.body) {
            document.addEventListener('DOMContentLoaded', buildModal);
            return;
        }
        var overlay = document.createElement('div');
        overlay.className = 'po-modal-overlay';
        overlay.id = 'poAlertModal';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = ''
            + '<div class="po-modal-card" id="poAlertCard" role="alertdialog" aria-modal="true" aria-labelledby="poAlertTitle" aria-describedby="poAlertMessage" data-type="info">'
            + '  <div class="po-modal-icon" id="poAlertIcon"></div>'
            + '  <h3 class="po-modal-title" id="poAlertTitle">Notice</h3>'
            + '  <p class="po-modal-message" id="poAlertMessage"></p>'
            + '  <div class="po-modal-field" id="poAlertField">'
            + '    <input type="text" class="po-modal-input" id="poAlertInput" autocomplete="off">'
            + '    <p class="po-modal-error" id="poAlertError"></p>'
            + '  </div>'
            + '  <div class="po-modal-actions">'
            + '    <button type="button" class="po-modal-btn po-modal-cancel" id="poAlertCancelBtn">Cancel</button>'
            + '    <button type="button" class="po-modal-btn po-modal-ok" id="poAlertOkBtn">OK</button>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(overlay);
    }

    injectStyles();
    buildModal();

    function els() {
        return {
            overlay: document.getElementById('poAlertModal'),
            card: document.getElementById('poAlertCard'),
            icon: document.getElementById('poAlertIcon'),
            title: document.getElementById('poAlertTitle'),
            message: document.getElementById('poAlertMessage'),
            field: document.getElementById('poAlertField'),
            input: document.getElementById('poAlertInput'),
            error: document.getElementById('poAlertError'),
            cancelBtn: document.getElementById('poAlertCancelBtn'),
            okBtn: document.getElementById('poAlertOkBtn')
        };
    }

    function applyType(type) {
        var e = els();
        if (!e.card) return;
        var allowed = { success: 1, error: 1, warning: 1, info: 1, confirm: 1, danger: 1 };
        var t = allowed[type] ? type : 'info';
        e.card.setAttribute('data-type', t);
        if (e.icon) e.icon.innerHTML = ICONS[t] || ICONS.info;
    }

    function open(opts) {
        injectStyles();
        buildModal();
        var e = els();
        if (!e.overlay || !e.card) return false;

        state.lastFocus = document.activeElement;
        state.onOk = typeof opts.onOk === 'function' ? opts.onOk : null;
        state.onCancel = typeof opts.onCancel === 'function' ? opts.onCancel : null;
        state.isConfirm = !!opts.isConfirm;
        state.inputMode = !!opts.input;
        state.validate = typeof opts.validate === 'function' ? opts.validate : null;

        if (e.title) e.title.textContent = opts.title || (opts.isConfirm ? 'Confirm' : 'Notice');
        if (e.message) e.message.textContent = opts.message == null ? '' : String(opts.message);
        applyType(opts.type || (opts.isConfirm ? 'confirm' : 'info'));

        if (e.field) {
            e.field.style.display = state.inputMode ? 'block' : 'none';
            e.field.classList.remove('has-error');
        }
        if (e.error) e.error.textContent = '';
        if (e.input) {
            e.input.type = opts.inputType || 'text';
            e.input.value = opts.value == null ? '' : String(opts.value);
            e.input.placeholder = opts.placeholder || '';
            e.input.setAttribute('autocomplete', opts.autocomplete || 'off');
        }

        if (e.cancelBtn) {
            e.cancelBtn.style.display = opts.isConfirm ? 'inline-flex' : 'none';
            e.cancelBtn.textContent = opts.cancelText || 'Cancel';
        }
        if (e.okBtn) {
            e.okBtn.textContent = opts.okText || (opts.isConfirm ? 'Yes' : 'OK');
        }

        e.overlay.classList.add('show');
        e.overlay.setAttribute('aria-hidden', 'false');

        setTimeout(function () {
            if (state.inputMode && e.input) {
                e.input.focus();
                if (e.input.select) e.input.select();
            } else if (e.okBtn) {
                e.okBtn.focus();
            }
        }, 60);
        return true;
    }

    function close() {
        var e = els();
        if (!e.overlay) return;
        e.overlay.classList.remove('show');
        e.overlay.setAttribute('aria-hidden', 'true');
    }

    function finish(ok) {
        var e = els();
        var wasInput = state.inputMode;
        var value = wasInput && e.input ? e.input.value : '';

        if (ok && wasInput && state.validate) {
            var error = state.validate(value);
            if (error) {
                if (e.error) e.error.textContent = String(error);
                if (e.field) e.field.classList.add('has-error');
                if (e.input) {
                    e.input.focus();
                    if (e.input.select) e.input.select();
                }
                return;
            }
        }

        var cb = ok ? state.onOk : state.onCancel;
        state.onOk = null;
        state.onCancel = null;
        state.isConfirm = false;
        state.inputMode = false;
        state.validate = null;
        close();

        var last = state.lastFocus;
        state.lastFocus = null;
        if (last && typeof last.focus === 'function') {
            try { last.focus(); } catch (err) {}
        }
        if (typeof cb === 'function') cb(wasInput ? value : undefined);
    }

    window.showAppAlert = function (message, title, onOk, options) {
        options = options || {};
        var opened = open({
            message: message,
            title: title || 'Notice',
            onOk: onOk,
            isConfirm: false,
            type: options.type || 'info',
            okText: options.okText
        });
        if (!opened) {
            alert(message == null ? '' : String(message));
            if (typeof onOk === 'function') onOk();
        }
    };

    window.showAppConfirm = function (message, title, onOk, onCancel, options) {
        options = options || {};
        var opened = open({
            message: message,
            title: title || 'Confirm',
            onOk: onOk,
            onCancel: onCancel,
            isConfirm: true,
            type: options.type || 'confirm',
            okText: options.okText,
            cancelText: options.cancelText
        });
        if (!opened) {
            if (confirm(message == null ? '' : String(message))) {
                if (typeof onOk === 'function') onOk();
            } else if (typeof onCancel === 'function') {
                onCancel();
            }
        }
    };

    window.showAppPrompt = function (message, title, onOk, onCancel, options) {
        options = options || {};
        var opened = open({
            message: message,
            title: title || 'Enter Value',
            onOk: onOk,
            onCancel: onCancel,
            isConfirm: true,
            type: options.type || 'confirm',
            okText: options.okText,
            cancelText: options.cancelText,
            input: true,
            inputType: options.inputType || 'text',
            placeholder: options.placeholder || '',
            value: options.value,
            autocomplete: options.autocomplete,
            validate: options.validate
        });
        if (!opened) {
            var value = prompt(message == null ? '' : String(message), options.value == null ? '' : String(options.value));
            if (value === null) {
                if (typeof onCancel === 'function') onCancel();
                return;
            }
            if (typeof options.validate === 'function') {
                var error = options.validate(value);
                if (error) {
                    alert(error);
                    return;
                }
            }
            if (typeof onOk === 'function') onOk(value);
        }
    };

    window.closeAppAlert = function () {
        finish(state.isConfirm ? false : true);
    };
    window.appAlertOk = function () {
        finish(true);
    };
    window.appAlertCancel = function () {
        finish(false);
    };

    document.addEventListener('click', function (ev) {
        var okTarget = ev.target && ev.target.closest ? ev.target.closest('#poAlertOkBtn') : null;
        if (okTarget) {
            finish(true);
            return;
        }
        var cancelTarget = ev.target && ev.target.closest ? ev.target.closest('#poAlertCancelBtn') : null;
        if (cancelTarget) {
            finish(false);
            return;
        }
        var overlay = document.getElementById('poAlertModal');
        if (overlay && ev.target === overlay) {
            finish(state.isConfirm ? false : true);
        }
    });

    document.addEventListener('keydown', function (ev) {
        var overlay = document.getElementById('poAlertModal');
        if (!overlay || !overlay.classList.contains('show')) return;

        if (ev.key === 'Escape') {
            ev.preventDefault();
            finish(state.isConfirm ? false : true);
            return;
        }

        if (state.inputMode && ev.key === 'Enter' && document.activeElement === els().input) {
            ev.preventDefault();
            finish(true);
            return;
        }

        if (ev.key === 'Tab') {
            var e = els();
            var focusable = [e.input, e.cancelBtn, e.okBtn].filter(function (el) {
                return el && el.style.display !== 'none' && !el.disabled && (el === e.input ? state.inputMode : true);
            });
            if (!focusable.length) return;

            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (ev.shiftKey && document.activeElement === first) {
                ev.preventDefault();
                last.focus();
            } else if (!ev.shiftKey && document.activeElement === last) {
                ev.preventDefault();
                first.focus();
            }
        }
    });
})();
</script>
