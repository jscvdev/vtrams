<?php
require_once __DIR__ . '/../redirects/redirect_config.inc.php';

/**
 * Access Control Helper
 * Handles role-based and ACL-based access control for the DVSYS system
 * 
 * This class provides comprehensive access control functionality including:
 * - Role-based access control (RBAC) using designations
 * - ACL level-based access control
 * - Designation-based access control
 * - File/page-based access control
 * 
 * Available Roles/Designations:
 * - System Admin
 * - Budget Unit
 * - Planning Section
 * - Accounting Unit
 * - Cashiers Unit
 * - Processor
 * - Liaison Officer
 */

class AccessControl
{
    /**
     * Available roles/designations in the system
     */
    private static $availableRoles = [
        'System Admin',
        'Budget Unit',
        'Planning Section',
        'Accounting Unit',
        'Cashiers Unit',
        'Processor',
        'Liaison Officer'
    ];

    /**
     * Role hierarchy for access control (higher number = more privileges)
     * Used for role-based comparisons
     */
    private static $roleHierarchy = [
        'System Admin' => 7,
        'Budget Unit' => 6,
        'Planning Section' => 5,
        'Accounting Unit' => 4,
        'Cashiers Unit' => 3,
        'Processor' => 2,
        'Liaison Officer' => 1
    ];

    /**
     * File-based access control rules
     * Maps file names to required ACL levels
     */
    private static $fileAccessRules = [
        'devtool.php' => 8,
        'edit_form.php' => 8,
        'designations.php' => 8,
        'file_tracking.php' => 10,
        'pending.php' => 4
    ];

    /**
     * File-based designation access rules
     * Maps file names to required designations
     */
    private static $fileDesignationRules = [
        'document_tracking.php' => ['Records Unit'],
        'voucher_ada.php' => ['Cashiers Unit', 'System Admin']
    ];

    /**
     * Get user roles/designations from session
     * Returns array of designations
     */
    public static function getUserRoles()
    {
        return self::getUserDesignations();
    }

    /**
     * Get primary user role (first designation)
     */
    public static function getUserRole()
    {
        $designations = self::getUserDesignations();
        return !empty($designations) ? $designations[0] : null;
    }

    /**
     * Check if user has required role level (based on hierarchy)
     * Checks if user's highest role level >= required level
     */
    public static function hasRoleLevel($requiredLevel)
    {
        return self::hasMinimumRoleLevel($requiredLevel);
    }

    /**
     * Check if user has specific role/designation
     */
    public static function hasRole($requiredRole)
    {
        return self::hasDesignation($requiredRole);
    }

    /**
     * Check if user has any of the specified roles/designations
     */
    public static function hasAnyRole($allowedRoles)
    {
        return self::hasAnyDesignation($allowedRoles);
    }

    /**
     * Get ACL value from session
     */
    public static function getACL()
    {
        return $_SESSION['acl'] ?? 1;
    }

    /**
     * Check if user has minimum ACL level
     */
    public static function hasMinimumACL($requiredACL)
    {
        $userACL = self::getACL();
        return $userACL >= $requiredACL;
    }

    /**
     * Check if user has exact ACL level
     */
    public static function hasACL($requiredACL)
    {
        $userACL = self::getACL();
        return $userACL === $requiredACL;
    }

    /**
     * Check if user has any of the specified ACL levels
     */
    public static function hasAnyACL($allowedACLs)
    {
        $userACL = self::getACL();
        return in_array($userACL, $allowedACLs);
    }

    /**
     * Get user designations from session
     */
    public static function getUserDesignations()
    {
        if (!isset($_SESSION['logged_user_designation'])) {
            return [];
        }
        return explode(",", $_SESSION['logged_user_designation']);
    }

    /**
     * Check if user has specific designation
     */
    public static function hasDesignation($requiredDesignation)
    {
        $designations = self::getUserDesignations();
        return in_array($requiredDesignation, $designations);
    }

    /**
     * Check if user has any of the specified designations
     */
    public static function hasAnyDesignation($allowedDesignations)
    {
        $userDesignations = self::getUserDesignations();
        return !empty(array_intersect($userDesignations, $allowedDesignations));
    }

    /**
     * Check file-based access control
     */
    public static function checkFileAccess($fileName)
    {
        // Check ACL-based rules
        if (isset(self::$fileAccessRules[$fileName])) {
            $requiredACL = self::$fileAccessRules[$fileName];
            if (!self::hasMinimumACL($requiredACL)) {
                return false;
            }
        }

        // Check designation-based rules
        if (isset(self::$fileDesignationRules[$fileName])) {
            $requiredDesignations = self::$fileDesignationRules[$fileName];
            if (!self::hasAnyDesignation($requiredDesignations)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Redirect user if file access denied
     */
    public static function redirectIfFileDenied($fileName, $redirectUrl = null)
    {
        if (!self::checkFileAccess($fileName)) {
            if ($redirectUrl === null) {
                $redirectUrl = get_redirect_internal_url_by_key('route_404');
            }
            header("Location: " . $redirectUrl);
            exit;
        }
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn()
    {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === "true";
    }

    /**
     * Require login, redirect if not logged in
     */
    public static function requireLogin($redirectUrl = null)
    {
        if (!self::isLoggedIn()) {
            if ($redirectUrl === null) {
                $redirectUrl = get_redirect_internal_url_by_key('route_404');
            }
            header("Location: " . $redirectUrl);
            exit;
        }
    }

    /**
     * Check access for specific modules based on roles/designations
     */
    public static function checkModuleAccess($module)
    {
        switch ($module) {
            case 'dashboard':
                return true; // All roles

            case 'user_management':
                return self::hasRole('System Admin'); // System Admin only

            case 'voucher_incoming':
                // ACL >= 3 OR specific designations
                return self::hasMinimumACL(3) ||
                    self::hasAnyRole([
                        'Planning Section',
                        'Budget Unit',
                        'Accounting Unit',
                        'Cashiers Unit',
                        'Processor'
                    ]);

            case 'voucher_forwarding':
                // ACL >= 3 OR specific designations
                return self::hasMinimumACL(3) ||
                    self::hasAnyRole([
                        'Planning Section',
                        'Budget Unit',
                        'Accounting Unit',
                        'Cashiers Unit',
                        'Processor'
                    ]);

            case 'voucher_ada':
                return self::hasAnyRole(['Cashiers Unit', 'System Admin']);

            case 'document_tracking':
                return self::hasRole('Records Unit');

            case 'devtool':
            case 'edit_form':
            case 'designations':
                return self::hasMinimumACL(8); // ACL >= 8

            case 'file_tracking':
                return self::hasMinimumACL(10); // ACL >= 10

            case 'pending':
                return self::hasMinimumACL(4); // ACL >= 4

            default:
                return false;
        }
    }

    /**
     * Redirect user if access denied
     */
    public static function redirectIfDenied($module, $redirectUrl = null)
    {
        if (!self::checkModuleAccess($module)) {
            $_SESSION['access_denied_message'] = "You don't have permission to access this module.";
            if ($redirectUrl === null) {
                $redirectUrl = get_redirect_url('documents_index');
            }
            header("Location: " . $redirectUrl);
            exit;
        }
    }

    /**
     * Redirect user if ACL level insufficient
     */
    public static function redirectIfACLDenied($requiredACL, $redirectUrl = null)
    {
        if (!self::hasMinimumACL($requiredACL)) {
            $_SESSION['access_denied_message'] = "You don't have sufficient access level to access this resource.";
            if ($redirectUrl === null) {
                $redirectUrl = get_redirect_internal_url_by_key('route_404');
            }
            header("Location: " . $redirectUrl);
            exit;
        }
    }

    /**
     * Redirect user if designation not allowed
     */
    public static function redirectIfDesignationDenied($requiredDesignations, $redirectUrl = null)
    {
        if (!self::hasAnyDesignation($requiredDesignations)) {
            $_SESSION['access_denied_message'] = "You don't have the required designation to access this resource.";
            if ($redirectUrl === null) {
                $redirectUrl = get_redirect_internal_url_by_key('route_404');
            }
            header("Location: " . $redirectUrl);
            exit;
        }
    }

    /**
     * Get access denied message
     */
    public static function getAccessDeniedMessage()
    {
        $message = $_SESSION['access_denied_message'] ?? '';
        unset($_SESSION['access_denied_message']);
        return $message;
    }

    /**
     * Check if user can perform action based on role hierarchy
     * Returns true if user's highest role level >= required level
     */
    public static function hasMinimumRoleLevel($requiredLevel)
    {
        $userRoles = self::getUserRoles();
        if (empty($userRoles)) {
            return false;
        }

        // Get the highest role level from user's designations
        $maxLevel = 0;
        foreach ($userRoles as $role) {
            $level = self::$roleHierarchy[$role] ?? 0;
            if ($level > $maxLevel) {
                $maxLevel = $level;
            }
        }

        return $maxLevel >= $requiredLevel;
    }

    /**
     * Get accessible modules for current user based on roles and ACL
     */
    public static function getAccessibleModules()
    {
        $modules = [];

        // All authenticated users
        $modules['dashboard'] = 'Dashboard';

        // Voucher modules based on ACL or designations
        if (self::checkModuleAccess('voucher_incoming')) {
            $modules['voucher_incoming'] = 'Voucher Incoming';
            $modules['voucher_forwarding'] = 'Voucher Forwarding';
        }

        // Cashiers Unit and System Admin
        if (self::checkModuleAccess('voucher_ada')) {
            $modules['voucher_ada'] = 'Voucher ADA';
        }

        // Records Unit
        if (self::checkModuleAccess('document_tracking')) {
            $modules['document_tracking'] = 'Document Tracking';
        }

        // High ACL users
        if (self::hasMinimumACL(8)) {
            $modules['devtool'] = 'Developer Tools';
            $modules['edit_form'] = 'Edit Form';
            $modules['designations'] = 'Designations';
        }

        if (self::hasMinimumACL(10)) {
            $modules['file_tracking'] = 'File Tracking';
        }

        if (self::hasMinimumACL(4)) {
            $modules['pending'] = 'Pending';
        }

        // System Admin only
        if (self::hasRole('System Admin')) {
            $modules['user_management'] = 'User Management';
            $modules['settings'] = 'Settings';
        }

        return $modules;
    }

    /**
     * Log access attempt
     */
    public static function logAccessAttempt($module, $granted)
    {
        if (class_exists('AuditHelper')) {
            $action = $granted ? 'access_granted' : 'access_denied';
            $description = $granted ? "Accessed {$module} module" : "Access denied to {$module} module";
            AuditHelper::logActivity($action, $description, ['module' => $module, 'granted' => $granted]);
        }
    }

    /**
     * Check if user can access a feature based on ACL (for view-level checks)
     * This is a convenience method for use in templates/views
     */
    public static function canAccess($requiredACL)
    {
        return self::hasMinimumACL($requiredACL);
    }

    /**
     * Check if user has ACL in a range (for view-level checks)
     */
    public static function hasACLInRange($minACL, $maxACL = null)
    {
        $userACL = self::getACL();
        if ($maxACL === null) {
            return $userACL >= $minACL;
        }
        return $userACL >= $minACL && $userACL <= $maxACL;
    }

    /**
     * Check if user has exact ACL or higher (for view-level checks)
     */
    public static function hasACLOrHigher($requiredACL)
    {
        return self::hasMinimumACL($requiredACL);
    }

    /**
     * Debug function to check user access
     */
    public static function debugUserAccess()
    {
        $acl = self::getACL();
        $userRoles = self::getUserRoles();
        $primaryRole = self::getUserRole();
        $accessibleModules = self::getAccessibleModules();

        return [
            'acl' => $acl,
            'user_roles' => $userRoles,
            'primary_role' => $primaryRole,
            'accessible_modules' => array_keys($accessibleModules),
            'session_data' => [
                'user_id' => $_SESSION['user_id'] ?? 'not_set',
                'username' => $_SESSION['username'] ?? 'not_set',
                'acl' => $acl,
                'designations' => $_SESSION['logged_user_designation'] ?? 'not_set'
            ]
        ];
    }
}
