/**
 * FoodBridge - shared client-side behavior.
 * Loaded once, at the bottom of every page, via includes/footer.php.
 * Every piece here is a progressive enhancement: if JavaScript fails to
 * load, every form still submits to PHP and every link still works -
 * nothing here is load-bearing for the app's actual functionality.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initAdminSidebar();
        initAutoDismissAlerts();
        initTableSearch();
        initDoubleSubmitGuard();
        initConfirmAttributes();
        initFutureDateMin();
    });

    /** Admin sidebar: off-canvas on tablet/mobile, toggled by a topbar button. */
    function initAdminSidebar() {
        var sidebar = document.querySelector('.admin-sidebar');
        var toggleBtn = document.querySelector('[data-sidebar-toggle]');
        var backdrop = document.querySelector('.admin-backdrop');
        if (!sidebar || !toggleBtn) return;

        function open() {
            sidebar.classList.add('show');
            document.body.classList.add('admin-sidebar-open');
            toggleBtn.setAttribute('aria-expanded', 'true');
        }
        function close() {
            sidebar.classList.remove('show');
            document.body.classList.remove('admin-sidebar-open');
            toggleBtn.setAttribute('aria-expanded', 'false');
        }

        toggleBtn.addEventListener('click', function () {
            sidebar.classList.contains('show') ? close() : open();
        });
        if (backdrop) backdrop.addEventListener('click', close);

        // Closing on navigation keeps the sidebar from staying open after
        // following a link on mobile and landing back on a narrow layout.
        sidebar.querySelectorAll('a.nav-link').forEach(function (link) {
            link.addEventListener('click', close);
        });
    }

    /**
     * Success/info alerts (e.g. "Donation added successfully.") fade out on
     * their own after a few seconds so the page doesn't stay cluttered with
     * old confirmations. Warnings and errors are left for the user to
     * dismiss themselves - those are worth reading, not skimming past.
     */
    function initAutoDismissAlerts() {
        document.querySelectorAll('.alert-success, .alert-info').forEach(function (alertEl) {
            setTimeout(function () {
                alertEl.classList.add('fb-fade-out');
                setTimeout(function () { alertEl.remove(); }, 400);
            }, 5000);
        });
    }

    /**
     * Wires up `<input data-table-search="#tableId">` to instantly filter
     * that table's rows by visible text as the user types. This is purely
     * a same-page convenience on top of rows the server already sent -
     * it does not replace or bypass any server-side status/category/search
     * filter the PHP page itself applies (those still control what rows
     * exist in the first place).
     */
    function initTableSearch() {
        document.querySelectorAll('[data-table-search]').forEach(function (input) {
            var table = document.querySelector(input.getAttribute('data-table-search'));
            if (!table) return;
            var rows = table.querySelectorAll('tbody tr');
            var emptyRow = table.querySelector('tbody tr[data-empty-row]');

            input.addEventListener('input', function () {
                var term = input.value.trim().toLowerCase();
                var visibleCount = 0;
                rows.forEach(function (row) {
                    if (row === emptyRow) return;
                    var match = row.textContent.toLowerCase().indexOf(term) !== -1;
                    row.hidden = !match;
                    if (match) visibleCount++;
                });
                if (emptyRow) {
                    emptyRow.hidden = visibleCount !== 0 || term === '';
                }
            });
        });
    }

    /**
     * Disables a form's submit button(s) right after submit, so an
     * impatient double-click can't fire "add donation" or "submit request"
     * twice before the first request has even returned. The button
     * re-enables itself if the browser restores a cached page (back/
     * forward) instead of staying disabled forever.
     */
    function initDoubleSubmitGuard() {
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                if (form.dataset.noGuard === 'true') return;
                // Buttons that are DOM children of the form, plus buttons
                // elsewhere on the page that target it via form="<id>"
                // (used where a table cell's controls can't nest a <form>
                // that spans multiple <td>s - see admin/pickups.php).
                var selector = 'button[type="submit"]';
                if (form.id) selector += ', button[type="submit"][form="' + form.id + '"]';
                document.querySelectorAll(selector).forEach(function (btn) {
                    if (form.id ? (btn.form !== form) : !form.contains(btn)) return;
                    btn.disabled = true;
                    btn.dataset.originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + btn.textContent.trim();
                });
            });
        });
        window.addEventListener('pageshow', function (evt) {
            if (!evt.persisted) return;
            document.querySelectorAll('button[type="submit"][disabled]').forEach(function (btn) {
                btn.disabled = false;
                if (btn.dataset.originalText) btn.innerHTML = btn.dataset.originalText;
            });
        });
    }

    /**
     * `<form data-confirm="Cancel this donation?">` (or a single button
     * with the same attribute) asks before submitting. New markup can use
     * this instead of a hand-written onclick="return confirm(...)".
     */
    function initConfirmAttributes() {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('submit', function (evt) {
                if (!window.confirm(el.getAttribute('data-confirm'))) {
                    evt.preventDefault();
                }
            });
        });
    }

    /** Any datetime-local input marked data-min-now gets "now" as its floor. */
    function initFutureDateMin() {
        document.querySelectorAll('input[type="datetime-local"][data-min-now]').forEach(function (input) {
            if (input.value) return; // don't clobber a value being edited
            var now = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
            input.min = now.toISOString().slice(0, 16);
        });
    }
})();
