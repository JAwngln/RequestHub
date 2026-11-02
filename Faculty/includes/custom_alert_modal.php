<!-- Shared Custom Alert / Confirm Modal -->
<div class="fac-modal-overlay" id="facAlertModal" aria-hidden="true">
    <div class="fac-modal-card" id="facAlertCard" role="alertdialog" aria-modal="true" aria-labelledby="facAlertTitle" aria-describedby="facAlertMessage" data-type="info">
        <div class="fac-modal-icon" id="facAlertIcon"></div>
        <h3 class="fac-modal-title" id="facAlertTitle">Notice</h3>
        <p class="fac-modal-message" id="facAlertMessage"></p>
        <div class="fac-modal-actions">
            <button type="button" class="fac-modal-btn fac-modal-cancel" id="facAlertCancelBtn">Cancel</button>
            <button type="button" class="fac-modal-btn fac-modal-ok" id="facAlertOkBtn">OK</button>
        </div>
    </div>
</div>

<style>
.fac-modal-overlay {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.55);
    -webkit-backdrop-filter: blur(3px);
    backdrop-filter: blur(3px);
    z-index: 100000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s ease;
    box-sizing: border-box;
}
.fac-modal-overlay.show {
    opacity: 1;
    visibility: visible;
}
.fac-modal-card {
    --fac-accent: #1c6b2f;
    --fac-accent-soft: #e8f5e9;
    width: 100%;
    max-width: 420px;
    max-height: 90vh;
    overflow-y: auto;
    background: #ffffff;
    border-radius: 16px;
    padding: 28px 26px 22px;
    box-sizing: border-box;
    text-align: center;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
    transform: translateY(14px) scale(0.97);
    opacity: 0;
    transition: transform 0.24s cubic-bezier(0.2, 0.9, 0.3, 1.15), opacity 0.2s ease;
}
.fac-modal-overlay.show .fac-modal-card {
    transform: translateY(0) scale(1);
    opacity: 1;
}
.fac-modal-card[data-type="success"] { --fac-accent: #1c6b2f; --fac-accent-soft: #e8f5e9; }
.fac-modal-card[data-type="error"]   { --fac-accent: #c62828; --fac-accent-soft: #fdecea; }
.fac-modal-card[data-type="warning"] { --fac-accent: #b26a00; --fac-accent-soft: #fff4e5; }
.fac-modal-card[data-type="info"]    { --fac-accent: #1a56db; --fac-accent-soft: #e8f0fe; }
.fac-modal-card[data-type="confirm"] { --fac-accent: #1c6b2f; --fac-accent-soft: #e8f5e9; }
.fac-modal-card[data-type="danger"]  { --fac-accent: #c62828; --fac-accent-soft: #fdecea; }

.fac-modal-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--fac-accent-soft);
    color: var(--fac-accent);
}
.fac-modal-icon svg {
    width: 28px;
    height: 28px;
    display: block;
}
.fac-modal-title {
    margin: 0 0 8px;
    font-family: "Poppins", sans-serif;
    font-size: 19px;
    font-weight: 600;
    color: #1f2937;
    line-height: 1.3;
}
.fac-modal-message {
    margin: 0 0 22px;
    font-family: "Poppins", sans-serif;
    font-size: 14px;
    line-height: 1.65;
    color: #5b6472;
    white-space: pre-line;
    word-break: break-word;
}
.fac-modal-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}
.fac-modal-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 1 1 0;
    min-width: 120px;
    padding: 11px 20px;
    border: 1px solid transparent;
    border-radius: 9px;
    font-family: "Poppins", sans-serif;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    transition: transform 0.15s ease, background 0.2s ease, box-shadow 0.2s ease;
}
.fac-modal-btn:active {
    transform: scale(0.98);
}
.fac-modal-btn:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(28, 107, 47, 0.22);
}
.fac-modal-ok {
    background: var(--fac-accent);
    color: #ffffff;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.16);
}
.fac-modal-ok:hover {
    filter: brightness(0.92);
}
.fac-modal-cancel {
    background: #f1f3f5;
    color: #495057;
    border-color: #e2e6ea;
}
.fac-modal-cancel:hover {
    background: #e6e9ed;
}
@media (max-width: 480px) {
    .fac-modal-card {
        padding: 24px 18px 18px;
        border-radius: 14px;
    }
    .fac-modal-title {
        font-size: 17px;
    }
    .fac-modal-message {
        font-size: 13px;
    }
    .fac-modal-btn {
        padding: 10px 14px;
        font-size: 13px;
        min-width: 0;
    }
}
</style>

<script>
(function () {
    if (window.__facultyAlertLoaded) return;
    window.__facultyAlertLoaded = true;

    var state = window.__facAlertState = {
        onOk: null,
        onCancel: null,
        isConfirm: false,
        lastFocus: null
    };

    var ICONS = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        confirm: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        danger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
    };

    function els() {
        return {
            overlay: document.getElementById('facAlertModal'),
            card: document.getElementById('facAlertCard'),
            icon: document.getElementById('facAlertIcon'),
            title: document.getElementById('facAlertTitle'),
            message: document.getElementById('facAlertMessage'),
            cancelBtn: document.getElementById('facAlertCancelBtn'),
            okBtn: document.getElementById('facAlertOkBtn')
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
        var e = els();
        if (!e.overlay || !e.card) return false;

        state.lastFocus = document.activeElement;
        state.onOk = typeof opts.onOk === 'function' ? opts.onOk : null;
        state.onCancel = typeof opts.onCancel === 'function' ? opts.onCancel : null;
        state.isConfirm = !!opts.isConfirm;

        if (e.title) e.title.textContent = opts.title || (opts.isConfirm ? 'Confirm' : 'Notice');
        if (e.message) e.message.textContent = opts.message == null ? '' : String(opts.message);
        applyType(opts.type || (opts.isConfirm ? 'confirm' : 'info'));

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
            if (e.okBtn) e.okBtn.focus();
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
        var cb = ok ? state.onOk : state.onCancel;
        state.onOk = null;
        state.onCancel = null;
        state.isConfirm = false;
        close();

        var last = state.lastFocus;
        state.lastFocus = null;
        if (last && typeof last.focus === 'function') {
            try { last.focus(); } catch (err) {}
        }
        if (typeof cb === 'function') cb();
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
            if (window.console && console.warn) console.warn(message);
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
            if (window.confirm(message == null ? '' : String(message))) {
                if (typeof onOk === 'function') onOk();
            } else if (typeof onCancel === 'function') {
                onCancel();
            }
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
        var okTarget = ev.target && ev.target.closest ? ev.target.closest('#facAlertOkBtn') : null;
        if (okTarget) {
            finish(true);
            return;
        }
        var cancelTarget = ev.target && ev.target.closest ? ev.target.closest('#facAlertCancelBtn') : null;
        if (cancelTarget) {
            finish(false);
            return;
        }
        var overlay = document.getElementById('facAlertModal');
        if (overlay && ev.target === overlay) {
            finish(state.isConfirm ? false : true);
        }
    });

    document.addEventListener('keydown', function (ev) {
        var overlay = document.getElementById('facAlertModal');
        if (!overlay || !overlay.classList.contains('show')) return;

        if (ev.key === 'Escape') {
            ev.preventDefault();
            finish(state.isConfirm ? false : true);
            return;
        }

        if (ev.key === 'Tab') {
            var e = els();
            var focusable = [e.cancelBtn, e.okBtn].filter(function (btn) {
                return btn && btn.style.display !== 'none' && !btn.disabled;
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
