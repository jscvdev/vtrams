<?php
/**
 * Page Title Helper
 * Provides consistent page title generation across the system
 */

class PageTitleHelper {
    private $pdo;
    private $systemSettings;
    
    public function __construct($connection) {
        $this->pdo = $connection;
        $this->loadSystemSettings();
    }
    
    /**
     * Load system settings from database
     */
    private function loadSystemSettings() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM system_settings WHERE id = 1");
            $stmt->execute();
            $this->systemSettings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$this->systemSettings) {
                // Fallback to default settings
                $this->systemSettings = [
                    'system_name' => 'PENRO Disbursement Voucher System',
                    'page_title' => 'Disbursement Voucher System',
                    'company_name' => 'Provincial Environment and Natural Resources Office',
                    'browser_title' => 'PENRO-DVS',
                    'header_text' => 'PENRO Disbursement Voucher System v1.0',
                ];
            }
        } catch (PDOException $e) {
            error_log("Error loading system settings: " . $e->getMessage());
            $this->systemSettings = [
                'system_name' => 'PENRO Disbursement Voucher System',
                'page_title' => 'Disbursement Voucher System',
                'company_name' => 'Provincial Environment and Natural Resources Office',
                'browser_title' => 'PENRO-DVS',
                'header_text' => 'PENRO Disbursement Voucher System v1.0',
            ];
        }
    }
    
    /**
     * Generate page title with consistent format
     * @param string $pageName The specific page name
     * @param bool $includeSystemName Whether to include system name
     * @return string Formatted page title
     */
    public function generatePageTitle($pageName = '', $includeSystemName = true) {
        $baseTitle = $this->systemSettings['page_title'] ?? 'Disbursement Voucher System';
        $systemName = $this->systemSettings['system_name'] ?? 'Disbursement Voucher System';
        
        if (empty($pageName)) {
            return $baseTitle;
        }
        
        if ($includeSystemName) {
            return $pageName . ' - ' . $baseTitle;
        } else {
            return $pageName;
        }
    }
    
    /**
     * Get the base page title from settings
     * @return string Base page title
     */
    public function getBasePageTitle() {
        return $this->systemSettings['page_title'] ?? 'Disbursement Voucher System';
    }
    
    /**
     * Get system name from settings
     * @return string System name
     */
    public function getSystemName() {
        return $this->systemSettings['system_name'] ?? 'PENRO Disbursement Voucher System';
    }
    
    /**
     * Get company name from settings
     * @return string Company name
     */
    public function getCompanyName() {
        return $this->systemSettings['company_name'] ?? 'Provincial Environment and Natural Resources Office';
    }

    /**
     * Get browser title from settings (used for <title>)
     * @return string Browser title
     */
    public function getBrowserTitle() {
        return $this->systemSettings['browser_title'] ?? ($this->systemSettings['page_title'] ?? 'Disbursement Voucher System');
    }

    /**
     * Get header text from settings (used in top header)
     * @return string Header text
     */
    public function getHeaderText() {
        return $this->systemSettings['header_text'] ?? ($this->systemSettings['system_name'] ?? 'PENRO Disbursement Voucher System');
    }
    
    /**
     * Update page title in database
     * @param string $newTitle New page title
     * @return bool Success status
     */
    public function updatePageTitle($newTitle) {
        try {
            $stmt = $this->pdo->prepare("UPDATE system_settings SET page_title = ? WHERE id = 1");
            $result = $stmt->execute([$newTitle]);
            
            if ($result) {
                // Refresh the cached settings
                $this->loadSystemSettings();
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error updating page title: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get predefined page titles for common pages
     * @return array Array of page titles
     */
    public function getPredefinedPageTitles() {
        return [
            'dashboard' => 'Dashboard',
            'payroll_management' => 'Payroll Management',
            'employee_management' => 'Employee Management',
            'payslips' => 'Payslips',
            'payroll_periods' => 'Payroll Periods',
            'reporting' => 'Reports',
            'auditing' => 'Auditing',
            'allowances_deductions' => 'Allowances & Deductions',
            'constants_management' => 'Constants Management',
            'settings' => 'System Settings',
            'user_management' => 'User Management'
        ];
    }
}
?>
