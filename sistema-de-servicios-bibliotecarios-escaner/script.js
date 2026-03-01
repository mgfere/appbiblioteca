//Solicitud de Servicio
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
$(document).ready(function() {
    $('#carrera').change(function() {
        var carreraId = $(this).val();
        $.ajax({
            url: 'getEspecialidades.php',
            method: 'POST',
            data: {
                carreraId: carreraId
            },
            success: function(data) {
                $('#especialidad').html(data);
            }
        });
    });
});

