document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM fully loaded and parsed');

    const contentElements = document.querySelectorAll('.content-wrapper');
    console.log('Found elements:', contentElements);

    contentElements.forEach(element => {
        element.classList.remove('contentSlideIn');
        element.classList.add('contentSlideIn');
    });
});

/*          SIDEBAR JS            */
const hideSidebar = (toggleId, sidebarId) => {
    const toggle = document.getElementById(toggleId),
        sidebar = document.getElementById(sidebarId)

    if (toggleId && sidebar) {
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

function expandMain(toggleId, mainId) {
    if (document.getElementById(toggleId) && document.getElementById(mainId)) {
        const
            toggle = document.getElementById(toggleId),
            main = document.getElementById(mainId)

        if (toggleId && main) {
            toggle.addEventListener('click', () => {
                main.classList.toggle('expand-main')
            })
        }
    }
}

// Call expandMain when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        expandMain('header-toggle', 'main');
    });
} else {
    expandMain('header-toggle', 'main');
}

// NOTE: Global page pre-loader (#pre-loader) was removed. Use table-level loaders instead.

/*          SIDEBAR JS            */

/*          FILTER FUNCTION            */

function filterNameFunction() {
    // Declare variables
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("filterInput");
    table = document.getElementById("my-Table");
    if (!input || !table) return;
    filter = input.value.toUpperCase();
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

(function bindFilterInputEnter() {
    function attach() {
        var el = document.getElementById("filterInput");
        if (!el || el.getAttribute("data-filter-enter-bound") === "1") return;
        if (!document.getElementById("my-Table")) return;
        el.setAttribute("data-filter-enter-bound", "1");
        el.removeAttribute("onkeyup");
        el.addEventListener("keydown", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                filterNameFunction();
            }
        });
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", attach);
    } else {
        attach();
    }
})();

function filterNameFunctionType() {
    // Declare variables
    var input, filter, table, tr, td, i, txtValue;
    if (document.getElementById("filterInputType")) {
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

function filterNameFunctionStatus(input) {
    // Declare variables
    var filter, table, tr, td, i, txtValue;
    console.log(input);

    filter = input.toUpperCase();
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

// function filterNameFunctionStatus(input){
//     // Declare variables
//     var input, filter, table, tr, td, i, txtValue;
//     if (document.getElementById("filterInputStatus"))
//     {
//         input = document.getElementById("filterInputStatus");
//     }
//     filter = input.value.toUpperCase();
//     table = document.getElementById("my-Table");
//     tr = table.getElementsByTagName("tr");

//     for (i = 1; i < tr.length; i++) {
//         // Hide the row initially.
//         tr[i].style.display = "none";

//         td = tr[i].getElementsByTagName("td");
//         for (var j = 0; j < td.length; j++) {
//             cell = tr[i].getElementsByTagName("td")[j];
//             if (cell) {
//                 if (cell.innerHTML.toUpperCase().indexOf(filter) > -1) {
//                     tr[i].style.display = "";
//                     break;
//                 }
//             }
//         }
//     }
// }

function filterNameFunctionSection() {
    // Declare variables
    var input, filter, table, tr, td, i, txtValue;
    if (document.getElementById("filterInputSection")) {
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

/*          HEADER USER MENU            */
function setHeaderUserMenuOpen(isOpen) {
    const headerMenu = document.querySelector('.header-user-menu');
    if (!headerMenu) {
        return;
    }

    const trigger = headerMenu.querySelector('.header-user-menu__trigger');
    const selectOptions = headerMenu.querySelector('.header-user-menu__dropdown');
    if (!trigger || !selectOptions) {
        return;
    }

    selectOptions.style.display = isOpen ? 'block' : 'none';
    selectOptions.classList.toggle('is-open', isOpen);
    trigger.classList.toggle('is-open', isOpen);
    trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

document.addEventListener('click', function (e) {
    const headerMenu = document.querySelector('.header-user-menu');
    if (!headerMenu) {
        return;
    }

    const trigger = headerMenu.querySelector('.header-user-menu__trigger');
    if (!trigger) {
        return;
    }

    if (trigger === e.target || trigger.contains(e.target)) {
        const selectOptions = headerMenu.querySelector('.header-user-menu__dropdown');
        const isOpen = selectOptions && selectOptions.style.display === 'block';
        setHeaderUserMenuOpen(!isOpen);
        return;
    }

    if (!headerMenu.contains(e.target)) {
        setHeaderUserMenuOpen(false);
    }
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        setHeaderUserMenuOpen(false);
    }
});

document.querySelectorAll('.header-user-menu__dropdown li[role="menuitem"]').forEach(function (option) {
    option.addEventListener('click', function () {
        setHeaderUserMenuOpen(false);
    });
});

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

function validateDateEffectivity() {
    if (datetime_start.value > datetime_end.value) {
        datetime_start.setCustomValidity("Invalid datetime value");
    } else {
        datetime_start.setCustomValidity('');
    }
}

if (datetime_start) {
    datetime_start.onchange = validateDateEffectivity;
}
if (datetime_end) {
    datetime_end.onchange = validateDateEffectivity;
}

//CC
document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('.cc-dropdown-menu');
    const parent = document.querySelector('.cc-dropdown'); // Replace with an actual parent element

    if (parent) {
        parent.addEventListener('click', (event) => {
            if (event.target.classList.contains('cc-dynamic')) {
                menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
            }
        });
    }

    // Close dropdown when clicking outside, except when clicking on a checkbox
    window.addEventListener('click', (event) => {
        if (!event.target.closest('.cc-dropdown') && !event.target.matches('.cc-dropdown-menu input[type="checkbox"]')) {
            if (menu) {
                menu.style.display = 'none';
            }
        }
    });

    const toggle = document.querySelector('.cc_to');
    const cc_input = document.getElementById('cc_input');

    // Update toggle text based on selections
    if (document.querySelectorAll('input[type="checkbox"]')) {
        if (menu) {
            const checkboxes = menu.querySelectorAll('input[type="checkbox"]');

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    const selected = Array.from(checkboxes)
                        .filter(i => i.checked)
                        .map(i => i.value)
                        .join(', ');

                    toggle.textContent = selected.length ? selected : 'Select Options';
                    cc_input.value = selected.length ? selected : '';
                });
            });
        }
    }
});




//FOR FILE DOWNLOAD 
function downloadFile(fileName) {
    const downloadUrl = '../../protected/handler/encode_module/download_old.php?file=' + encodeURIComponent(fileName);

    fetch(downloadUrl, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/octet-stream',
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('File not found or failed to fetch');
            }

            const disposition = response.headers.get('Content-Disposition');


            return response.blob().then(blob => {
                // Create a link to trigger the download
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = fileName; // Use the file name from the response or a default name
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        })
        .catch(error => {
            console.error('Download error:', error);
        });
}

function downloadFile2(fileName) {
    const viewUrl = '../../protected/handler/encode_module/download.php?file=' + encodeURIComponent(fileName);

    // Open the PDF in a new tab
    window.open(viewUrl, '_blank');
}


function checkFileStatus(file_status) {
    if (document.querySelector(".download_file_container")) {
        if (file_status != "None" && file_status != "") {
            document.querySelector(".download_file_container").classList.remove("hidden_input");
            document.querySelector(".download_file_container").style.display = "flex";
        }
        else {
            document.querySelector(".download_file_container").classList.add("hidden_input");
            document.querySelector(".download_file_container").style.display = "none";
        }
    }
}


//NEW FROM FORWARDING

function hideActionMan(file_status) {
    document.querySelector('.action_man_container').classList.add('hidden_input');

    if (file_status != "None" && file_status != "") {
        document.querySelector(".download_file_container").classList.remove("hidden_input");
        document.querySelector(".download_file_container").style.display = "flex";
    } else {
        document.querySelector(".download_file_container").classList.add("hidden_input");
        document.querySelector(".download_file_container").style.display = "none";
    }
}

function showActionMan(file_status) {
    document.querySelector('.action_man_container').classList.remove('hidden_input');
    document.querySelector('.action_man_container').style.display = "flex";

    if (file_status != "None" && file_status != "") {
        document.querySelector(".download_file_container").classList.remove("hidden_input");
        document.querySelector(".download_file_container").style.display = "flex";
    } else {
        document.querySelector(".download_file_container").classList.add("hidden_input");
        document.querySelector(".download_file_container").style.display = "none";
    }
}

const doc_to_oic_checker = document.getElementById('doc_to_oic_checker');

if (doc_to_oic_checker) {
    doc_to_oic_checker.addEventListener('change', e => {
        if (e.target.checked) {
            document.getElementById('document_to_oic').disabled = false;
        } else {
            document.getElementById('document_to_oic').disabled = true;
        }
    });
}

const cc_checkbox = document.getElementById('cc_checkbox');

if (cc_checkbox) {
    cc_checkbox.addEventListener('change', e => {
        if (e.target.checked) {
            document.querySelector('.cc_to').classList.add("cc-dynamic");
        } else {
            document.querySelector('.cc_to').classList.remove("cc-dynamic");
        }
    });
}

const options_checker = document.getElementById('options_checker');

if (options_checker) {
    options_checker.addEventListener('change', e => {
        if (e.target.checked) {
            document.getElementById('document_to').disabled = false
        } else {
            document.getElementById('document_to').disabled = true
        }
    });
}

// FOR FORWARDING TARGET
const selectElements = document.querySelectorAll(".form-custom-input"); // Get all select elements
const target = "<?php echo $_SESSION['logged_user_designation']; ?>";
const targetArray = target.split(',');

// The string you want to remove
const stringToRemove = "Records Unit"; // Replace with the string you want to remove

// Filter out the string you want to remove
const filteredArray = targetArray.filter(item => item.trim() !== stringToRemove);

selectElements.forEach(selectElement => {
    if (selectElement.options && selectElement.options.length) {
        Array.from(selectElement.options).forEach(option => { // Loop through each option
            if (filteredArray.includes(option.value)) {
                option.classList.add('hidden'); // Add 'hidden' class if value matches
            }
        });
    }
});

$(document).ready(function () {
    $(".prioritized").each(function () {
        if ($(this).text() == "Urgent") {
            $(this).parent().css("background-color", "lightyellow");
            $(this).parent().children('td').css("color", "orangered");
        }
    })
});

const checkbox = document.getElementById('checker');

if (checkbox) {
    checkbox.addEventListener('change', e => {
        if (e.target.checked) {
            document.querySelector('.input-dynamic').style.display = "none";
            document.querySelector('#check-container').style.display = "flex";
        } else {
            document.querySelector('.input-dynamic').style.display = "flex";
            document.querySelector('#check-container').style.display = "none";
        }
    });
}

//2ND FROM INCOMING

function hideFileOptions() {
    document.querySelector('.file_path_container').classList.add('hidden_input')
}

function showFileOptions() {
    document.querySelector('.file_path_container').classList.remove('hidden_input')
}

function confirmReceive() {
    var confirmation = confirm("Are you sure to Receive?")
    if (confirmation) {
        window.location.href = "forwarding.php";
    }
}

//3RD FROM PENDING


const checkbox2 = document.getElementById('checker_priority');

if (checkbox2) {
    checkbox2.addEventListener('change', e => {
        if (e.target.checked) {
            // document.getElementById('priority-container').style.display = "flex";
            document.querySelector('.priority_status').removeAttribute('disabled');
        } else {
            // document.getElementById('priority-container').style.display = "none";
            document.querySelector('.priority_status').setAttribute('disabled', "true");
        }
    });
}
const checkbox3 = document.getElementById('for_action_checkbox');

if (checkbox3) {
    checkbox3.addEventListener('change', e => {
        if (e.target.checked) {
            document.getElementById('complexity-container').style.display = "flex";
            document.getElementById('complexity-label-container').style.display = "flex";
            document.querySelector('.for_action').removeAttribute('disabled');
            document.getElementById('complexity').required = true;
        } else {
            document.getElementById('complexity-container').style.display = "none";
            document.getElementById('complexity-label-container').style.display = "none";
            document.querySelector('.for_action').setAttribute('disabled', "true");
            document.getElementById('complexity').required = false;
        }
    });
}


const oic_checkbox = document.getElementById('oic_checker');

if (oic_checkbox) {
    oic_checkbox.addEventListener('change', e => {
        if (e.target.checked) {
            document.querySelector('.input-dynamic').style.display = "none";
            document.querySelector('#check-container').style.display = "flex";
            document.querySelector('.document_to_oic').disabled = false;
            document.querySelector('#justification').style.display = "flex";
            document.querySelector('#justify_input').required = true;
            document.querySelector('#document_to_oic').required = true;
        } else {
            document.querySelector('.input-dynamic').style.display = "flex";
            document.querySelector('.document_to_oic').disabled = true;
            document.querySelector('#check-container').style.display = "none";
            document.querySelector('#justification').style.display = "none";
            document.querySelector('#justify_input').required = false;
            document.querySelector('#document_to_oic').required = false;
        }
    });
}

