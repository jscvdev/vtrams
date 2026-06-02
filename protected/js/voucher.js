function normalizeAmountDisplay(raw) {
    var v = String(raw || '').replace(/,/g, '').trim();
    if (v === '') return '';
    v = v.replace(/[^\d.]/g, '');
    var dot = v.indexOf('.');
    if (dot !== -1) {
        v = v.slice(0, dot + 1) + v.slice(dot + 1).replace(/\./g, '');
    }
    return v;
}

function formatAmountDisplay(raw) {
    var normalized = normalizeAmountDisplay(raw);
    if (normalized === '') return '';
    var parts = normalized.split('.');
    var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.length > 1 ? intPart + '.' + parts[1] : intPart;
}

const originalCell = document.querySelectorAll('.amount');

originalCell.forEach(element => {
    const formattedNumber = formatAmountDisplay(element.innerText);
    if (formattedNumber !== '') {
        element.innerText = formattedNumber;
    }
});
