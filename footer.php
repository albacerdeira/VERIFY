</main> <!-- Fecha a tag <main> aberta no header.php -->
    </div> <!-- Fecha a tag <div> aberta no header.php -->
    
    <!-- Script para Toggle da Sidebar em Mobile e Funcionalidades de Colapso -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
        
        // Toggle Mobile
        if (sidebar && sidebarToggle && sidebarOverlay) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            });
            
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
        }
        
        // Colapso da Sidebar (Desktop)
        if (sidebarCollapseBtn && sidebar) {
            // Restaura estado do localStorage
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            }
            
            sidebarCollapseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
        }
        
        // Seções colapsáveis do menu
        const sectionHeaders = document.querySelectorAll('.sidebar-section-header');
        sectionHeaders.forEach(header => {
            header.addEventListener('click', function() {
                const section = this.parentElement;
                const wasOpen = section.classList.contains('open');
                
                // Fecha todas as outras seções
                document.querySelectorAll('.sidebar-section').forEach(s => {
                    if (s !== section) s.classList.remove('open');
                });
                
                // Toggle da seção atual
                section.classList.toggle('open');
                
                // Salva estado no localStorage
                const sectionId = Array.from(section.parentElement.children).indexOf(section);
                if (!wasOpen) {
                    localStorage.setItem('openSection', sectionId);
                } else {
                    localStorage.removeItem('openSection');
                }
            });
        });
        
        // Restaura seção aberta
        const openSectionId = localStorage.getItem('openSection');
        if (openSectionId !== null) {
            const sections = document.querySelectorAll('.sidebar-section');
            if (sections[openSectionId]) {
                sections[openSectionId].classList.add('open');
            }
        }
        
        // Auto-abre seção que contém link ativo
        const activeLink = document.querySelector('.sidebar-nav-link.active');
        if (activeLink) {
            const parentSection = activeLink.closest('.sidebar-section');
            if (parentSection) {
                parentSection.classList.add('open');
            }
        }
    });
    </script>
</body>
</html>