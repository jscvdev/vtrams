<?php

declare(strict_types=1);

function get_document_deleteid (object $pdo, string $document_id){
    $query = "SELECT * FROM pending WHERE document_id = :document_id";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);

    $statement->execute();

    return $statement->rowCount() < 0;
}

function pending_delete_target_document_logs($pdo, $document_id)
{
    $delete_query = "DELETE pending, action_logs, document_tracking FROM pending JOIN action_logs ON pending.document_id = action_logs.document_id LEFT JOIN document_tracking ON pending.document_id = document_tracking.document_id WHERE pending.document_id = :document_id";

    $statement = $pdo->prepare($delete_query);

    $statement->bindParam(":document_id",$document_id);

    $statement->execute();

    return $statement->rowCount() > 0;
}
