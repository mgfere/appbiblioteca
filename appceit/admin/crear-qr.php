<?php
require '../includes/funciones.php';
$auth = adminAutenticado();
if (!$auth) {
    header('Location: login.php');
    exit;
}


//se

$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
$rolAdministrador = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;
$idAdministrador = isset($_SESSION['id']) ? $_SESSION['id'] : null;
require '../includes/config/database.php';
$db = conectarDB();
// Obtener secciones
$secciones = [];
$resSecciones = mysqli_query($db, "SELECT id, nombre_seccion FROM secciones ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($resSecciones)) {
    $secciones[] = $row;
}
// Obtener libros con sección
$libros = [];
$resLibros = mysqli_query($db, "SELECT id, titulo, codigo, editorial, seccionId FROM libros WHERE QR = 0 ORDER BY codigo ASC");
while ($row = mysqli_fetch_assoc($resLibros)) {
    $libros[] = $row;
}
incluirTemplate('sidebar');
?>
<style>
    /* Estilos generales existentes */
    .tabular-wrapper h2 {
        margin-top: 0;
        color: #09a787;
        font-size: 24px;
        font-weight: 600;
    }

    .btn-volver-wrapper {
        margin: 20px;
    }

    .btn-volver {
        background-color: #0978a7;
        color: white;
        padding: 10px 20px;
        border: none;
        margin: 10px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 20px;
    }

    a {
        color: white !important;
    }

    .btn-volver:hover {
        background-color: rgb(111, 159, 161);
    }

    .tabular-wrapper {
        width: 100%;
        margin-top: 20px;
        padding: 20px;
        background-color: #f8f9fa;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        clear: both;
    }

    .tabular-wrapper label {
        display: block;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
        font-size: 14px;
    }

    .tabular-wrapper select {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        background-color: white;
        color: #333;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
        margin-bottom: 15px;
        appearance: none;
        background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 4 5\'><path fill=\'%23666\' d=\'M2 0L0 2h4zm0 5L0 3h4z\'/></svg>');
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 12px;
        cursor: pointer;
    }

    .tabular-wrapper select:hover {
        border-color: #09a787;
    }

    .tabular-wrapper select:focus {
        outline: none;
        border-color: #09a787;
        box-shadow: 0 0 0 3px rgba(9, 167, 135, 0.1);
    }

    .tabular-wrapper button {
        background-color: #09a787;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease;
        box-shadow: 0 2px 4px rgba(9, 167, 135, 0.2);
    }

    .tabular-wrapper button:hover {
        background-color: rgb(61, 180, 148);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(9, 167, 135, 0.3);
    }

    .tabular-wrapper button:active {
        transform: translateY(0);
    }

    .btn-descarga {
        background-color: #0978a7;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        margin-top: 15px;
        min-width: 180px;
    }

    .btn-descarga i {
        margin-right: 8px;
        font-size: 18px;
    }

    .btn-descarga:hover {
        background-color: #075c82;
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
    }

    .btn-descarga:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    #download-qr-pdf-btn {
        background-color: #dc3545;
        padding: 10px;
        border-radius: 10px;
    }

    #download-qr-pdf-btn:hover {
        background-color: #c82333;
    }


    #download-qr-png-btn {
        background-color: #28a745;
        margin-left: 15px;
        padding: 10px;
        border-radius: 10px;
    }

    #download-qr-png-btn:hover {
        background-color: #218838;
    }


    #qr-result {
        margin: 20px;
        text-align: center;
    }

    @media (max-width: 768px) {
        .tabular-wrapper {
            padding: 15px;
            margin-top: 15px;
        }

        .tabular-wrapper select,
        .tabular-wrapper button,
        .btn-descarga {
            /* Incluimos la nueva clase */
            font-size: 13px;
            padding: 10px 20px;
            /* Ajustamos padding para móviles */
            min-width: unset;
            /* Quitar ancho mínimo en móviles */
        }

        .btn-descarga i {
            margin-right: 5px;
            /* Ajustar espacio icono en móviles */
            font-size: 16px;
        }
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<div class="container main--content">
    <div class="header--wrapper">
        <div class="header--title">
            <span style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; color: #09a787; width: 230px; text-transform: uppercase">
                <?php if ($rolAdministrador === '1') {
                    echo 'Administrador general';
                } else {
                    echo 'Administrador';
                } ?>
            </span>
            <span>Bienvenido, <?php echo htmlspecialchars($nombreAdministrador); ?></span>
            <h2>Generar Código QR</h2>
        </div>
        <div class="user--info">
            <div class="search--box">
                <i class="fas fa-search"></i>
                <input type="text" id="buscar" placeholder="Buscar" disabled />
            </div>
            <img src="../public/img/logouttn.png" alt="Foto de perfil" />
        </div>
    </div>
    <div class="tabular-wrapper">
        <div class="btn-volver-wrapper">
            <a href="panel-libros.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
        <div>
            <h2>Selecciona una sección y luego un libro para generar el código QR.</h2>
            <label for="seccion">Selecciona una sección:</label>
            <select id="seccion" name="seccion">
                <option value="">-- Todas las secciones --</option>
                <?php foreach ($secciones as $seccion): ?>
                    <option value="<?php echo $seccion['id']; ?>"><?php echo htmlspecialchars($seccion['nombre_seccion']); ?></option>
                <?php endforeach; ?>
            </select>
            <label for="libro">Selecciona un libro:</label>
            <select id="libro" name="libro">
                <option value="">-- Selecciona un libro --</option>
                <?php foreach ($libros as $libro): ?>
                    <option value="<?php echo $libro['id']; ?>" data-codigo="<?php echo htmlspecialchars($libro['codigo']); ?>" data-titulo="<?php echo htmlspecialchars($libro['titulo']); ?>" data-seccion="<?php echo $libro['seccionId']; ?>">
                        <?php echo htmlspecialchars($libro['titulo']) . ' - ' . htmlspecialchars($libro['codigo']) . ' - ' . htmlspecialchars($libro['editorial']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button onclick="generarQR()">
                <i class="fas fa-qrcode">
                </i> Generar QR</button>
            <div id="qr-result" style="display: none;">
                <img id="qr-image" src="" alt="Código QR generado" />
                <br>
                <a id="download-qr-pdf-btn" href="#" style="display:none; margin-top:10px;" class="btn-continuar"><i class="fas fa-file-pdf"></i> Descargar QR (PDF)</a>
                <a id="download-qr-png-btn" href="#" download style="display:none; margin-top:10px; margin-left: 10px;" class="btn-continuar"><i class="fas fa-download"></i> Descargar QR (PNG)</a>
                <p id="qr-message" style="margin-top: 10px;"></p>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const selectSeccion = document.getElementById('seccion');
    const selectLibro = document.getElementById('libro');
    const allOptions = Array.from(selectLibro.options).slice(1);
    selectSeccion.addEventListener('change', function() {
        const seccionId = this.value;
        selectLibro.innerHTML = '<option value="">-- Selecciona un libro --</option>';
        allOptions.forEach(opt => {
            if (!seccionId || opt.getAttribute('data-seccion') === seccionId) {
                selectLibro.appendChild(opt);
            }
        });
        selectLibro.selectedIndex = 0;
    });

    function generarQR() {
        const libroSelect = document.getElementById("libro");
        const libroId = libroSelect.value;
        const selectedOption = libroSelect.options[libroSelect.selectedIndex];
        const libroCodigo = selectedOption.getAttribute('data-codigo');
        const libroTitulo = selectedOption.getAttribute('data-titulo');

        if (!libroId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Por favor, selecciona un libro.',
            });
            return;
        }

        let tempDiv = document.createElement('div');
        tempDiv.style.display = 'none';
        document.body.appendChild(tempDiv);

        const qr = new QRCode(tempDiv, {
            text: libroId,
            width: 256,
            height: 256,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        setTimeout(() => {
            const img = tempDiv.querySelector("img") || tempDiv.querySelector("canvas");
            if (!img) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo generar el código QR. Inténtalo de nuevo.',
                });
                tempDiv.remove();
                return;
            }

            let dataUrl;
            if (img.tagName === "IMG") {
                dataUrl = img.src;
            } else if (img.tagName === "CANVAS") {
                dataUrl = img.toDataURL("image/png");
            }

            document.getElementById("qr-image").src = dataUrl;
            document.getElementById("qr-message").textContent = "Código QR generado exitosamente.";
            document.getElementById("download-qr-png-btn").style.display = "block";
            document.getElementById("qr-result").style.display = "block";

            // Limpiar y normalizar nombre de archivo (sin espacios ni caracteres especiales)
            function slugify(text) {
                return text.toString().toLowerCase()
                    .replace(/\s+/g, '-') // Reemplaza espacios por -
                    .replace(/[^\w\-]+/g, '') // Elimina caracteres no válidos
                    .replace(/\-\-+/g, '-') // Reemplaza múltiples - por uno solo
                    .replace(/^-+/, '') // Elimina - al inicio
                    .replace(/-+$/, ''); // Elimina - al final
            }
            // Genera el nombre de archivo que esperas que guardar-qr.php use
            let suggestedFileName = `${slugify(libroCodigo)}_${slugify(libroTitulo)}.png`;

            // Obtén las referencias a los nuevos botones
            const downloadQrPdfBtn = document.getElementById("download-qr-pdf-btn");
            const downloadQrPngBtn = document.getElementById("download-qr-png-btn");


            // Petición a guardar-qr.php para guardar la imagen y obtener el nombre final
            fetch("../includes/guardar-qr.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        libroId: libroId,
                        qrData: dataUrl,
                        libroCodigo: libroCodigo, // Asegúrate de enviar estos al PHP
                        libroTitulo: libroTitulo // Asegúrate de enviar estos al PHP
                    })
                })
                .then(res => res.json()) // Espera una respuesta JSON
                .then(response => {
                    if (response.status === "success" && response.fileName) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: 'Código QR generado y guardado exitosamente.',
                        });

                        // Configura el enlace para descargar el PDF
                        downloadQrPdfBtn.href = `../includes/DescargarPDF.php?img=${encodeURIComponent(response.fileName)}&type=qr`;
                        downloadQrPdfBtn.style.display = "inline-block"; // Asegura que el botón de PDF se muestre

                        // Configura el enlace para descargar el PNG (si aún lo quieres directo)
                        downloadQrPngBtn.href = dataUrl;
                        downloadQrPngBtn.download = response.fileName; // Usa el nombre de archivo que devolvió el servidor
                        downloadQrPngBtn.style.display = "inline-block";


                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo guardar el código QR: ' + (response.message || 'Error desconocido'),
                        });
                        downloadQrPdfBtn.style.display = "none"; // Oculta el botón de PDF si hay error
                        downloadQrPngBtn.style.display = "none"; // Oculta el botón de PNG si hay error
                    }
                    tempDiv.remove(); // Limpia el div temporal
                })
                .catch(error => {
                    console.error('Error al guardar QR:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error de red o del servidor al guardar el QR.',
                    });
                    tempDiv.remove();
                });
        }, 800);
    }
</script>
</body>

</html>