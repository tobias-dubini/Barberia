  </main>

  <footer style="text-align: center; padding: 0.65rem 0; color: var(--text-secondary); font-size: 0.8rem; border-top: 1px solid var(--glass-border); margin-top: 0.5rem;">
    <div class="container">
      ✂️ Brotherhood Barbershop &bull; Todos los derechos reservados
    </div>
  </footer>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const preloader = document.getElementById('site-preloader');
      if (preloader) {
        const hidePreloader = () => {
          preloader.classList.add('preloader-hidden');
          setTimeout(() => {
            preloader.style.display = 'none';
          }, 700);
        };

        if (document.readyState === 'complete') {
          setTimeout(hidePreloader, 400);
        } else {
          window.addEventListener('load', () => setTimeout(hidePreloader, 400));
          // Fallback de 1.4s para evitar demoras
          setTimeout(hidePreloader, 1400);
        }
      }
    });
  </script>
</body>
</html>
