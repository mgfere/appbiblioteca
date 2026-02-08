'use strict';

class Tabs {
  constructor(idElemento) {
    this.tabs = document.getElementById(idElemento);
    this.nav = this.tabs.querySelector(".tabs");

    this.nav.addEventListener("click", (e) => {
      // Comprobamos que el elemento que clickeamos tenga la clase de tabs__button
      if ([...e.target.classList].includes("tabs__button")) {
        // Obtenemos la tab que queremos mostrar
        const tab = e.target.dataset.tab;

        // Quitamos la clase active de algunas otras tabs que la tengan
        if (this.tabs.querySelector(".tab--active")) {
          this.tabs.querySelector(".tab--active").classList.remove("tab--active");
        }

        // Quitamos la clase active de algunas otros butones que la tengan
        if (this.tabs.querySelector(".tabs__button--active")) {
          this.tabs.querySelector(".tabs__button--active").classList.remove("tabs__button--active");
        }

        // Agregamos la clase active al tab
        this.tabs.querySelector(`#${tab}`).classList.add("tab--active");
        // Agregamos la clase active al boton
        e.target.classList.add("tabs__button--active");
      }
    });
  }
}

function mostrarPassword(id) {
  const passwordInput = document.getElementById(id);
  const toggleButton = passwordInput.parentElement.querySelector('.toggle-password');
  
  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    toggleButton.textContent = "Ocultar";
  } else {
    passwordInput.type = "password";
    toggleButton.textContent = "Mostrar";
  }
}

document.querySelectorAll("toggle-password", mostrarPassword);

new Tabs("mas-informacion");
