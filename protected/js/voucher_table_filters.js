(function (window) {
    'use strict';

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function daysInMonth(year, month) {
        return new Date(year, month, 0).getDate();
    }

    function rebuildDayOptions(daySelect, year, month, selected) {
        var keep = selected && selected !== 'all' ? selected : 'all';
        var max = year && month ? daysInMonth(year, month) : 31;
        daySelect.innerHTML = '';
        var allOpt = document.createElement('option');
        allOpt.value = 'all';
        allOpt.textContent = 'Day';
        daySelect.appendChild(allOpt);
        for (var d = 1; d <= max; d++) {
            var opt = document.createElement('option');
            opt.value = pad2(d);
            opt.textContent = pad2(d);
            daySelect.appendChild(opt);
        }
        if (keep !== 'all' && Number(keep) >= 1 && Number(keep) <= max) {
            daySelect.value = pad2(Number(keep));
        } else {
            daySelect.value = 'all';
        }
    }

    function appendYmdSortToUrl(url, ids) {
        ids = ids || {};
        var yearEl = document.getElementById(ids.yearId || 'tableYearFilter');
        var monthEl = document.getElementById(ids.monthId || 'tableMonthFilter');
        var dayEl = document.getElementById(ids.dayId || 'tableDayFilter');
        var sortEl = document.getElementById(ids.sortId || 'tableSortFilter');
        var u = new URL(url, window.location.href);
        u.searchParams.delete('year');
        u.searchParams.delete('month');
        u.searchParams.delete('day');
        u.searchParams.delete('sort');
        u.searchParams.set('page', '1');
        if (yearEl && yearEl.value && yearEl.value !== 'all') {
            u.searchParams.set('year', yearEl.value);
            if (monthEl && monthEl.value && monthEl.value !== 'all') {
                u.searchParams.set('month', monthEl.value);
                if (dayEl && dayEl.value && dayEl.value !== 'all') {
                    u.searchParams.set('day', dayEl.value);
                }
            }
        }
        if (sortEl && sortEl.value && sortEl.value !== 'asc') {
            u.searchParams.set('sort', sortEl.value);
        }
        return u.pathname + u.search + u.hash;
    }

    function bindVoucherTableSequentialFilters(opts) {
        opts = opts || {};
        var yearEl = document.getElementById(opts.yearId || 'tableYearFilter');
        var monthEl = document.getElementById(opts.monthId || 'tableMonthFilter');
        var dayEl = document.getElementById(opts.dayId || 'tableDayFilter');
        var sortEl = document.getElementById(opts.sortId || 'tableSortFilter');
        if (!yearEl || !monthEl || !dayEl) {
            if (sortEl && typeof opts.onChange === 'function') {
                sortEl.addEventListener('change', opts.onChange);
            }
            return;
        }

        function sync(resetDownstream) {
            var hasYear = yearEl.value !== 'all';
            monthEl.disabled = !hasYear;
            if (!hasYear) {
                monthEl.value = 'all';
            }
            var hasMonth = hasYear && monthEl.value !== 'all';
            dayEl.disabled = !hasMonth;
            var yearNum = hasYear ? Number(yearEl.value) : 0;
            var monthNum = hasMonth ? Number(monthEl.value) : 0;
            var keepDay = resetDownstream ? 'all' : dayEl.value;
            rebuildDayOptions(dayEl, yearNum, monthNum, keepDay);
            if (!hasMonth) {
                dayEl.value = 'all';
            }
        }

        function fireChange() {
            if (typeof opts.onChange === 'function') {
                opts.onChange();
            }
        }

        yearEl.addEventListener('change', function () {
            monthEl.value = 'all';
            dayEl.value = 'all';
            sync(true);
            fireChange();
        });
        monthEl.addEventListener('change', function () {
            dayEl.value = 'all';
            sync(true);
            fireChange();
        });
        dayEl.addEventListener('change', fireChange);
        if (sortEl) {
            sortEl.addEventListener('change', fireChange);
        }
        sync(false);
    }

    window.bindVoucherTableSequentialFilters = bindVoucherTableSequentialFilters;
    window.appendVoucherTableYmdSortToUrl = appendYmdSortToUrl;
})(window);
