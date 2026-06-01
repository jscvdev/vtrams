function setQuery() {
    // Get form data
    var inputData = $('#filterInput').val();

    // AJAX request
    $.ajax({
        url: 'set_search_query.php',
        method: 'POST',
        data: { inputData: inputData },
        success: function(response) {
            console.log(inputData); // Optional: Log the input data
            // After AJAX request is successful, trigger print
            console.log(response);
            window.print();
        },
        error: function(xhr, status, error) {
            console.error('Error:', error); // Log any errors
        }
    });
}