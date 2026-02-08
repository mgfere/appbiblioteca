<?php
// Mantén el código PHP existente
require '../includes/funciones.php'; 

$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
$rolAdministrador = (int) $_SESSION['rol'];
$idAdministrador = isset($_SESSION['id']) ? $_SESSION['id'] : null;

require '../includes/config/database.php';
$db = conectarDB();

incluirTemplate('sidebar');
?>

<div class="container main--content">
    <div class="header--wrapper">
        <div class="header--title">
            <span style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; color: #09a787; width: 230px; text-transform: uppercase">
               <?php if ($rolAdministrador === 1) {
          echo 'Máster';
        } elseif($rolAdministrador === 2) {
          echo 'Administrador general';
        }else{
          echo 'Administrador';
        } ?>
            </span>
            <span>Bienvenido, <?php echo htmlspecialchars($nombreAdministrador); ?></span>
            <h2>Escanear Libro para Préstamo Presencial-interno</h2>
        </div>
        
        <div class="user--info">
            <div class="search--box">
                <i class="fas fa-search"></i>
                <input type="text" id="buscar" placeholder="Buscar" disabled />
            </div>
            <img src="../public/img/logouttn.png" alt="Foto de perfil" />
        </div>
    </div>

    <div class="tabular--wrapper">
        <h3 class="main--title">Escanea el Código del Libro</h3>
        <div class="btn-volver-wrapper">
            <a href="panel-prestamos.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
        
        <div class="scanner-interface">
            <h2>ESCANEAR CÓDIGO DEL LIBRO</h2>
            <div class="manual-input">
                Escanea o ingresa el ID del libro
                <input type="text" id="bookIdInput" placeholder="ID del libro" autofocus />
            </div>
            <button class="btn-continuar" id="btnContinuarPrestamo" type="button">Continuar</button>
        </div>
    </div>
</div>

<style>
    /* Mantén los estilos relevantes, pero elimina los relacionados con el escáner QR */
    .scanner-interface {
        text-align: center;
        padding: 20px;
        max-width: 100%;
        box-sizing: border-box;
    }

    .scanner-interface h2 {
        color: #333;
        margin-bottom: 20px;
        font-size: 24px;
    }

    .manual-input {
        margin-top: 20px;
        color: #555;
        text-align: center;
        padding: 0 20px;
        font-size: 16px;
    }

    .manual-input input[type="text"] {
        padding: 12px 15px;
        border: 2px solid #09a787;
        border-radius: 8px;
        width: 90%;
        max-width: 400px;
        margin-top: 15px;
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .manual-input input[type="text"]:focus {
        outline: none;
        border-color: #078a6f;
        box-shadow: 0 0 0 3px rgba(9, 167, 135, 0.2);
    }

    .btn-continuar {
        background-color: #09a787;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 20px;
        transition: background-color 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .btn-continuar:hover {
        background-color: #078a6f;
        transform: translateY(-1px);
    }

    .btn-continuar:disabled {
        background-color: #ccc;
        cursor: not-allowed;
        transform: none;
    }

    .btn-volver-wrapper {
        margin-top: 30px;
    }

    .btn-volver {
        background-color: #0978a7;
        color: white !important;
        padding: 10px 20px;
        border: none;
        margin: 10px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 20px;
        text-decoration: none;
        display: inline-block;
        transition: background-color 0.3s ease;
    }

    .btn-volver:hover {
        background-color: rgb(111, 159, 161);
        color: white !important;
    }

    /* Mantén los estilos para el selector de libros y responsive */
    #libroSelector {
        width: 90%;
        max-width: 800px;
        padding: 12px 15px;
        margin: 15px auto;
        border: 2px solid #09a787;
        border-radius: 8px;
        font-size: 14px;
        color: #333;
        background-color: white;
        appearance: none;
        background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 5"><path fill="%23666" d="M2 0L0 2h4zm0 5L0 3h4z"/></svg>');
        background-repeat: no-repeat;
        background-position: right 15px center;
        background-size: 12px;
        cursor: pointer;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
        display: block;
        box-sizing: border-box;
    }

    #libroSelector:hover {
        border-color: #078a6f;
        box-shadow: 0 2px 4px rgba(9, 167, 135, 0.2);
    }

    #libroSelector:focus {
        outline: none;
        border-color: #09a787;
        box-shadow: 0 0 0 3px rgba(9, 167, 135, 0.3);
    }

    #libroSelector option {
        padding: 10px;
        background-color: white;
        color: #333;
        font-size: 13px;
        line-height: 1.4;
    }

    .libro-selector-container {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-top: 20px;
        padding: 0 20px;
        box-sizing: border-box;
    }

    .selector-info {
        color: #666;
        font-size: 15px;
        margin-bottom: 15px;
        text-align: center;
        max-width: 600px;
        line-height: 1.5;
        background-color: #f8f9fa;
        padding: 12px 20px;
        border-radius: 8px;
        border-left: 4px solid #09a787;
    }

    @media (max-width: 768px) {
        .scanner-interface h2 {
            font-size: 20px;
        }

        #libroSelector {
            width: 95%;
            padding: 10px 12px;
            font-size: 13px;
            margin: 10px auto;
        }
        
        #libroSelector option {
            font-size: 12px;
            padding: 8px;
        }
        
        .manual-input input[type="text"] {
            width: 95%;
            padding: 10px 12px;
        }
        
        .selector-info {
            font-size: 13px;
            padding: 10px 15px;
            margin: 0 10px 15px 10px;
        }

        .btn-continuar {
            padding: 10px 20px;
            font-size: 14px;
        }
    }

    @media (max-width: 480px) {
        #libroSelector {
            width: 98%;
            font-size: 12px;
            padding: 8px 10px;
        }
        
        .manual-input {
            padding: 0 10px;
        }

        .scanner-interface {
            padding: 15px 10px;
        }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookIdInput = document.getElementById('bookIdInput');
    const btnContinuar = document.getElementById('btnContinuarPrestamo');
    const scannerInterface = document.querySelector('.scanner-interface');
    let isProcessing = false;
    

    // Función principal que se llama al buscar
    const handleSearch = () => {
        if (isProcessing) return;

        // Limpia cualquier selector que ya exista
        const oldSelectorContainer = document.querySelector('.libro-selector-container');
        if (oldSelectorContainer) {
            oldSelectorContainer.remove();
        }

        const bookId = bookIdInput.value.trim();
        if (!bookId) {
            Swal.fire("Atención", "Por favor, ingresa un código para buscar.", "warning");
            return;
        }

        isProcessing = true;
        btnContinuar.disabled = true;

        $.ajax({
            url: 'validar-libro-ajax.php',
            method: 'POST',
            data: { bookId: bookId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // ✅ AQUÍ ESTÁ LA LÓGICA CORRECTA
                    if (response.action === 'redirect') {
                        // Acción: Redirigir (un solo libro)
                        window.location.href = 'prestamos/RegistrarPrestamoSinID.php?bookId=' + response.bookId;
                    } else if (response.action === 'select') {
                        // Acción: Mostrar selector (múltiples libros)
                        displayBookSelector(response.libros);
                    }
                } else {
                    // Si success es false, mostramos el mensaje de error
                    Swal.fire("Error", response.message, "error");
                }
            },
            error: function() {
                Swal.fire("Error de Conexión", "No se pudo comunicar con el servidor.", "error");
            },
            complete: function() {
                isProcessing = false;
                btnContinuar.disabled = false;
            }
        });
    };

    // Función que DIBUJA el selector en la página
    const displayBookSelector = (libros) => {
        const container = document.createElement('div');
        container.className = 'libro-selector-container';

        const info = document.createElement('div');
        info.className = 'selector-info';
        info.innerHTML = `🔎 Se encontraron ${libros.length} libros. Por favor, selecciona el correcto:`;

        const select = document.createElement('select');
        select.id = 'libroSelector';
        select.innerHTML = '<option value="">-- Elige un libro de la lista --</option>';

        libros.forEach(libro => {
            const option = document.createElement('option');
            option.value = libro.id;
            let text = `${libro.titulo.substring(0, 40)}... (ID: ${libro.id}) - Disp: ${libro.cantidad}`;
            
            // Si el libro no está disponible, lo marcamos
            if (libro.status !== 'Activo' || libro.cantidad <= 0) {
                option.disabled = true;
                text += ' (No disponible)';
            }
            option.textContent = text;
            select.appendChild(option);
        });

        container.appendChild(info);
        container.appendChild(select);
        scannerInterface.appendChild(container); // Lo agrega dentro del área del scanner

        // IMPORTANTE: Le decimos al botón "Continuar" que ahora use el selector
        btnContinuar.onclick = () => {
            const selectedId = select.value;
            if (selectedId) {
                window.location.href = 'prestamos/RegistrarPrestamoSinID.php?bookId=' + selectedId;
            } else {
                Swal.fire("Atención", "Debes seleccionar un libro de la lista.", "warning");
            }
        };
    };

    // Asignamos los eventos para que todo funcione
    bookIdInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSearch();
        }
    });
    
    // Le decimos al botón que al inicio, siempre ejecute la búsqueda
    btnContinuar.onclick = handleSearch;
});
</script>
<?php
mysqli_close($db);
incluirTemplate('footer');
?>