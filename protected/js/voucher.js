// Get the original number from the table cell
const originalCell = document.querySelectorAll('.amount');

originalCell.forEach(element => {
    const number = parseFloat(element.innerText);
    // Format the number
    const formattedNumber = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(number);
    // Insert the formatted number into the table
    element.innerText = formattedNumber;
});