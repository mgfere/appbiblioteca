<?php
$idusuario = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 0;


?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CEIT</title>
  <!-- Poppins -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
    rel="stylesheet" />
  <link rel="icon" href="logouttn.ico" type="image/x-icon">
  <!-- Css -->
  <link rel="stylesheet" href="public/css/bundle.css" />
</head>

<body>
  <!-- Header -->
  <header>
    <div class="content">
      <div class="menu container">
        <div class="logo">
          <a href="index.php">
            <img src="public/img/logouttn.png" alt="La mejor Universidad">
          </a>
        </div>
        <input type="checkbox" id="menu_input" />
        <label for="menu_input">
          <img class="menu-icono" src="public/img/menu.png" />
        </label>
        <!-- Navbar -->
        <nav class="navbar">
          <ul>
            <li>
              <a href="index.php"><i class="fas fa-home"></i>Inicio</a>
            </li>
            <li>
              <a href="perfil.php"><i class="fas fa-user-circle"></i>Perfil</a>
            </li>
            <li>
              <a href="reservaciones.php?id=<?php echo $_SESSION['usuario_id']; ?>"><i class="fas fa-swatchbook"></i>Reservaciones</a>
            </li>
            <li>
              <a href="cerrar-sesion.php"><i class="fas fa-sign-out-alt"></i>Volver</a>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </header>