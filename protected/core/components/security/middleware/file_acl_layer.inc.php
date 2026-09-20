<?php

/**
 * File-name ACL and designation rules from AccessControl.
 */
function vtrams_middleware_layer_file_acl(): void
{
    require_once __DIR__ . '/../access_control.inc.php';

    $fileName = basename((string) ($_SERVER['PHP_SELF'] ?? ''));

    if ($fileName === '') {
        return;
    }

    if (!AccessControl::checkFileAccess($fileName)) {
        require_once __DIR__ . '/../../redirects/redirect_config.inc.php';
        redirect_to_internal('route_404');
    }
}
