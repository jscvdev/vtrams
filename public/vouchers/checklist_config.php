<?php

/**
 * Centralized checklist configuration for voucher forward slip printouts.
 * Voucher types and checklist templates are driven by the checklist folder:
 * - Image files (*.jpg, *.png): filename (without extension) = voucher type name.
 * - JSON files (*.json): optional { "type", "title", "items" } to define or override checklist items.
 * When the checklist folder exists and has files, its types replace the built-in voucher types in the UI.
 */

if (!defined('CHECKLIST_CONFIG_LOADED')) {
    define('CHECKLIST_CONFIG_LOADED', 1);
}

require_once dirname(__DIR__, 2) . '/protected/core/components/helpers/request_cache.inc.php';

const CHECKLIST_CACHE_NS = 'checklist';

/** Path to scan for checklist templates (images + optional JSON). */
if (!defined('CHECKLIST_TEMPLATES_PATH')) {
    // Primary source: project-root /checklist (portable across PCs)
    // Repo layout: vtrams/public/vouchers/checklist_config.php -> vtrams/checklist
    $projectChecklist = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'checklist';

    // Optional override (machine-specific) can be provided via environment variable.
    // Example (Windows): setx DVSYS_CHECKLIST_PATH "D:\shared\checklist"
    $external = getenv('DVSYS_CHECKLIST_PATH') ?: null;

    // Fallback: local templates folder next to this config
    $local = __DIR__ . DIRECTORY_SEPARATOR . 'checklist_templates';
    define(
        'CHECKLIST_TEMPLATES_PATH',
        is_dir($projectChecklist)
            ? $projectChecklist
            : (($external && is_dir($external)) ? $external : (is_dir($local) ? $local : null))
    );
}

/** Default checklist items when no type-specific list is defined. */
function checklist_default_items()
{
    return [
        'Obligation Request and Status',
        'Disbursement Voucher',
        'Supporting documents as per checklist',
        'Others',
    ];
}

/**
 * Normalize a checklist entry: plain string, or { "label", "subitems": [...] } for sub-lines on the slip.
 *
 * @param mixed $item
 * @return array{label: string, subitems: string[]}
 */
function checklist_parse_item($item)
{
    if (is_string($item)) {
        $t = trim($item);

        return ['label' => $t, 'subitems' => []];
    }
    if (is_array($item) && isset($item['label']) && is_string($item['label'])) {
        $subs = [];
        if (!empty($item['subitems']) && is_array($item['subitems'])) {
            foreach ($item['subitems'] as $s) {
                if (is_string($s)) {
                    $st = trim($s);
                    if ($st !== '') {
                        $subs[] = $st;
                    }
                }
            }
        }

        return ['label' => trim($item['label']), 'subitems' => $subs];
    }

    return ['label' => '', 'subitems' => []];
}

/**
 * Type-specific checklist items for folder-derived types (so slip content changes by type).
 * Keys must match image filenames (without extension) from the checklist folder.
 * Add or edit entries to match your templates; JSON in the folder overrides these.
 *
 * @return array<string, array{title: string, items: array<int, string|array<string, mixed>>}>
 */
function checklist_type_specific_items()
{
    return [
        'Boat Rental Cash Advance' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR BOAT RENTAL CASH ADVANCE',
            'items' => [
                'Expense Monitoring',
                'Obligation Request & Status',
                'Disbursement Voucher',
                'Approved Purchase Request',
                'Proposal',
                'Cash Accountability',
                'Certificate of Balances',
            ],
        ],
        'Cash Advances on PS & MOOE by the Cashier' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR CASH ADVANCES ON PS & MOOE BY THE CASHIER/CDO',
            'items' => [
                'Disbursement Voucher',
                'Certification as to the outstanding balance of Cashier/SDO',
                'Statement of Cash Accountability indicating the Bond Number',
                'Maximum Cash Accountability',
                'Estimate of Expenses',
                'Module/So (in case of Training)',
                'Others',
            ],
        ],
        'Catering or Food Packs Services' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR CATERING/FOOD PACKS SERVICES',
            'items' => [
                'Form 10',
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Purchase Request',
                'Request for Quotation',
                'Abstract of Bids',
                'BAC Resolution',
                'Purchase Order',
                'Inspection & Acceptance Report',
                'Documentaries/Pictures',
                'Attendance',
                'Official Receipt (upon payment)',
                'Tax Clearance',
                'Activity Proposal',
                'CAF',
                'Request of Payment',
                'Special Order/ Notice of Meeting',
                'Minutes of the Activity',
                'Bid Evaluation Report',
                'Post-Qualification Evaluation Report',
            ],
        ],
        'Contractual Services or Job Order' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR CONTRACTUAL SERVICES/JOB ORDER',
            'items' => [
                'Form 10',
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Notarized Contract/Job Order',
                'Assumption of Duty',
                'Sworn',
                'Daily Time Record (DTR)',
                'S.O/ Memo',
                'Travel Order',
                'C/A',
                'Accomplishment Report of Travel',
                'Accomplishment Report for the Month',
                'RER',
                'Inspection Report and Acceptance',
                'Others',
            ],
        ],
        'Diesel or Fuel Expnese' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR DIESEL/FUEL EXPENSE',
            'items' => [
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Bill of Statement',
                'Requisition & Issuance Slip',
                'Trip Ticket',
                'Inspection & Acceptance Report',
                'Photocopy TO',
            ],
        ],
        'e-NGP Retention' => [
            'title' => 'e-NGP CHECKLIST ATTACHMENTS FOR RETENTION',
            'items' => [
                'Form 10',
                'Obligation Request & Status (ORS)',
                'Disbursement Vouchers (DV)',
                'Validation Report',
                'Pictures of Validation',
                'Maps, if there\'s any',
                'Copy of Certificate of Completion',
                'Copy of Certificate of Final Acceptance and Turn-over',
                'Request for release of Retention money',
                'Certification from the end-users that the project is Completed and inspected',
                'Narrative Report',
            ],
        ],
        'e-NGP Seedling Production & MP' => [
            'title' => 'e-NGP CHECKLIST ATTACHMENTS FOR SEEDLING PRODUCTION & MP',
            'items' => [
                'Form 10',
                'Obligation Request & Status (ORS)',
                'Disbursement Vouchers (DV)',
                'Request for Inspection & Billing',
                'Statement of Work Accomplishment',
                'Statement of Account (SOA)',
                'Geo-tagged Photos',
                'Certificate of Inspection & Verification',
                'Narrative Report',
                'Certificate of Acceptance',
            ],
        ],
        'Fixed Expenditure' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR FIXED EXPENDITURE (Electricity, Water, Telephone, Internet, etc.)',
            'items' => [
                'Expense Monitoring',
                'Obligation Request & Status',
                'Disbursement Voucher',
                'Monthly Bill/Statement of Account',
                'Official Receipt',
                'Others',
            ],
        ],
        'Furniture & Fixture Expense' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR FURNITURE & FIXTURE EXPENSE',
            'items' => [
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Purchase Request',
                'Request for Quotation',
                'BAC Resolution',
                'Abstract of Bids',
                'Technical Specifications',
                'BAC Resolution (Awarding of Contract)',
                'Purchase Order',
                'Inspection & Acceptance Report',
            ],
        ],
        'Hauling' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR HAULING',
            'items' => [
                'Expense Monitoring',
                'Obligation Request & Status',
                'Disbursement Voucher',
                'Purchase Request',
                'Apprehension Report',
                'Tally Sheet',
                'Custodial Report',
                'Seizure Receipt',
                'Inspection & Acceptance Report',
                'Photo Documentation',
                'Contract of Service',
                'Official Receipt',
            ],
        ],
        'Liquidation of Cash Advances for Local Travel' => [
            'title' => 'FOR THE LIQUIDATION OF CASH ADVANCES FOR LOCAL TRAVEL',
            'items' => [
                'Itinerary number',
                'Liquidation Report (duly signed)',
                'Copy of previously approved pre-payment documents (OBR, DV, IoT, TO & S.O)',
                'Approved Itinerary of Travel duly signed by employees, certified by supervisor & approved by ARD/RD; and indicate purpose of travel.',
                'Paper/Electronic Plane Ticket, Boarding Pass, Terminal Fee, Boat/Bus/Van/Taxi Ticket or RER for above 75.00, Trip Ticket',
                'Certificate of Travel Completed',
                'Certificate of Appearance',
                'Narrative Travel Report noted by Supervisor',
            ],
        ],
        'Motor Vehicle Insurance' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR MOTOR VEHICLE INSURANCE',
            'items' => [
                'Expense Monitoring',
                'Obligation Request & Status',
                'Disbursement Voucher',
                'Premium Computation Slip',
                'Official Receipt',
            ],
        ],
        'Office Supplies or Equipment' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR OFFICE SUPPLIES/EQUIPMENT',
            'items' => [
                'Form 10',
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Purchase Request',
                'BAC Resolution (SVP)',
                'Request for Quotation',
                'Post-Qualification Evaluation Report',
                'Bid Evaluation Report',
                'Abstract of Bids As Read & Calculated',
                'BAC Resolution (LCRQ)',
                'Purchase Order',
                'Inspection & Acceptance Report',
                'Delivery Receipt',
                'Documentation/Pictures',
            ],
        ],
        'Petty Cash Fund Replenishment' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR PETTY CASH FUND REPLENISHMENT',
            'items' => [
                'Form 10',
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Petty Cash Fund Register',
                'Requisition and Issue Voucher',
                'Petty Cash Fund Record',
                'Inspection & Acceptance Report',
                'Petty Cash Voucher',
                'Receipt (A. Certification (No supplies available), B. RIS)',
            ],
        ],
        'PRE-Traveling Expenses' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR PRE-TRAVELING EXPENSES',
            'items' => [
                'Itinerary number',
                'Expense Monitoring',
                'Obligation Request & Status',
                'Disbursement Voucher',
                'Special Order/Invitation (if attending seminars, meeting conferences)',
                'Approved Itinerary of Travel (Appendix A) duly signed by employee) Certified by Supervisor & Approved by RED, an indicate purpose of travel',
            ],
        ],
        'Procurement of Airconditioning' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR PROCUREMENT OF AIRCONDITIONING',
            'items' => [
                'Expense Monitoring',
                'Obligation Request & Status',
                'Disbursement Voucher',
                'Certificate of Availability of Funds',
                'Purchase Request',
                'BAC 1',
                'Request for Quotation',
                'Abstract Of Bids As Read & Calculated',
                'Post-Qualification Evaluation Report',
                'Bid Evaluation Report',
                'BAC 2',
                'Purchase Order',
                'Billing Statement',
                'Delivery Receipt',
                'Inspection & Acceptance Report',
                'Documentation/Pictures',
                'Philgeps',
            ],
        ],
        'Procurement of Motorcycle' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR PROCUREMENT OF MOTORCYCLE',
            'items' => [
                'Form 10',
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Purchase Request',
                'CAF',
                'BAC (SVP)',
                'Bid Evaluation Report',
                'Request for Quotation (a. Quotation Form, b. Technical Specifications, c. Proof of PhilGEPS Registration, d. Omnibus Sworn Statement, e. Business Permit)',
                'Post-Qualification Evaluation Report',
                'Abstract of Bids As Read & Calculated',
                'BAC (LCRQ)',
                'Purchase Order',
                'Inspection & Acceptance Report',
                'Charge Invoice & Delivery Receipt',
                'Official Receipt',
                'Documentation/Pictures',
            ],
        ],
        'Procurement Services of Drinking Water' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR PROCUREMENT SERVICES OF DRINKING WATER',
            'items' => [
                'Form 10',
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Certificate of Availability of Fund (CAF)',
                'Purchase Request',
                'Terms of Reference',
                'BAC 1 (HOPE)',
                'Request for Quotation (a. Quotation Form, b. Technical Specifications, c. Proof of PhilGEPS Registration, d. Omnibus Sworn Statement, e. Business Permit)',
                'Post-Qualification Evaluation Report',
                'Bid Evaluation Report',
                'Abstract of Bids As Read & Calculated',
                'BAC 2 (LCRQ)',
                'Notice of Award',
                'Contract',
                'Notice to Proceed',
                'Billing Statement',
                'Delivery Receipt',
            ],
        ],
        'Reimbursement of Fuel Expense' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR REIMBURSEMENT OF FUEL EXPENSE',
            'items' => [
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Purchase Request',
                'Vehicle Trip Ticket',
                'Official Receipt',
                'Certification of out of Stock',
                'Inspection & Acceptance Report',
                'Summary of Expenses',
            ],
        ],
        'Repair & Maintenance' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR REPAIR & MAINTENANCE',
            'items' => [
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Purchase Request',
                'Request for Quotation',
                'Abstract of Bids As Calculated & Read',
                'BAC Resolution',
                'Purchase Order',
                'Inspection & Acceptance Report',
                'Job Order Complaint Form',
                'Request for Pre-repair Inspection',
                'Statement of Account/ Summary of Billings',
                'Technical Specifications',
                'Bid Evaluation Report',
                'Post-Qualification Evaluation Report',
                'Report of Waste Materials',
            ],
        ],
        'Representation and Transportation Allowance' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR REPRESENTATION & TRANSPORTATION ALLOWANCE (RATA)',
            'items' => [
                'Disbursement Voucher',
                'Daily Time Record',
                'Certification that there is no vehicle issued in the performance of official duties',
                'Certification that the expenses incur is in connection with the official function of the claimant',
            ],
        ],
        'Remittances' => [
            'title' => 'REMITTANCES',
            'items' => [
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Remittance form / schedule',
                'Proof of remittance / acknowledgment',
                'Supporting documents per remittance type',
                'Others',
            ],
        ],
        'Traveling Expenses' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR TRAVELING EXPENSES',
            'items' => [
                'Itinerary number',
                'Expense Monitoring',
                'Obligation Request & Status',
                'Disbursement Voucher',
                'Approved Travel Order',
                'Special Order/Invitation (if attending seminars, meeting conferences)',
                'Approved Itinerary of Travel duly signed by employee)',
                [
                    'label' => 'Proof of Transportation',
                    'subitems' => ['Plane', 'Van', 'Bus', 'Trip Ticket', 'etc.'],
                ],
                'Certificate of travel Completed',
                'Certificate of Appearance',
                'Narrative Travel Report noted by Supervisor',
                'Certificate of Non-payment by PENRO Accountant',
                'Others',
            ],
        ],
        'Various Supplies & Materials' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR VARIOUS SUPPLIES & MATERIALS',
            'items' => [
                'Expense Monitoring',
                'Obligation Request & Status',
                'Disbursement Voucher',
                'Purchase Request',
                'BAC- HOPE',
                'Request for Quotation',
                'Business Permit',
                'Abstract of Bids as Read & Calculated',
                'Purchase Order',
                'Inspection & Acceptance',
                'Official Receipt',
                'Philgeps',
            ],
        ],
    ];
}

/**
 * Scan directory for image templates: type name = filename without extension.
 * Uses type-specific checklist items when defined so slip content changes by type.
 * Supported: .jpg, .jpeg, .png
 *
 * @param string|null $dir
 * @return array<string, array{title: string, items: string[]}>
 */
function checklist_scan_folder_images($dir)
{
    $templates = [];
    if ($dir === null || $dir === '' || !is_dir($dir)) {
        return $templates;
    }
    $typeSpecific = checklist_type_specific_items();
    $extensions = ['jpg', 'jpeg', 'png'];
    foreach ($extensions as $ext) {
        $files = @glob($dir . DIRECTORY_SEPARATOR . '*.' . $ext);
        if (!is_array($files)) {
            continue;
        }
        foreach ($files as $path) {
            $base = basename($path, '.' . $ext);
            $type = trim($base);
            if ($type !== '') {
                if (isset($typeSpecific[$type])) {
                    $templates[$type] = $typeSpecific[$type];
                } else {
                    $templates[$type] = [
                        'title' => strtoupper($type),
                        'items' => checklist_default_items(),
                    ];
                }
            }
        }
    }
    return $templates;
}

/**
 * Scan a directory for *.json checklist templates.
 *
 * @param string|null $dir
 * @return array<string, array{title: string, items: string[]}>
 */
function checklist_scan_folder_json($dir)
{
    $templates = [];
    if ($dir === null || $dir === '' || !is_dir($dir)) {
        return $templates;
    }
    $files = @glob($dir . DIRECTORY_SEPARATOR . '*.json');
    if (!is_array($files)) {
        return $templates;
    }
    foreach ($files as $path) {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            continue;
        }
        $data = @json_decode($raw, true);
        if (!is_array($data)) {
            continue;
        }
        $type  = isset($data['type']) ? trim((string)$data['type']) : null;
        $title = isset($data['title']) ? trim((string)$data['title']) : '';
        $items = isset($data['items']) && is_array($data['items'])
            ? array_values(array_map('trim', array_filter($data['items'], 'is_string')))
            : checklist_default_items();
        if ($type === '') {
            $base = basename($path, '.json');
            $type = str_replace('_', ' ', $base);
        }
        if ($type !== null && $type !== '') {
            $templates[$type] = [
                'title' => $title !== '' ? $title : strtoupper($type),
                'items' => $items,
            ];
        }
    }
    return $templates;
}

/**
 * Get all checklist templates from the folder (images + JSON). JSON overrides image for same type.
 *
 * @return array<string, array{title: string, items: string[]}>
 */
function checklist_get_folder_templates()
{
    $dir = defined('CHECKLIST_TEMPLATES_PATH') ? CHECKLIST_TEMPLATES_PATH : null;
    $cacheKey = 'folder:' . ($dir === null || $dir === '' ? '' : (string) $dir);

    return RequestCache::remember(CHECKLIST_CACHE_NS, $cacheKey, static function () use ($dir): array {
        if ($dir === null || $dir === '') {
            return [];
        }
        $fromImages = checklist_scan_folder_images($dir);
        $fromJson = checklist_scan_folder_json($dir);

        return $fromJson + $fromImages;
    });
}

/**
 * Built-in checklist definitions (used only when checklist folder is missing or empty).
 */
function checklist_get_builtin_templates()
{
    return [
        'TEV' => [
            'title' => 'TRAVEL EXPENSES / PER DIEM',
            'items' => [
                'Itinerary number',
                'Travel Order',
                'Accomplishment Report of Travel',
                'Certificate of Appearance / Travel',
                'DTR (Daily Time Record)',
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Others',
            ],
        ],
        'eNGP' => [
            'title' => 'eNGP / REFORESTATION',
            'items' => [
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Contract / Job Order',
                'Inspection Report and Acceptance',
                'Certificate of Planting',
                'RER / Report',
                'Others',
            ],
        ],
        'Procurement of Supplies' => [
            'title' => 'PROCUREMENT OF SUPPLIES',
            'items' => [
                'Purchase Request',
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Inspection Report and Acceptance',
                'Delivery Receipt / Invoice',
                'Others',
            ],
        ],
        'Salaries' => [
            'title' => 'SALARIES AND WAGES',
            'items' => [
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Payroll / LDDAP',
                'DTR (Daily Time Record)',
                'Others',
            ],
        ],
        'Remittances' => [
            'title' => 'REMITTANCES',
            'items' => [
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Remittance form / schedule',
                'Proof of remittance / acknowledgment',
                'Supporting documents per remittance type',
                'Others',
            ],
        ],
        'Goods & Services' => [
            'title' => 'MANDATORY SUPPORTING DOCUMENTS FOR CONTRACTUAL SERVICES/JOB ORDER',
            'items' => [
                'Form 10',
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Notarized Contract/ Job Order',
                'Assumption of Duty',
                'Sworn',
                'Daily Time Record (DTR)',
                'S.O/ Memo',
                'Travel Order',
                'C/A',
                'Accomplishment Report of Travel',
                'Accomplishment Report for the Month',
                'RER',
                'Inspection Report and Acceptance',
                'Others',
            ],
        ],
        'Utilities' => [
            'title' => 'UTILITIES',
            'items' => [
                'Obligation Request and Status',
                'Disbursement Voucher',
                'Statement of Account / Bill',
                'Others',
            ],
        ],
    ];
}

/**
 * Built-in types that remain available even when checklist folder templates are active.
 *
 * @return string[]
 */
function checklist_always_available_types()
{
    return ['Remittances'];
}

/**
 * Returns the active template set: folder templates if folder has content, else built-in.
 * Types listed in checklist_always_available_types() are merged in when missing from the folder.
 *
 * @return array<string, array{title: string, items: string[]}>
 */
function checklist_get_active_templates()
{
    return RequestCache::remember(CHECKLIST_CACHE_NS, 'active_templates', static function (): array {
        $folder = checklist_get_folder_templates();
        $builtin = checklist_get_builtin_templates();
        if (!empty($folder)) {
            foreach (checklist_always_available_types() as $type) {
                if (!isset($folder[$type]) && isset($builtin[$type])) {
                    $folder[$type] = $builtin[$type];
                }
            }

            return $folder;
        }

        return $builtin;
    });
}

/**
 * Returns checklist template for the given voucher type.
 *
 * @param string $voucherType
 * @return array{title: string, items: string[]}
 */
function checklist_for_type($voucherType)
{
    $templates = checklist_get_active_templates();
    $type = trim((string)$voucherType);
    if (isset($templates[$type]) && !empty($templates[$type]['items'])) {
        return [
            'title' => $templates[$type]['title'],
            'items' => $templates[$type]['items'],
        ];
    }
    return [
        'title' => 'SUPPORTING DOCUMENTS',
        'items' => checklist_default_items(),
    ];
}

/**
 * Returns voucher types with display labels for dropdowns.
 * When checklist folder has files, only folder types are returned (replacing built-in).
 *
 * @return array<string, string> [ value => label ]
 */
function checklist_types_with_labels()
{
    return RequestCache::remember(CHECKLIST_CACHE_NS, 'types_with_labels', static function (): array {
        $templates = checklist_get_active_templates();
        // Display-label fixes for legacy/misspelled stored type values.
        // IMPORTANT: keys remain unchanged to preserve existing saved voucher_type values.
        $labelFixes = [
            'Diesel or Fuel Expnese' => 'Diesel Fuel Expense',
            'Contractual Services or Job Order' => 'Contractual Services or Job Order Salary',
        ];
        $out = [];
        foreach (array_keys($templates) as $type) {
            $out[$type] = $labelFixes[$type] ?? $type;
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    });
}

/**
 * Returns all known voucher type values (for dropdown sync).
 *
 * @return string[]
 */
function checklist_known_types()
{
    return array_keys(checklist_types_with_labels());
}

/**
 * Human-readable label for a stored voucher_type value (falls back to raw value).
 */
function voucher_type_display_label(string $stored): string
{
    $t = trim($stored);
    if ($t === '') {
        return '';
    }
    $map = checklist_types_with_labels();

    return $map[$t] ?? $t;
}

/**
 * HTML for a voucher type pill (same visual language as .remarks-badge in pstyle.css).
 */
function voucher_type_badge_html(string $stored): string
{
    $label = voucher_type_display_label($stored);
    if ($label === '') {
        return '<span class="voucher-type-badge voucher-type-badge--empty">&mdash;</span>';
    }

    return '<span class="voucher-type-badge">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}
