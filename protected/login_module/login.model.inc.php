<?php

declare(strict_types=1);

function get_user(object $pdo, string $emp_id) {
    $query = "SELECT * FROM user_group WHERE emp_id= :emp_id;";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":emp_id", $emp_id);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC);
    return $result;
}