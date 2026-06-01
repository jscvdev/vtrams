Checklist templates
===================
The system scans: C:\Users\jsray\Desktop\checklist first, then this folder (checklist_templates).

VOUCHER TYPES (from folder):
- Image files (*.jpg, *.png): filename without extension = voucher type name.
  Example: "Traveling Expenses.jpg" adds type "Traveling Expenses" to the dropdown.
- These types replace the built-in voucher types in voucher.php when the folder has any images.

CHECKLIST ITEMS (optional JSON):
- Place .json files to define or override checklist items for a type.
- Each JSON: { "type": "Type Name", "title": "HEADING ON SLIP", "items": ["Doc 1", "Doc 2"] }
- "type" must match an existing type (e.g. from an image filename).
- "title": heading on the printed slip (optional).
- "items": array of document labels for the checklist (optional; default list used if omitted).
