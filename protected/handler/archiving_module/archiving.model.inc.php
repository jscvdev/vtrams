<?php

declare(strict_types=1);

function archiving_delete_from_receiving(object $pdo, string $document_id) {
    $query = "DELETE FROM receiving WHERE document_id = :document_id";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function insert_to_archive(object $pdo, string $document_id, string $document_title, string $document_description, string $document_receive_type,
                             string $encoded_from, string $encoded_by, string $document_type, string $document_no_pages, string $document_receiver, string $document_sender,
                             string $document_date, string $forwarded_to, string $archived_from, string $forwarded_by, string $action_by_from, string $remarks, string $datetime_encoded, string $currTime, 
                             string $action, string $justification, string $purpose, string $office_from, string $office_to, string $for_action, string $complexity, string $file_path, string $file_name, string $file_type, string $reply_id) {
    $query = "INSERT INTO archives (document_id, document_title, document_desc, document_receive_type, encoded_from, encoded_by,  document_type, no_pages, document_receiver, document_sender, document_date, fwd_to, archived_from, archived_by, archived_by_from, remarks, datetime_encoded, datetime_archived, document_status, justification, purpose, office_from, office_to, for_action, complexity, file_path, file_name, file_type, reply_id) 
                        VALUES (:document_id, :document_title, :document_desc, :document_receive_type, :encoded_from, :encoded_by, :document_type, :no_pages, :document_receiver, :document_sender, :document_date, :fwd_to, :archived_from, :archived_by, :remarks, :archived_by_from, :datetime_encoded, :datetime_archived, :document_status, :justification, :purpose, :office_from, :office_to, :for_action, :complexity, :file_path, :file_name, :file_type, :reply_id)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);
    $statement->bindParam(":document_title",$document_title);
    $statement->bindParam(":document_desc",$document_description);
    $statement->bindParam(":document_receive_type",$document_receive_type);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":document_type",$document_type);
    $statement->bindParam(":no_pages",$document_no_pages);
    $statement->bindParam(":document_receiver",$document_receiver);
    $statement->bindParam(":document_sender",$document_sender);
    $statement->bindParam(":document_date",$document_date);
    $statement->bindParam(":fwd_to",$forwarded_to);
    $statement->bindParam(":archived_from",$archived_from);
    $statement->bindParam(":archived_by",$forwarded_by);
    $statement->bindParam(":archived_by_from",$action_by_from);
    $statement->bindParam(":remarks",$remarks);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":datetime_archived",$currTime);
    $statement->bindParam(":document_status",$action);
    $statement->bindParam(":justification",$justification);
    $statement->bindParam(":purpose",$purpose);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":for_action",$for_action);
    $statement->bindParam(":complexity",$complexity);
    $statement->bindParam(":file_path",$file_path);
    $statement->bindParam(":file_name",$file_name);
    $statement->bindParam(":file_type",$file_type);
    $statement->bindParam(":reply_id",$reply_id);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function archiving_log_to_document_tracking(object $pdo, string $document_id, string $forwarded_to, string $forwarded_from, string $forwarded_by, string $remarks, string $currTime, string $action, string $turnaround_time) {
    $query = "UPDATE document_tracking SET fwd_to = :fwd_to, fwd_from = :fwd_from, fwd_by = :fwd_by, remarks = :remarks, datetime_forwarded = :datetime_forwarded, datetime_received = :datetime_received, received_by = :received_by, document_status = :document_status, action = :action, turnaround_time = :turnaround_time WHERE document_id = :document_id";

    $statement = $pdo->prepare($query);

    $cons = "RECORDS";

    $statement->bindParam(":document_id",$document_id);
    $statement->bindParam(":fwd_to",$forwarded_to);
    $statement->bindParam(":fwd_from",$forwarded_from);
    $statement->bindParam(":fwd_by",$forwarded_by);
    $statement->bindParam(":remarks",$remarks);
    $statement->bindParam(":datetime_forwarded",$currTime);
    $statement->bindParam(":datetime_received",$currTime);
    $statement->bindParam(":received_by",$cons);
    $statement->bindParam(":document_status",$action);
    $statement->bindParam(":action",$action);
    $statement->bindParam(":turnaround_time",$turnaround_time);

    $statement->execute();

    $statement->execute();

    return $statement->rowCount() > 0;
}

function archiving_get_document_id (object $pdo, $document_id){
    $query = "SELECT * FROM archives WHERE document_id = :document_id";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function set_tracking (object $pdo, string $reply_id ,string $document_id){
    $query = "UPDATE document_tracking SET reply_id = :reply_id WHERE document_id = :document_id";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":reply_id",$reply_id);
    $statement->bindParam(":document_id",$document_id);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function get_reply_id (object $pdo, string $document_id, string $forwarded_by){
    $query = "SELECT document_id FROM document_tracking WHERE document_id = :document_id and encoded_by = :encoded_by";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);
    $statement->bindParam(":encoded_by",$forwarded_by);

    $statement->execute();

    return $statement->rowCount() > 0;
}