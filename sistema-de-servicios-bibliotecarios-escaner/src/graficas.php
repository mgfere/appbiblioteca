<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gráficas</title>
        <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="output.css">
    <script src="https://unpkg.com/xlsx@0.16.9/dist/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/file-saverjs@latest/FileSaver.min.js"></script>
    <script src="https://unpkg.com/tableexport@latest/dist/js/tableexport.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="graficas.js"></script>
    
</head>
    <body>
    <script>
        const serviciosData = <?php echo json_encode($groupedData); ?>;
        const carrerasData = <?php echo json_encode($carrerasGroupedData); ?>;
    </script>
        <div class="chart-container">
            <canvas id="serviciosChart" width="400" height="400"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="carrerasChart" width="400" height="400"></canvas>
        </div>
        <iframe src="vista_admin.php"></iframe>
       <script>
        // Crear un elemento canvas para la gráfica de pastel
        const canvas = document.createElement('canvas');
            canvas.id = 'serviciosChart';
            canvas.width = 400;
            canvas.height = 400;
            const chartContainer = document.querySelector('.chart-container'); // Agrega una clase a un contenedor adecuado en tu HTML
            chartContainer.appendChild(canvas);

            // Obtener los datos agrupados para la gráfica de pastel
            const serviciosData = <?php echo json_encode($groupedData); ?>;
            const serviciosLabels = Object.keys(serviciosData);
            const serviciosValues = Object.values(serviciosData);

            // Crear la gráfica de pastel
            new Chart(canvas, {
                type: 'pie',
                data: {
                    labels: serviciosLabels,
                    datasets: [{
                        data: serviciosValues,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(63, 215, 125, 0.92)',
                            'rgba(128, 0, 0, 0.7)',
                            'rgba(0, 128, 0, 0.7)',
                            'rgba(0, 0, 128, 0.7)',
                            'rgba(255, 165, 0, 0.8)',
                            'rgba(0, 255, 255, 0.8)',
                            'rgba(255, 0, 255, 0.8)',
                            'rgba(128, 128, 0, 0.9)',
                            'rgba(0, 128, 128, 0.9)',
                            'rgba(128, 0, 128, 0.9)',
                            'rgba(0, 0, 0, 0.6)'
                        ],
                    }],
                },
            });
        // Crear un elemento canvas para la gráfica de pastel de carreras
        const carrerasCanvas = document.createElement('canvas');
        carrerasCanvas.id = 'carrerasChart';
        carrerasCanvas.width = 400;
        carrerasCanvas.height = 400;
        const carrerasChartContainer = document.querySelector('.carreras-chart-container'); // Agrega una clase a un contenedor adecuado en tu HTML
        carrerasChartContainer.appendChild(carrerasCanvas);

        // Obtener los datos agrupados para la gráfica de pastel de carreras
        const carrerasData = <?php echo json_encode($carrerasGroupedData); ?>;
        const carrerasLabels = Object.keys(carrerasData);
        const carrerasValues = Object.values(carrerasData);

        // Crear la gráfica de pastel de carreras
        new Chart(carrerasCanvas, {
            type: 'pie',
            data: {
                labels: carrerasLabels,
                datasets: [{
                    data: carrerasValues,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(63, 215, 125, 0.92)',
                        'rgba(128, 0, 0, 0.7)',
                        'rgba(0, 128, 0, 0.7)',
                        'rgba(0, 0, 128, 0.7)',
                        'rgba(255, 165, 0, 0.8)',
                        'rgba(0, 255, 255, 0.8)',
                        'rgba(255, 0, 255, 0.8)',
                        'rgba(128, 128, 0, 0.9)',
                        'rgba(0, 128, 128, 0.9)',
                        'rgba(128, 0, 128, 0.9)',
                        'rgba(0, 0, 0, 0.6)',
                        'rgba(255, 0, 0, 0.8)',
                        'rgba(0, 255, 0, 0.8)',
                        'rgba(0, 0, 255, 0.8)',
                        'rgba(255, 255, 0, 0.9)',
                        'rgba(255, 0, 255, 0.7)',
                        'rgba(0, 255, 255, 0.7)',
                        'rgba(128, 0, 0, 0.6)',
                        'rgba(0, 128, 0, 0.6)',
                        'rgba(0, 0, 128, 0.6)',
                        'rgba(255, 165, 0, 0.7)',
                        'rgba(0, 255, 255, 0.6)',
                        'rgba(255, 0, 255, 0.6)',
                        'rgba(128, 128, 0, 0.8)',
                        'rgba(0, 128, 128, 0.8)',
                        'rgba(128, 0, 128, 0.8)'

                    ],
                }],
            },
        });
       </script>
    </body>
    </html>
    