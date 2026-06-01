<?php

/**
 * Check whether a UDC is already assigned in user_group.
 */
function udc_exists(PDO $pdo, string $udc): bool
{
    $query = 'SELECT 1 FROM user_group WHERE udc = :udc LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->bindParam(':udc', $udc, PDO::PARAM_STR);
    $statement->execute();

    return (bool) $statement->fetchColumn();
}

/**
 * Generate a unique random UDC (default 5 alphanumeric characters).
 *
 * @throws RuntimeException When no unique value can be produced within max attempts.
 */
function generate_unique_udc(PDO $pdo, int $length = 5, int $maxAttempts = 100): string
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $charMax = strlen($characters) - 1;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $candidate = '';
        for ($i = 0; $i < $length; $i++) {
            $candidate .= $characters[random_int(0, $charMax)];
        }
        if (!udc_exists($pdo, $candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Unable to generate a unique UDC after ' . $maxAttempts . ' attempts.');
}
