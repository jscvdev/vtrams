<?php

declare(strict_types=1);

function get_emp_id(object $pdo, string $emp_id) {
    $query = "SELECT emp_id FROM user_group WHERE emp_id = :emp_id;";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":emp_id", $emp_id);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC);
    return $result;
}

function set_user(object $pdo, string $emp_id, string $emp_fn, string $emp_mi, string $emp_ln, string $section, string $office, string $password, string $randomString) {
    $query = "INSERT INTO user_group(emp_id, emp_fn, emp_mi, emp_ln, section, office, password, udc) VALUES (:emp_id, :emp_fn, :emp_mi, :emp_ln, :section, :office, :password, :udc);";
    $statement = $pdo->prepare($query);

    $options = [
        'cost' => 12
    ];
    $hashedPwd = password_hash($password, PASSWORD_BCRYPT, $options);

    $statement->bindParam(":emp_id", $emp_id);
    $statement->bindParam(":emp_fn", $emp_fn);
    $statement->bindParam(":emp_mi", $emp_mi);
    $statement->bindParam(":emp_ln", $emp_ln);
    $statement->bindParam(":section", $section);
    $statement->bindParam(":office", $office);
    $statement->bindParam(":password", $hashedPwd);
    $statement->bindParam(":udc", $randomString);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC);
    return $result;
}