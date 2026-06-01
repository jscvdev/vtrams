# COA Data Format Documentation

## Overview
The `coa_data` file has been formatted into JSON files that can be easily used for dropdown/select options in web applications.

## Generated Files

### 1. `coa_data.json` (Full Format)
Complete data structure with categories and metadata:
- **Structure**: Object with `options` array, `total` count, and `categories` list
- **Each option contains**:
  - `id`: Unique identifier
  - `value`: The requirement text (used as option value)
  - `label`: Display text (used as option label)
  - `category`: Full category path (e.g., "Cash Advances > Granting of Cash Advances")
  - `section`: Main section (e.g., "Cash Advances")
  - `subsection`: Subsection if available
  - `subsubsection`: Sub-subsection if available

**Use this when you need:**
- Grouped dropdowns with optgroups
- Category filtering
- Hierarchical organization
- Metadata about each requirement

### 2. `coa_data_simple.json` (Simple Format)
Simplified array format for basic dropdowns:
- **Structure**: Array of objects with `value` and `label` properties
- **Each option contains**:
  - `value`: The requirement text
  - `label`: Display text (same as value)

**Use this when you need:**
- Simple dropdown population
- Quick implementation
- Minimal data structure

## Usage Examples

### JavaScript/HTML Example

```html
<select id="coaRequirements"></select>

<script>
// Load simple format
fetch('coa_data_simple.json')
    .then(response => response.json())
    .then(options => {
        const select = document.getElementById('coaRequirements');
        select.innerHTML = '<option value="">-- Select --</option>';
        options.forEach(option => {
            const opt = document.createElement('option');
            opt.value = option.value;
            opt.textContent = option.label;
            select.appendChild(opt);
        });
    });
</script>
```

### PHP Example

```php
<?php
$coaData = json_decode(file_get_contents('coa_data_simple.json'), true);
?>
<select name="requirement">
    <option value="">-- Select Requirement --</option>
    <?php foreach ($coaData as $option): ?>
        <option value="<?php echo htmlspecialchars($option['value']); ?>">
            <?php echo htmlspecialchars($option['label']); ?>
        </option>
    <?php endforeach; ?>
</select>
```

### jQuery Example

```javascript
$.getJSON('coa_data_simple.json', function(options) {
    const $select = $('#coaRequirements');
    $select.append('<option value="">-- Select --</option>');
    $.each(options, function(index, option) {
        $select.append($('<option>', {
            value: option.value,
            text: option.label
        }));
    });
});
```

## File Statistics
- **Total Options**: 404 requirements
- **Main Categories**: 14 sections
  - Cash Advances
  - Fund Transfers to NGOs/POs/CSOs
  - Fund Transfers to Implementing Agency
  - Salary
  - Allowances, Honoraria and Other Forms of Compensations
  - Other Expenditures
  - Extraordinary and Miscellaneous Expenses
  - Prisoner's Subsistence Allowance
  - Procurement of Goods, Consulting Services and Infrastructure Projects
  - Cultural and Athletic Activities
  - Human Resource Development and Training Program
  - Financial Expenses
  - Legal Retainer's Fee
  - Road Right-of-Way (ROW)/Real Property

## Notes
- All text is preserved as-is from the original document
- Multi-line requirements have been combined into single entries
- Special characters and formatting are maintained
- The data is UTF-8 encoded

## Regenerating the JSON Files
If you need to regenerate the JSON files from the original `coa_data` file, run:

```bash
python format_coa_data.py
```

This will create both `coa_data.json` and `coa_data_simple.json` files.











