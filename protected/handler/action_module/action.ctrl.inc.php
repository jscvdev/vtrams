<?php

declare(strict_types=1);

function log_user_action(object $pdo, string $document_id, string $document_title, string $document_description, string $document_receive_type, string $document_type,
                         string $no_pages, string $document_receiver, string $document_sender, string $document_date, string $action, string $datetime_action, string $action_from, string $action_by, string $action_by_from, string $encoded_by, string $office_from, string $remarks)
{
    document_user_action($pdo, $document_id, $document_title, $document_description, $document_receive_type, $document_type,
        $no_pages, $document_receiver, $document_sender, $document_date, $action, $datetime_action, $action_from, $action_by, $action_by_from, $encoded_by, $office_from, $remarks);
}
