<?php

declare(strict_types=1);

function document_user_action(object $pdo, string $document_id, string $document_title, string $document_description, string $document_receive_type, string $document_type,
                              string $no_pages, string $document_receiver, string $document_sender, string $document_date, string $action, string $datetime_action, string $action_from, string $action_by, string $action_by_from, string $encoded_by, string $office_from, string $remarks) {
    $query = "INSERT into action_logs(document_id, document_title, document_desc, document_receive_type, document_type, no_pages, document_receiver, document_sender, document_date, action, datetime_action, action_from, action_by, action_by_from, encoded_by, office_from, remarks)
                        VALUES (:document_id, :document_title, :document_desc, :document_receive_type, :document_type, :no_pages, :document_receiver,:document_sender, :document_date, :action, :datetime_action, :action_from, :action_by, :action_by_from, :encoded_by, :office_from, :remarks)";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);
    $statement->bindParam(":document_title",$document_title);
    $statement->bindParam(":document_desc",$document_description);
    $statement->bindParam(":document_receive_type",$document_receive_type);
    $statement->bindParam(":document_type",$document_type);
    $statement->bindParam(":no_pages",$no_pages);
    $statement->bindParam(":document_receiver",$document_receiver);
    $statement->bindParam(":document_sender",$document_sender);
    $statement->bindParam(":document_date",$document_date);
    $statement->bindParam(":action",$action);
    $statement->bindParam(":datetime_action",$datetime_action);
    $statement->bindParam(":action_from",$action_from);
    $statement->bindParam(":action_by",$action_by);
    $statement->bindParam(":action_by_from",$action_by_from);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":remarks",$remarks);

    $statement->execute();

    return $statement->rowCount() > 0;
}