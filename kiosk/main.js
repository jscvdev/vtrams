/*          SIDEBAR JS            */
const hideSidebar = (toggleId, sidebarId) => {
    const toggle = document.getElementById(toggleId),
        sidebar = document.getElementById(sidebarId)

    if(toggleId && sidebar)
    {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('hide-sidebar')
        })
    }
}
hideSidebar('header-toggle', 'sidebar')

const sidebarLink = document.querySelectorAll('.sidebar__link')

function linkColor() {
    sidebarLink.forEach(l => l.classList.remove('active-link'))
    this.classList.add('active-link')
}

sidebarLink.forEach(l => l.addEventListener('click', linkColor))

const expandMain = (toggleId, mainId) => {
    if (document.getElementById(toggleId) && document.getElementById(mainId))
    {
        const
            toggle = document.getElementById(toggleId),
            main = document.getElementById(mainId)

        if(toggleId && main)
        {
            toggle.addEventListener('click', () => {
                main.classList.toggle('expand-main')
            })
        }
    }
}

expandMain('header-toggle', 'main')

// NOTE: Global page pre-loader (#pre-loader) was removed.

/*          SIDEBAR JS            */

/*          FILTER FUNCTION            */

function filterNameFunction(){
    // Declare variables
    var input, filter, table, tr, td, i, txtValue;
    if (document.getElementById("filterInput"))
    {
        input = document.getElementById("filterInput");
    }
    filter = input.value.toUpperCase();
    table = document.getElementById("my-Table");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        // Hide the row initially.
        tr[i].style.display = "none";

        td = tr[i].getElementsByTagName("td");
        for (var j = 0; j < td.length; j++) {
            cell = tr[i].getElementsByTagName("td")[j];
            if (cell) {
                if (cell.innerHTML.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                    break;
                }
            }
        }
    }
}

function filterNameFunctionType(){
    // Declare variables
    var input, filter, table, tr, td, i, txtValue;
    if (document.getElementById("filterInputType"))
    {
        input = document.getElementById("filterInputType");
    }
    filter = input.value.toUpperCase();
    table = document.getElementById("my-Table");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        // Hide the row initially.
        tr[i].style.display = "none";

        td = tr[i].getElementsByTagName("td");
        for (var j = 0; j < td.length; j++) {
            cell = tr[i].getElementsByTagName("td")[j];
            if (cell) {
                if (cell.innerHTML.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                    break;
                }
            }
        }
    }
}

function filterNameFunctionStatus(){
    // Declare variables
    var input, filter, table, tr, td, i, txtValue;
    if (document.getElementById("filterInputStatus"))
    {
        input = document.getElementById("filterInputStatus");
    }
    filter = input.value.toUpperCase();
    table = document.getElementById("my-Table");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        // Hide the row initially.
        tr[i].style.display = "none";

        td = tr[i].getElementsByTagName("td");
        for (var j = 0; j < td.length; j++) {
            cell = tr[i].getElementsByTagName("td")[j];
            if (cell) {
                if (cell.innerHTML.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                    break;
                }
            }
        }
    }
}

function filterNameFunctionSection(){
    // Declare variables
    var input, filter, table, tr, td, i, txtValue;
    if (document.getElementById("filterInputSection"))
    {
        input = document.getElementById("filterInputSection");
    }
    filter = input.value.toUpperCase();
    table = document.getElementById("my-Table");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        // Hide the row initially.
        tr[i].style.display = "none";

        td = tr[i].getElementsByTagName("td");
        for (var j = 0; j < td.length; j++) {
            cell = tr[i].getElementsByTagName("td")[j];
            if (cell) {
                if (cell.innerHTML.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                    break;
                }
            }
        }
    }
}

/*          FILTER FUNCTION            */

/*          CUSTOM DROP DOWN            */
document.addEventListener('click', function(e) {
    const select = document.querySelector('.target_select');
    const selectOptions = document.querySelector('.select-options');
    const selectStyled = document.querySelector('.select-styled');

    if (e.target === selectStyled) {
        if (document.getElementById("target_select"))
        {
            selectOptions.style.display = selectOptions.style.display === 'block' ? 'none' : 'block';
            document.querySelector('.target_select').removeAttribute("id");
        }
        else
        {
            selectOptions.style.display = selectOptions.style.display === 'block' ? 'none' : 'block';
            document.querySelector('.target_select').id = "target_select";
        }
    }

    // else if (select.contains(e.target)) {
    //     selectOptions.style.display = 'none';
    //     document.querySelector('.target_select').id = "target_select";
    // }
});
/*          CUSTOM DROP DOWN            */

/*          CUSTOM LI MENU BAR (NEEDS TO BE FIXED)            */
document.querySelectorAll('.select-options li').forEach(option => {
    option.addEventListener('click', function() {;
        const value = this.getAttribute('data-value');
        const text = this.textContent;
        if (document.getElementById('dropdown') && document.querySelector('.select-styled') && document.querySelector('.select-options'))
        {
            document.getElementById('dropdown').value = value;
            document.querySelector('.select-styled').textContent = text;
        }
    });
    document.addEventListener('click', function(event) {
        const target = event.target;
        const customSelect = document.querySelector('.custom-select');
        if (!customSelect.contains(target)) {
            document.querySelector('.select-options').style.display = 'none';
        }
    });
});
/*          CUSTOM LI MENU BAR (NEEDS TO BE FIXED)            */

/*          INTERNET TIME(GMT+8) PST            */
function updateInternetTime() {
    // Get current UTC time
    const currentUTC = new Date();

    // Convert UTC time to Philippine Standard Time (GMT+8)
    const pstTime = new Date(currentUTC.getTime() + (8 * 60 * 60 * 1000)); // Adding 8 hours in milliseconds

    // Extract PST components
    const year = pstTime.getFullYear();
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const month = monthNames[pstTime.getUTCMonth()]; // Get month name from array
    const day = pstTime.getUTCDate().toString().padStart(2, '0');
    const hours = pstTime.getUTCHours().toString().padStart(2, '0');
    const minutes = pstTime.getUTCMinutes().toString().padStart(2, '0');
    const seconds = pstTime.getUTCSeconds().toString().padStart(2, '0');

    // Format the date and time
    const formattedDateTime = `${month} ${day}, ${year} ${hours}:${minutes}:${seconds}`;

    // Get the DOM element with id 'time'
    const timeElement = document.getElementById('time');

    if (window.innerWidth <= 1065) {
        // If viewport width is 1065px or less, show PST
        timeElement.textContent = `PST: ${formattedDateTime}`;
    } else {
        // Otherwise, show Philippine Standard Time
        timeElement.textContent = `Philippine Standard Time: ${formattedDateTime}`;
    }
}

// Run the update function every second
if (document.getElementById('time')) {
    setInterval(updateInternetTime, 1000);
    updateInternetTime(); // Initial call to set time immediately on page load
}

/*          INTERNET TIME(GMT+8) PST            */



/*          VALIDATIONS VIA JS           */
var datetime_start = document.getElementById("datetime_start")
    , datetime_end = document.getElementById("datetime_end");

function validateDateEffectivity(){
    if(datetime_start.value > datetime_end.value) {
        datetime_start.setCustomValidity("Invalid datetime value");
    } else {
        datetime_start.setCustomValidity('');
    }
}

if (datetime_start)
{
    datetime_start.onchange = validateDateEffectivity;
}
if (datetime_end)
{
    datetime_end.onchange = validateDateEffectivity;
}