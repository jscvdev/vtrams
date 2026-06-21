<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/components/helpers/handler_session_err_helper.inc.php';

function check_routing_delete_errors(): void
{
    handler_emit_session_errors('error_routing_delete');
}
