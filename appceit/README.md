Cambiar las siguientes rutas de manera manual del archivo **admin/panel-control** para que el _fetch_ funcione correctamente.

# Línea 259

`fetch('http://localhost/admin/obtener_reservas.php')`

# Línea 384

`fetch('http://localhost/admin/obtenerNumReservas.php')`

# Línea 395

`fetch('http://localhost/admin/obtenerNumUsuarios.php')`

# Línea 404

`fetch('http://localhost/admin/autorizar_prestamo.php')`

---

También es necesario cambiar la ruta del siguiente archivo ubicado en **public/js/index.js** por la ruta del servidor

# Línea 21

`fetch("http://localhost/libros.php")`

---

Al igual que cambiar las siguientes rutas por las del servidor de las siguientes variables de entorno, ubicadas en el archivo **.env**

`LOGOUT_IMG_URL=https://ceit.space/public/img/logouttn.png`
`LOGOCEIT_IMG_URL=https://ceit.space/admin/logoceit.jpg`

---

Estas rutas deben ser cambiadas por las rutas del **servidor** ejemplo:

**https://ceit.space**/admin/obtener_reservas.php
**https://ceit.space**/admin/obtenerNumReservas.php
**https://ceit.space**/admin/obtenerNumUsuarios.php
**https://ceit.space**/admin/autorizar_prestamo.php
**http://ceit.space**/libros.php
**https://ceit.space**/public/img/logouttn.png
**https://ceit.space**/admin/logoceit.jpg

---

# Tareas Cron

El proyecto requiere que se habiliten algunas tareas o trabajos cron con la siguiente configuración en ciertos archivos

+-------+------+-----+-------+------------+
| Minuto| Hora | Día | Mes   | Día Semana |
+-------+------+-----+-------+------------+
|   0   |  0   |  *  |   *   |    *       |
+-------+------+-----+-------+------------+

wget -q -0 - "https://ceit.space/admin/send_reminders.php" >/dev/null 2>&1

wget -q -0 - "https://ceit.space/admin/eliminar_reservaciones_antiguas.php" >/dev/null 2>81

wget -q -0 - "https://ceit.space/admin/send_reminders_presencial.php" >/dev/null 2>81

Nota: https://ceit.space debe ser **reemplazado**

# Publicacion Docker

- Volume set up
```
docker volume create ceitapp
cd /var/lib/docker/volumes/ceitapp
chmod -R 775 _data
chown -R www-data:www-data _data
```
- Clean up commands
```
docker container stop <CONTAINER_ID>
docker container remove <CONTAINER_ID>
docker image rm uttn/ceitapp:latest
```

- Build Command
```
docker build https://<user>:<password>@gitlab.uttn.app/uttn/sp2024/appceit.git#VERSIONFINALPI2025ESTESI -t uttn/ceitapp:latest
```
- Run Command
```
docker run -d --restart unless-stopped -p 22504:80 -v ceitapp:/var/www/html/imagenes uttn/ceitapp:latest
```
//Antes de que usar la aplicacion se debe instalar java jdk 17 para que funcione la generacion de codigos de barras 
//https://www.oracle.com/java/technologies/javase/jdk17-archive-downloads.html

//Para poder descargar los qr y barras como pdf necesitamos intalar una extension nueva con composer y correrlo en la consola
--composer require dompdf/dompdf

-si llegaras a tener errores como el siguiente:
*curl error 77 while downloading https://repo.packagist.org/p2/dompdf/dompdf.json: error setting certificate file: C:\xampp\apache\bin\curl-ca-bundle.crt 

//solo ve a la carpeta de php y buscar tu archivo.ini
--configurar bien las rutas siguientes

-curl.cainfo="C:\xam\apache\bin\curl-ca-bundle.crt" - mala ruta
-curl.cainfo="C:\xamppp\apache\bin\curl-ca-bundle.crt" - buena ruta

-openssl.cafile="C:\xam\apache\bin\curl-ca-bundle.crt" - mala ruta
-openssl.cafile="C:\xamppp\apache\bin\curl-ca-bundle.crt" - buena ruta

//en dado caso de tener otro error puesdes optar por esto
--composer config --global disable-tls true 

//despues debes desactivarlo
--composer config --global disable-tls false


//Bueno por si no jala el java o algo asi
--Remove-Item -Path C:\xamppp\htdocs\ProyectoQR\appceit\JavaBarcode\bin\* -Recurse -Force

usamos ese comando para borrar cualquier archivo class de la carpeta bin del proyecto, ojo si ya tines otros archivos en la carpeta se borraran igual, bueno lo haces y queda limpio, luego deberas usar

--javac -encoding UTF-8 -cp ".;lib/*" src/BarcodeGeneratorApp.java -d bin

para crear el archivo class de tu java, porque el UTF-8? es la codificacion de caracteres que usas en tu proyecto, si no lo usas no importa, pero si lo usas debes usar esa codificacion. Y asi ya estaria listo el proyecto para que funcione la genracion de Codigods de barras

Otra cosa importante, digo no creo que pase nada pero las librerias que se usan en el proyecto deben estar en la carpeta lib del proyecto, porque si no lo haces el proyecto no funcionara, asi que si no tienes las librerias en la carpeta lib del proyecto, debes descargarlas y ponerlas en la carpeta lib del proyecto
Son
ZXing: core-3.5.3.jar
ZXing: javase-3.5.3.jar
MySQL: mysql-connector-java-8.4.0.jar o 9.3.0.jar


