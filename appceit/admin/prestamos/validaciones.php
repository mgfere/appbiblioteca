<?php
// ========================================
// FUNCIONES DE VALIDACIÓN Y FORMATO
// ========================================

/**
 * Formatea número de teléfono mexicano
 * Convierte cualquier formato a 10 dígitos
 */
function formatearTelefonoMexicano($telefono) {
    // Remover todo lo que no sea número
    $telefono = preg_replace('/[^0-9]/', '', $telefono);
    
    // Si tiene código de país +52, removerlo
    if (strlen($telefono) == 12 && substr($telefono, 0, 2) == '52') {
        $telefono = substr($telefono, 2);
    }
    
    // Si tiene 11 dígitos y empieza con 1, remover el 1
    if (strlen($telefono) == 11 && substr($telefono, 0, 1) == '1') {
        $telefono = substr($telefono, 1);
    }
    
    // Debe quedar exactamente con 10 dígitos
    if (strlen($telefono) == 10) {
        return $telefono;
    }
    
    return false; // Número inválido
}

/**
 * Valida número de teléfono mexicano
 */
function validarTelefonoMexicano($telefono) {
    $telefonoFormateado = formatearTelefonoMexicano($telefono);
    
    if (!$telefonoFormateado) {
        return false;
    }
    
    // Validar que no empiece con 0 o 1
    if (substr($telefonoFormateado, 0, 1) == '0' || substr($telefonoFormateado, 0, 1) == '1') {
        return false;
    }
    
    // Números móviles en México generalmente empiezan con: 2, 3, 4, 5, 6, 7, 8, 9
    $primerDigito = substr($telefonoFormateado, 0, 1);
    if (in_array($primerDigito, ['2', '3', '4', '5', '6', '7', '8', '9'])) {
        return $telefonoFormateado;
    }
    
    return false;
}

/**
 * Formatea teléfono para mostrar (con guiones)
 */
function formatearTelefonoParaMostrar($telefono) {
    $telefonoLimpio = formatearTelefonoMexicano($telefono);
    
    if ($telefonoLimpio && strlen($telefonoLimpio) == 10) {
        // Formato: XXX-XXX-XXXX
        return substr($telefonoLimpio, 0, 3) . '-' . substr($telefonoLimpio, 3, 3) . '-' . substr($telefonoLimpio, 6, 4);
    }
    
    return $telefono; // Devolver original si no se puede formatear
}

/**
 * Valida email con reglas más estrictas
 */
function validarEmailMejorado($email) {
    // Validación básica de PHP
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Validaciones adicionales
    $email = strtolower(trim($email));
    
    // No puede empezar o terminar con punto
    if (substr($email, 0, 1) == '.' || substr($email, -1) == '.') {
        return false;
    }
    
    // No puede tener puntos consecutivos
    if (strpos($email, '..') !== false) {
        return false;
    }
    
    // Debe tener exactamente un @
    if (substr_count($email, '@') != 1) {
        return false;
    }
    
    // Validar dominio
    $partes = explode('@', $email);
    $dominio = $partes[1];
    
    // Dominio debe tener al menos un punto
    if (strpos($dominio, '.') === false) {
        return false;
    }
    
    return $email;
}

/**
 * Genera errores de validación en español
 */
function generarErroresTelefono($telefono) {
    if (empty($telefono)) {
        return "El número de teléfono es obligatorio";
    }
    
    $telefonoSinFormato = preg_replace('/[^0-9]/', '', $telefono);
    
    if (strlen($telefonoSinFormato) < 10) {
        return "El número de teléfono debe tener al menos 10 dígitos";
    }
    
    if (strlen($telefonoSinFormato) > 12) {
        return "El número de teléfono es demasiado largo";
    }
    
    if (!validarTelefonoMexicano($telefono)) {
        return "Formato de teléfono mexicano inválido. Ejemplo: 8991234567";
    }
    
    return "";
}

function generarErroresEmail($email) {
    if (empty($email)) {
        return "El correo electrónico es obligatorio";
    }
    
    if (strlen($email) > 254) {
        return "El correo electrónico es demasiado largo";
    }
    
    if (!validarEmailMejorado($email)) {
        return "Formato de correo electrónico inválido";
    }
    
    return "";
}