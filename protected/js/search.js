document.addEventListener('DOMContentLoaded', function() {
    const dataTable = document.getElementById('dataTable');
    const pagination = document.getElementById('pagination');
    const searchInput = document.getElementById('searchInput');

    let currentPage = 1;
    const rowsPerPage = 10; // Adjust number of rows per page as needed

    function fetchData() {
        fetch('getData.php')
            .then(response => response.json())
            .then(data => {
                // Once data is fetched, proceed with pagination and display
                setupPagination(data);
                displayData(paginateData(data, currentPage));
            })
            .catch(error => {
                console.error('Error fetching data:', error);
            });
    }

    // Function to display data in the table
    function displayData(items) {
        const tableBody = document.getElementById('target_body'); // Update to match your table body ID
        tableBody.innerHTML = ''; // Clear previous table rows

        items.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.document_id}</td>
                <td>${item.document_sender}</td>
                <td>${item.document_date}</td>
                <td>${item.fwd_by}</td>
                <td>${item.fwd_to}</td>
                <td>${item.document_status}</td>
                <td>${item.datetime_forwarded}</td>
                <td>${item.received_by}</td>
                <td>${item.datetime_received}</td>
                <td>${item.reply_status}</td>
                <td>${item.reply_id}</td>
                <td>${item.reply_by}</td>
                <td>${item.datetime_reply}</td>
                <td>${item.turnaround_time}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    function paginateData(items, page) {
        const startIndex = (page - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;
        const paginatedItems = items.slice(startIndex, endIndex);
        return paginatedItems;
    }

    function setupPagination(items) {
        pagination.innerHTML = ''; // Clear previous pagination links

        const pageCount = Math.ceil(items.length / rowsPerPage);

        for (let i = 1; i <= pageCount; i++) {
            const link = document.createElement('a');
            link.href = '#';
            link.textContent = i;

            if (i === currentPage) {
                link.classList.add('pagination-link', 'active');
            } else {
                link.classList.add('pagination-link');
            }

            link.addEventListener('click', function(event) {
                event.preventDefault();
                currentPage = i;
                const paginatedItems = paginateData(items, currentPage);
                displayData(paginatedItems);
                setupPagination(items);
            });

            pagination.appendChild(link);
        }
    }

    function filterData(searchTerm, items) {
        const filteredData = items.filter(item => {
            return item.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.description.toLowerCase().includes(searchTerm.toLowerCase());
        });
        return filteredData;
    }

    searchInput.addEventListener('input', function() {
        const searchTerm = searchInput.value.trim();
        fetchData(); // Fetch fresh data on each search input change
        currentPage = 1; // Reset to first page after search
    });

    // Initial page load
    fetchData();
});