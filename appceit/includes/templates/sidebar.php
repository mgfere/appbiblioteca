<?php
$rol = (int) $_SESSION['rol'];
$es_admin_master = $_SESSION['es_admin_master'] ?? false;
$matricula_original = $_SESSION['matricula_original'] ?? '';
$id_master_real = $_SESSION['id_master_real'] ?? null;
$perfil_temporal = $_SESSION['perfil_temporal'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Panel de Administrador</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../public/css/bundle.css" />
    <link rel="stylesheet" href="../public/css/sidebar.css" />
    <link rel="icon" href="../logouttn.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body>
    <header class="header--menu">
        <div class="logo">
            <a href="../admin/panel-control.php">
                <img src="../public/img/logouttn.png" alt="Logo Universidad" />
            </a>
        </div>
        <nav>
            <ul class="menu">
                <li>
                    <a href="../admin/panel-control.php">
                        <i class="fas fa-calendar-check"></i>
                        <span>Reservaciones</span>
                    </a>
                </li>

                <?php if ($rol === 1 || $rol === 2): ?>
                    <li class="has-submenu">
                        <a href="../admin/panel-libros.php">
                            <i class="fas fa-book"></i>
                            <span>Gestión de Libros</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="../admin/panel-libros.php"><i class="fas fa-book-open"></i> Ver Libros</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <li class="has-submenu">
                    <a href="panel-prestamos.php">
                        <i class="fas fa-handshake"></i>
                        <span>Préstamos</span>
                    </a>
                    <ul class="submenu">
                        <li><a href="panel-prestamos.php"><i class="fas fa-home"></i> Préstamos Internos</a></li>
                        <li><a href="panel-prestamos-presenciales.php"><i class="fas fa-external-link-alt"></i>
                                Préstamos Externos</a></li>
                    </ul>
                </li>

                <li class="has-submenu">
                    <a href="../admin/panel-usuarios.php">
                        <i class="fas fa-users"></i>
                        <span>Usuarios</span>
                    </a>
                    <ul class="submenu">
                        <li><a href="../admin/panel-usuarios.php"><i class="fas fa-user-graduate"></i> Estudiantes</a>
                        </li>
                        <li><a href="../admin/panel-docentes.php"><i class="fas fa-user-tie"></i>Docentes</a>
                        </li>
                        <li><a href="../admin/panel-usuarios-externos.php"><i class="fas fa-user"></i> Usuarios
                                Externos</a></li>
                    </ul>
                </li>

                <?php if ($rol === 1 || $rol === 2): ?>
                    <li class="has-submenu">
                        <a href="../admin/panel-administradores.php">
                            <i class="fas fa-user-shield"></i>
                            <span>Administración</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="../admin/panel-administradores.php"><i class="fas fa-user-tie"></i>
                                    Administradores</a></li>
                            <li><a href="../admin/panel-secciones.php"><i class="fab fa-elementor"></i> Secciones</a></li>
                            <li>
                                <a href="#" onclick="confirmarVolverPrincipal(event)">
                                    <i class="fa-solid fa-right-left"></i>
                                    <span>Ir a CEIT Registros</span>
                                </a>
                            </li>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if ($rol === 1 || $rol === 2): ?>
                    <li class="has-submenu">
                        <a href="../admin/estadisticas.php">
                            <i class="fas fa-chart-line"></i>
                            <span>Reportes</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="../admin/estadisticas.php"><i class="fas fa-chart-area"></i> Prestamos</a></li>
                            <li><a href="../admin/devoluciones.php"><i class="fas fa-reply"></i>Devoluciones</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                  <li class="logout">
                    <a href="./cerrar-sesion.php" onclick="return confirmarLogout()">
                        <i class="fas fa-arrow-left"></i> <span>Volver</span>
                    </a>
                </li>
            </ul>
        </nav>
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <div class="contenedor">
        <div class="main--content">
            <!-- Tu contenido aquí -->
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const menuToggle = document.getElementById('menuToggle');
            const navMenu = document.querySelector('.header--menu nav');
            const header = document.querySelector('.header--menu');
            const mainContent = document.querySelector('.main--content');

            // Toggle del menú principal
            menuToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const isActive = navMenu.classList.contains('active');

                if (isActive) {
                    closeMenu();
                } else {
                    openMenu();
                }
            });

            // Función para abrir menú
            function openMenu() {
                navMenu.classList.add('active');
                menuToggle.querySelector('i').classList.remove('fa-bars');
                menuToggle.querySelector('i').classList.add('fa-times');
                document.body.style.overflow = 'hidden'; // Prevenir scroll del body
            }

            // Función para cerrar menú
            function closeMenu() {
                navMenu.classList.remove('active');
                menuToggle.querySelector('i').classList.add('fa-bars');
                menuToggle.querySelector('i').classList.remove('fa-times');
                document.body.style.overflow = ''; // Restaurar scroll del body

                // Cerrar todos los submenús
                const hasSubmenuItems = document.querySelectorAll('.has-submenu');
                hasSubmenuItems.forEach(item => {
                    item.classList.remove('active');
                });
            }

            // Manejo de submenús
            const hasSubmenuItems = document.querySelectorAll('.has-submenu');

            hasSubmenuItems.forEach(item => {
                const link = item.querySelector('a');
                const submenu = item.querySelector('.submenu');

                link.addEventListener('click', function (e) {
                    if (window.innerWidth <= 991) {
                        e.preventDefault();
                        e.stopPropagation();

                        const isCurrentlyActive = item.classList.contains('active');

                        // Cerrar otros submenús primero
                        hasSubmenuItems.forEach(otherItem => {
                            if (otherItem !== item) {
                                otherItem.classList.remove('active');
                            }
                        });

                        // Abrir/cerrar el actual
                        if (isCurrentlyActive) {
                            item.classList.remove('active');
                        } else {
                            item.classList.add('active');
                        }
                    }
                });

                // Cerrar submenú al hacer clic en sus enlaces
                if (submenu) {
                    const submenuLinks = submenu.querySelectorAll('a');
                    submenuLinks.forEach(submenuLink => {
                        submenuLink.addEventListener('click', function (e) {
                            if (window.innerWidth <= 991) {
                                // Permitir que el enlace funcione normalmente
                                // pero cerrar el menú después de un pequeño delay
                                setTimeout(() => {
                                    closeMenu();
                                }, 150);
                            }
                        });
                    });
                }
            });

            // Cerrar menú al hacer clic fuera
            document.addEventListener('click', function (e) {
                const isClickInsideMenu = navMenu.contains(e.target);
                const isClickOnToggle = menuToggle.contains(e.target);

                if (!isClickInsideMenu && !isClickOnToggle && navMenu.classList.contains('active')) {
                    closeMenu();
                }
            });

            // Prevenir cierre al hacer clic dentro del menú principal
            navMenu.addEventListener('click', function (e) {
                // Solo prevenir si el clic es en el contenedor del menú, no en los submenús
                if (e.target === navMenu || e.target.classList.contains('menu')) {
                    e.stopPropagation();
                }
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth > 991) {
                    if (navMenu.classList.contains('active')) {
                        closeMenu();
                    }

                    document.body.style.overflow = '';
                }
            });

            function setActivePage() {
                const currentPath = window.location.pathname.split('/').pop();
                const menuLinks = document.querySelectorAll('.menu li:not(.has-submenu) a, .submenu li a, .menu > li > a');

                const allMenuItems = document.querySelectorAll('.menu li');
                allMenuItems.forEach(item => {
                    item.classList.remove('current-page', 'active');
                });

                menuLinks.forEach(link => {
                    const linkPath = link.getAttribute('href');
                    if (linkPath) {
                        const linkFile = linkPath.split('/').pop();
                        if (linkFile === currentPath) {
                            const menuItem = link.closest('li');
                            if (menuItem) {
                                menuItem.classList.add('current-page');
                                // Si es submenú, marca también el padre
                                const parentMenu = menuItem.closest('.has-submenu');
                                if (parentMenu) {
                                    parentMenu.classList.add('current-page');
                                }
                            }
                        }
                    }
                });
            }

            setActivePage();

            window.addEventListener('scroll', function () {
                if (window.scrollY > 10) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });

            if ('ontouchstart' in window) {
                document.body.classList.add('touch-device');

                hasSubmenuItems.forEach(item => {
                    const submenu = item.querySelector('.submenu');
                    if (submenu) {
                        submenu.addEventListener('touchstart', function (e) {
                            e.stopPropagation();
                        });
                    }
                });
            }
        });

        // Función para confirmar logout
        function confirmarLogout() {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¿Deseas volver tu login.uttn.app?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#09a787',
                confirmButtonText: 'Sí, quiero volver',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = './cerrar-sesion.php';
                }
            });
            return false;
        }

        function confirmarVolverPrincipal(event) {
            event.preventDefault();

            Swal.fire({
                title: '¿Volver al Sistema Principal?',
                text: "Serás redirigido manteniendo tu sesión activa",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#09a787',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, volver',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../admin/generar_sso_principal.php';
                }
            });
        }
    </script>
</body>

</html>