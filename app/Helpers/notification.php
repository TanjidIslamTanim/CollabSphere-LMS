<?php

declare(strict_types=1);

function createNotification(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    string $type = 'general',
    ?int $referenceId = null
): void {

    $query = $pdo->prepare(
        'INSERT INTO notifications
        (
            user_id,
            title,
            message,
            type,
            reference_id
        )
        VALUES
        (
            :user_id,
            :title,
            :message,
            :type,
            :reference_id
        )'
    );

    $query->execute([
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'reference_id' => $referenceId,
    ]);
}