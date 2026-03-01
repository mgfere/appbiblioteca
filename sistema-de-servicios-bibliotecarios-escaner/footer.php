<?php
date_default_timezone_set('America/Mexico_City');
?>
<footer class="footer-uttn">
    <div class="container mx-auto px-6 py-6 text-center">
        <p class="footer-text mb-3">
            Innovación tecnológica impulsada por la Carrera de Tecnologías de la Información y el Departamento de Sistemas.
        </p>
        
        <div class="footer-divider"></div>
        
        <p class="footer-copyright">
            &copy; <?php echo date('Y'); ?> | Universidad Tecnológica de Tamaulipas Norte | 
            <a href="https://www.uttn.edu.mx/aviso-de-privacidad/" class="footer-link">Aviso de Privacidad</a>
        </p>
    </div>
</footer>

<style>


.footer-uttn {
    background: linear-gradient(135deg, #0e8c73 0%, #1ab192 50%, #0e7c66 100%);
    color: white;
    margin-top: auto;
    box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.1);
}

.footer-text {
    font-size: 1.05rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.95);
    line-height: 1.6;
    letter-spacing: 0.3px;
}

.footer-divider {
    height: 1px;
    background: linear-gradient(
        to right, 
        transparent, 
        rgba(255, 255, 255, 0.4) 20%, 
        rgba(255, 255, 255, 0.4) 80%, 
        transparent
    );
    margin: 1rem auto;
    max-width: 600px;
}

.footer-copyright {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 400;
}

.footer-link {
    color: rgba(255, 255, 255, 0.95);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border-bottom: 1px solid transparent;
}

.footer-link:hover {
    color: #ffd700;
    border-bottom-color: #ffd700;
}

/* Responsive */
@media (max-width: 640px) {
    .footer-text {
        font-size: 0.95rem;
    }
    
    .footer-copyright {
        font-size: 0.85rem;
        line-height: 1.6;
    }
}
</style>