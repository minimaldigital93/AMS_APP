{{--
    Global mobile responsiveness for data tables.

    Any <table> inside <main> that is NOT already given a bespoke mobile card
    layout (those are wrapped in `hidden md:block`) is auto-converted on phones
    into a stacked "card" list: the <thead> is hidden and each cell shows its
    column label (read from the matching <th>) on the left and its value on the
    right — so no horizontal scrolling is needed.

    Opt a table out by adding the `no-rtable` class to the <table>.
--}}
<style>
{{-- `screen and` matters: print media evaluates width queries against the A4
     page box (~703px portrait), which is below 768px — without the qualifier
     the card-mode styles (td width:100%, text-align:right, centered colspans)
     leak into every printed table. print.css only undoes the display changes. --}}
@media screen and (max-width: 767px) {
    table.rtable { display: block; width: 100%; min-width: 0 !important; }
    table.rtable thead { display: none; }
    table.rtable tbody,
    table.rtable tfoot { display: block; width: 100%; }
    table.rtable tr {
        display: block;
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 12px;
        padding: 4px 12px;
        margin-bottom: 10px;
        background: var(--card-bg, #fff);
    }
    table.rtable tfoot tr { background: var(--hover-bg, #f8fafc); }
    table.rtable td {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        text-align: right;
        padding: 7px 0 !important;
        border: none !important;
        white-space: normal;
    }
    table.rtable tr td:not(:last-child) { border-bottom: 1px solid var(--border-color, #f1f5f9) !important; }
    table.rtable td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 11px;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--text-secondary, #94a3b8);
        text-align: left;
        flex-shrink: 0;
    }
    table.rtable td:not([data-label])::before { content: ""; }
    table.rtable td.rtable-full {
        justify-content: center;
        text-align: center;
    }
    table.rtable td.rtable-full::before { content: none; }
    table.rtable td.rtable-empty { display: none; }
    /* let inputs/selects in stacked form tables breathe */
    table.rtable td input,
    table.rtable td select { max-width: 60%; }
}
</style>
<script>
(function () {
    function stampTable(table) {
        if (table.dataset.rtableDone) return;
        var headRow = table.querySelector('thead tr');
        if (!headRow) return; // no headers to derive labels from — leave as-is
        var labels = Array.prototype.map.call(headRow.children, function (th) {
            return (th.textContent || '').trim();
        });
        table.classList.add('rtable');
        table.querySelectorAll('tbody tr, tfoot tr').forEach(function (row) {
            var colIndex = 0;
            Array.prototype.forEach.call(row.children, function (cell) {
                var span = cell.colSpan || 1;
                var isEmpty = cell.children.length === 0 && (cell.textContent || '').trim() === '';
                if (span > 1) {
                    cell.classList.add('rtable-full');
                    if (isEmpty) cell.classList.add('rtable-empty');
                } else {
                    var label = labels[colIndex] || '';
                    if (label) cell.setAttribute('data-label', label);
                    if (isEmpty) cell.classList.add('rtable-empty');
                }
                colIndex += span;
            });
        });
        table.dataset.rtableDone = '1';
    }

    function init() {
        document.querySelectorAll('main table').forEach(function (table) {
            // Skip tables that have a dedicated mobile card layout (hidden on phones)
            if (table.closest('.hidden')) return;
            if (table.classList.contains('no-rtable')) return;
            try { stampTable(table); } catch (e) { /* never break the page over a table */ }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
