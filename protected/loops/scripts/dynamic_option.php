<script>
    document.addEventListener('DOMContentLoaded', () => {
        const officeToSelect = document.getElementById('office_to');
        const documentToSelect = document.getElementById('document_to');

        // Store logged user office from PHP into JS variable
        const loggedUserOffice = <?php echo json_encode($loggedUserOffice); ?>;

        if (!documentToSelect) {
            console.error("ERROR: #document_to not found");
            return;
        }

        // Capture original options for logged user's office
        const originalOptions = Array.from(documentToSelect.options).map(opt => ({
            value: opt.value,
            text: opt.textContent,
            disabled: opt.disabled
        }));

        // Sample optionsMap (replace with your real mappings or load dynamically)
        const optionsMap = {
            'CENRO DOLORES': [{
                value: 'Records Unit',
                label: 'Records Unit'
            }],
            'CENRO BORONGAN': [{
                value: 'Records Unit',
                label: 'Records Unit'
            }],
            'DENR-PENRO EASTERN SAMAR': [{
                value: 'Records Unit',
                label: 'Records Unit'
            }]
        };

        function restoreOriginalOptions() {
            documentToSelect.innerHTML = '';
            originalOptions.forEach(opt => {
                const optionEl = document.createElement('option');
                optionEl.value = opt.value;
                optionEl.textContent = opt.text;
                if (opt.disabled) optionEl.disabled = true;
                documentToSelect.appendChild(optionEl);
            });
        }

        function updateDocumentToOptions(selectedOffice) {
            if (selectedOffice === loggedUserOffice) {
                // If selected office is logged user's office, restore original options
                restoreOriginalOptions();
                return;
            }

            const options = optionsMap[selectedOffice] || [];

            if (options.length === 0) {
                // No custom options, restore original anyway or clear
                restoreOriginalOptions();
                return;
            }

            documentToSelect.innerHTML = '';
            options.forEach(({
                value,
                label
            }) => {
                const optionEl = document.createElement('option');
                optionEl.value = value;
                optionEl.textContent = label;
                documentToSelect.appendChild(optionEl);
            });
        }

        // Trigger update when officeToSelect changes
        if (officeToSelect) {
            officeToSelect.addEventListener('change', () => {
                if (!officeToSelect.disabled && officeToSelect.value) {
                    updateDocumentToOptions(officeToSelect.value);
                } else {
                    restoreOriginalOptions();
                }
            });
        }
    });
</script>