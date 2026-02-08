<?php

require 'app.php';

function incluirTemplate($nombre): void
{
    include TEMPLATES_URL . "/{$nombre}.php";
}

function adminAutenticado(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $auth = $_SESSION['login'] ?? false;
    $admin_matricula = $_SESSION['administrador'] ?? null;
    $timestamp = $_SESSION['timestamp'] ?? 0;

    $session_expired = (time() - $timestamp) > (2 * 60 * 60);

    if ($auth === true && $admin_matricula && !$session_expired) {
        return true;
    }

    if ($session_expired) {
        session_destroy();
    }

    return false;
}

function usuarioAutenticado(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $auth = $_SESSION['login'] ?? false;

    $id = $_SESSION['usuario_id'] ?? null;
    $nombre = $_SESSION['usuario_nombre'] ?? null;

    $timestamp = $_SESSION['timestamp'] ?? 0;
    $session_expired = ($timestamp > 0) && (time() - $timestamp) > (2 * 60 * 60);

    if ($auth === true && !empty($id) && !empty($nombre) && !$session_expired) {
        return true;
    }

    return false;
}