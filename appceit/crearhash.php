<?php
// Escribe aquí la nueva contraseña que quieres usar
$nueva_password = 'admin123'; 

$hash_nuevo = password_hash($nueva_password, PASSWORD_DEFAULT);

echo "Tu nuevo hash es: <br>";
echo $hash_nuevo;
?>