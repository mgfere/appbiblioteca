document.addEventListener("DOMContentLoaded", function () {
  let db;
  const request = indexedDB.open("LibrosDB", 1);

  request.onupgradeneeded = function (event) {
db = event.target.result;
const objectStore = db.createObjectStore("libros", { keyPath: "id" });

objectStore.createIndex("titulo", "titulo", { unique: false });
  };

  request.onsuccess = function (event) {
    db = event.target.result;
    sincronizarLibros();
  };

  request.onerror = function (event) {
    console.error("Error al inicializar IndexedDB:", event.target.errorCode);
  };

  function sincronizarLibros() {
    fetch("https://biblioteca.uttn.app/admin/obtener_libros.php")
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then((libros) => {
        const transaction = db.transaction("libros", "readwrite");
        const objectStore = transaction.objectStore("libros");
        objectStore.clear();
        libros.forEach((libro) => {
          objectStore.put(libro);
        });
      })
      .catch((error) => console.error("Error al cargar los libros de MySQL:", error));
  }

  const searchInput = document.getElementById("busqueda_bars-input");
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      const query = this.value.toLowerCase();
      if (query.trim() === "") {
        location.reload();
        return;
      }
      const transaction = db.transaction("libros", "readonly");
      const objectStore = transaction.objectStore("libros");
      const index = objectStore.index("titulo");

      const results = [];
      index.openCursor().onsuccess = function (event) {
        const cursor = event.target.result;
        if (cursor) {
          if (cursor.value.titulo.toLowerCase().includes(query)) {
            results.push(cursor.value);
          }
          cursor.continue();
        } else {
          displayResults(results);
        }
      };
    });

    searchInput.addEventListener("change", function () {
      if (this.value.trim() === "") {
        location.reload();
        return;
      }
    });
  }

  function displayResults(results) {
    const resultsList = document.getElementById("libros_contenedor");
    const mensajeContainer = document.querySelector(".mensaje-container");

    if (resultsList) {
      resultsList.innerHTML = "";

      if (results.length === 0 && !mensajeContainer) {
        const mensajeContainer = document.createElement("div");
        mensajeContainer.classList.add("container", "mensaje-container");

        const mensaje = document.createElement("p");
        mensaje.classList.add("mensaje");
        mensaje.textContent = "No se encontraron resultados";

        mensajeContainer.appendChild(mensaje);
        resultsList.parentElement.appendChild(mensajeContainer);
      } else if (results.length > 0 && mensajeContainer) {
        mensajeContainer.remove();
      } else {
        results.forEach((result) => {
          const libroItem = document.createElement("div");
          libroItem.classList.add("libro-item");

          const libroImg = document.createElement("div");
          libroImg.classList.add("libro-img");
          const img = document.createElement("img");
          img.src = "imagenes/" + result.imagen;
          img.alt = result.titulo;
          libroImg.appendChild(img);

          const libroInfo = document.createElement("div");
          libroInfo.classList.add("libro-info");
          const codigo = document.createElement("p");
          codigo.textContent = "#" + result.codigo;
          const h3 = document.createElement("h3");
          h3.textContent = result.titulo;
          const p = document.createElement("p");
          p.textContent = result.autor;

          libroInfo.appendChild(codigo);
          libroInfo.appendChild(h3);
          libroInfo.appendChild(p);

          libroItem.appendChild(libroImg);
          libroItem.appendChild(libroInfo);

          const a = document.createElement("a");
          a.href = "detalle-libro.php?id=" + result.id;
          a.title = result.titulo;
          a.appendChild(libroItem);

          resultsList.appendChild(a);
        });
      }
    } else {
      console.error("No se pudo encontrar el elemento con ID 'libros_contenedor'");
    }
  }
});
