document.querySelectorAll('.accordion-header').forEach((header) => {
    header.addEventListener('click', () => {
      const body = header.nextElementSibling;
      const icon = header.querySelector('.accordion-icon');
      const isActive = header.classList.contains('active');
  
      // Bütün digər akordeonları bağlamaq
      document.querySelectorAll('.accordion-body').forEach((b) => b.classList.remove('show'));
      document.querySelectorAll('.accordion-header').forEach((h) => h.classList.remove('active'));
  
      // Aktivləşdirmək/dəaktivləşdirmək
      if (!isActive) {
        header.classList.add('active');
        body.classList.add('show');
      }
    });
  });