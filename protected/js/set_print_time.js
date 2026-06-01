function getCurrentTime() {
    const options = {
        timeZone: 'Asia/Singapore',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };

    const date = new Intl.DateTimeFormat('en-US', options).format(new Date());
    const [month, day, year, hour, minute, second] = date.split(/[/,\s:]+/);
    return `${month}-${day}-${year} ${hour}:${minute}:${second}`;
}

// Run the function every 2 seconds
setInterval(getCurrentTime, 2000);

// Run the function every 2 seconds
setInterval(getCurrentTime, 2000);

function setDocumentData() {
    if (document.getElementById('print_time'))
    {
        document.getElementById('print_time').textContent = getCurrentTime();
    }
}

window.addEventListener('beforeprint', () => {
    setDocumentData();
});