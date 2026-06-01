<?php

declare(strict_types=1);

function insert_to_pending(object $pdo, string $document_id, string $document_title, string $document_description, string $document_receive_type, string $encoded_from,
                           string $encoded_by, string $document_type, string $no_pages, string $document_receiver, string $document_sender, string $document_date,
                           string $currTime, string $action, string $priority, string $purpose, string $office_from, string $for_action, string $complexity,
                           string $fileName, string $filePath, string $fileType) {
    $query = "INSERT INTO pending (document_id, document_title, document_desc, document_receive_type, encoded_from, encoded_by, document_type, no_pages, document_receiver, document_sender, document_date, datetime_encoded ,document_status, priority, purpose, office_from, for_action, complexity, file_name, file_path, file_type) 
                        VALUES (:document_id, :document_title, :document_desc, :document_receive_type, :encoded_from, :encoded_by, :document_type, :no_pages, :document_receiver, :document_sender, :document_date, :datetime_encoded, :document_status, :priority, :purpose, :office_from, :for_action, :complexity, :file_name, :file_path, :file_type)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);
    $statement->bindParam(":document_title",$document_title);
    $statement->bindParam(":document_desc",$document_description);
    $statement->bindParam(":document_receive_type",$document_receive_type);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":document_type",$document_type);
    $statement->bindParam(":no_pages",$no_pages);
    $statement->bindParam(":document_receiver",$document_receiver);
    $statement->bindParam(":document_sender",$document_sender);
    $statement->bindParam(":document_date",$document_date);
    $statement->bindParam(":datetime_encoded",$currTime);
    $statement->bindParam(":document_status",$action);
    $statement->bindParam(":priority",$priority);
    $statement->bindParam(":purpose",$purpose);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":for_action",$for_action);
    $statement->bindParam(":complexity",$complexity);
    $statement->bindParam(":file_name",$fileName);
    $statement->bindParam(":file_path",$filePath);
    $statement->bindParam(":file_type",$fileType);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function insert_to_routing(object $pdo, string $document_id, string $document_title, string $document_description, string $document_receive_type, string $encoded_from,
                           string $encoded_by, string $document_type, string $no_pages, string $document_receiver, string $document_sender, string $document_date,
                           string $currTime, string $action, string $priority, string $purpose, string $office_from, string $for_action, string $complexity,
                           string $fileName, string $filePath, string $fileType) {
    $query = "INSERT INTO routing (document_id, document_title, document_desc, document_receive_type, encoded_from, encoded_by, document_type, no_pages, document_receiver, document_sender, document_date, datetime_encoded ,document_status, priority, purpose, office_from, for_action, complexity, file_name, file_path, file_type) 
                        VALUES (:document_id, :document_title, :document_desc, :document_receive_type, :encoded_from, :encoded_by, :document_type, :no_pages, :document_receiver, :document_sender, :document_date, :datetime_encoded, :document_status, :priority, :purpose, :office_from, :for_action, :complexity, :file_name, :file_path, :file_type)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);
    $statement->bindParam(":document_title",$document_title);
    $statement->bindParam(":document_desc",$document_description);
    $statement->bindParam(":document_receive_type",$document_receive_type);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":document_type",$document_type);
    $statement->bindParam(":no_pages",$no_pages);
    $statement->bindParam(":document_receiver",$document_receiver);
    $statement->bindParam(":document_sender",$document_sender);
    $statement->bindParam(":document_date",$document_date);
    $statement->bindParam(":datetime_encoded",$currTime);
    $statement->bindParam(":document_status",$action);
    $statement->bindParam(":priority",$priority);
    $statement->bindParam(":purpose",$purpose);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":for_action",$for_action);
    $statement->bindParam(":complexity",$complexity);
    $statement->bindParam(":file_name",$fileName);
    $statement->bindParam(":file_path",$filePath);
    $statement->bindParam(":file_type",$fileType);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function insert_document_id(object $pdo, string $document_id) {
    $query = "INSERT into encoded_document_id (document_id) VALUES (:document_id)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function get_document_id (object $pdo, string $document_id){
    $query = "
    SELECT dt.*, edi.*
    FROM document_tracking dt
    JOIN encoded_document_id edi ON dt.document_id = edi.document_id
    WHERE dt.document_id = :document_id
";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function insert_into_tracking(object $pdo, string $document_id, string $document_title, string $document_description, string $document_receive_type,
string $encoded_from, string $encoded_by, string $document_type, string $document_no_pages, string $document_receiver, string $document_sender,
string $document_date, string $datetime_encoded, string $action, string $office_from) {
    $query = "INSERT INTO document_tracking (document_id, document_title, document_desc, document_receive_type, encoded_from, encoded_by, document_type, no_pages, document_receiver, document_sender, document_date, datetime_encoded, action, office_from) 
                        VALUES (:document_id, :document_title, :document_desc, :document_receive_type, :encoded_from, :encoded_by, :document_type, :no_pages, :document_receiver, :document_sender, :document_date, :datetime_encoded, :action, :office_from)";

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
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":action",$action);
    $statement->bindParam(":office_from",$office_from);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function check_if_replied (object $pdo, string $document_id){
    $query = "SELECT * FROM document_tracking WHERE document_id = :document_id and reply_status IS NOT NULL";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$document_id);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function check_if_authorized (object $pdo, string $target_document, string $action_by){
    $query = "SELECT * FROM document_tracking WHERE document_id = :document_id and fwd_by = :fwd_by";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":document_id",$target_document);
    $statement->bindParam(":fwd_by",$action_by);

    $statement->execute();

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!empty($row)) {
        return false;
    } else {
        // No rows found, return false or handle the case accordingly
        return true;
    }
}

function update_document_log(object $pdo, string $reply_status, string $action_by,  string $currTime, string $document_id, string $target_document) {
    $query = "UPDATE document_tracking SET reply_status = :reply_status, reply_id = :reply_id, reply_by = :reply_by, datetime_reply = :datetime_reply  WHERE document_id = :target_document";

    $statement = $pdo->prepare($query);
    $cons = "TBD";

    $statement->bindParam(":reply_status",$reply_status);
    $statement->bindParam(":reply_id",$document_id);
    $statement->bindParam(":reply_by",$action_by);
    $statement->bindParam(":datetime_reply",$currTime);
    $statement->bindParam(":target_document",$target_document);

    $statement->execute();

    return $statement->rowCount() > 0;
}