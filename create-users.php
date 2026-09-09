<?php

require_once "config/database.php";

$users = [
    [
        "name" => "System Admin",
        "email" => "admin@propflow.com",
        "password" => "Admin@123",
        "role" => "admin"
    ],
    [
        "name" => "Sales Employee",
        "email" => "sales@propflow.com",
        "password" => "Sales@123",
        "role" => "sales"
    ]
];

foreach ($users as $user) {

    $hashedPassword = password_hash(
        $user["password"],
        PASSWORD_DEFAULT
    );

    $stmt = $pdo->prepare("
        INSERT INTO users
        (name, email, password, role)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $user["name"],
        $user["email"],
        $hashedPassword,
        $user["role"]
    ]);
}

echo "Users created successfully.";
?>