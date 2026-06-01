<?php

declare(strict_types=1);

function routing_get_document_deleteid (object $pdo, string $document_id){
    $query = "SELECT * FROM routing WHERE document_id = :document_id";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);

    $statement->execute();

    return $statement->rowCount() < 0;
}

function routing_delete_target_document_logs($pdo, $document_id)
{
    $delete_query = "DELETE routing, action_logs, document_tracking FROM routing JOIN action_logs ON routing.document_id = action_logs.document_id LEFT JOIN document_tracking ON routing.document_id = document_tracking.document_id WHERE routing.document_id = :document_id";

    $statement = $pdo->prepare($delete_query);

    $statement->bindParam(":document_id",$document_id);

    $statement->execute();

    return $statement->rowCount() > 0;
}
