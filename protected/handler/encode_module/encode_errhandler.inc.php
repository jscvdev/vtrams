<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/components/helpers/handler_session_err_helper.inc.php';

function check_encode_errors(): void
{
    if (!isset($_SESSION['error_encode'])) {
        return;
    }

    if (isset($_SESSION['purpose_encode'])) {
        unset($_SESSION['purpose_encode']);
        handler_emit_session_errors('error_encode');
        return;
    }

    if (isset($_SESSION['purpose_reply'])) {
        unset($_SESSION['purpose_reply']);
        handler_emit_session_errors('error_encode');
        return;
    }

    handler_emit_session_errors('error_encode');
}
