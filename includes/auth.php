<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';


function requireLogin()
{
    if (!isset($_SESSION['user'])) {
        header("Location: ../login.php");
        exit;
    }
}


function requireRole($role)
{
    requireLogin();

    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        die("Access Denied");
    }
}


function currentUser()
{
    return $_SESSION['user'] ?? null;
}