// Function to decode HTML entities using DOMParser
function decodeHtmlEntities(str) {
    var doc = new DOMParser().parseFromString(str, 'text/html');
    return doc.documentElement.textContent || str;  // This handles all HTML entities
}

// Function to get current time formatted for Asia/Singapore timezone
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

// Run the function every 2 seconds to update the time
setInterval(() => {
    document.getElementById('print_time').textContent = getCurrentTime();
}, 2000);


// Function to extract the value by key from items list
function getValueByKey(items, key) {
    const item = items.find(item => item.key === key);
    return item ? decodeHtmlEntities(item.value) : null;
}

// Function to handle item passing and storing in sessionStorage
function passItem(itemList, documentId) {
    const items = itemList[documentId];

    if (items) {
        const document_id = getValueByKey(items, 'document_id');
        const document_type = getValueByKey(items, 'document_type');
        const document_sender = getValueByKey(items, 'document_sender');
        const document_subject = getValueByKey(items, 'document_title'); // This contains the title to be decoded
        const document_received = getValueByKey(items, 'datetime_encoded');
        const for_action = getValueByKey(items, 'for_action');
        const remarks = getValueByKey(items, 'remarks');

        // Decode document_title and store in sessionStorage
        sessionStorage.setItem('document_id', document_id);
        sessionStorage.setItem('document_type', document_type);
        sessionStorage.setItem('document_sender', document_sender);
        sessionStorage.setItem('document_title', decodeHtmlEntities(document_subject)); // Decoding here
        sessionStorage.setItem('datetime_encoded', document_received);
        sessionStorage.setItem('for_action', for_action);
        sessionStorage.setItem('remarks', remarks);

        console.log('Data updated:', sessionStorage.getItem('document_id'));
        console.log('Data updated:', sessionStorage.getItem('document_type'));
    } else {
        sessionStorage.clear();
        console.log('No items found for documentId, sessionStorage cleared.');
    }
}

// Function to generate QR Code using a code value
function generateQRCode(code, callback) {
    var text = code.trim(); // Get the text input value and remove any surrounding spaces

    console.log("Input data:", text); // Log the input data for debugging

    if (!text) {
        alert("Please enter a valid URL or text."); // Show an alert if the input is empty
        return;
    }

    // Show a loading indicator while the QR code is being generated
    const qrDiv = document.getElementById('qrcode');
    qrDiv.innerHTML = '<div>Loading QR Code...</div>'; // A simple loading message

    // Generate the QR code as Data URL (base64-encoded image)
    QRCode.toDataURL(text, {
        errorCorrectionLevel: 'H', // Set error correction level to 'H' for higher reliability
        width: 200, // QR code width (pixels)
        color: {
            dark: "#000000", // QR code color
            light: "#ffffff" // Background color
        }
    }, function (err, url) {
        if (err) {
            console.error("Error generating QR code:", err);
            alert("Error generating QR code.");
            return;
        }

        // Create an image element and set the source to the generated Data URL
        var img = document.createElement("img");
        img.src = url;
        img.alt = "Generated QR Code";

        // Clear previous QR code and append the new one
        qrDiv.innerHTML = ''; // Clear the loading message
        qrDiv.appendChild(img); // Append the new QR code image

        // Execute the callback after QR code is set
        if (callback) callback();
    });
}

// Function to set document data in the HTML elements
function setDocumentData() {
    document.getElementById('document_id').textContent = sessionStorage.getItem('document_id');
    document.getElementById('document_type').textContent = sessionStorage.getItem('document_type');
    document.getElementById('document_sender').textContent = sessionStorage.getItem('document_sender');
    
    // Decode the document title when setting it in the HTML
    const decodedTitle = decodeHtmlEntities(sessionStorage.getItem('document_title'));
    document.getElementById('document_title').textContent = decodedTitle;
    
    document.getElementById('datetime_encoded').textContent = sessionStorage.getItem('datetime_encoded');
    document.getElementById('datetime_encoded2').textContent = sessionStorage.getItem('datetime_encoded');
    document.getElementById('print_time').textContent = getCurrentTime();
    document.getElementById('remarks').textContent = sessionStorage.getItem('remarks');
    
    const x_id = sessionStorage.getItem('document_id');
    console.log("Document_id to set:", x_id);

    // First set the data, then generate the QR code
    generateQRCode(sessionStorage.getItem('document_id'), () => {
        // Now everything is set, and QR code is generated
        console.log("QR code set successfully.");
    });

    if (sessionStorage.getItem('for_action') === "true") {
        document.getElementById("complex-container").style.display = "flex";
    } else {
        document.getElementById("complex-container").style.display = "none";
    }

    // Log the sessionStorage data to ensure it's correct
    console.log('sessionStorage data:', sessionStorage.getItem('document_id'));
}

// Event listener for print setup
window.addEventListener('beforeprint', () => {
    console.log('beforeprint event triggered.');
    setDocumentData();
});
