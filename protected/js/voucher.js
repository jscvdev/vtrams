document.addEventListener('DOMContentLoaded', function() {
    if (typeof formatAmountTableCells === 'function') {
        formatAmountTableCells('.amount[data-amount]:not([data-amount-skip])');
    }
});
