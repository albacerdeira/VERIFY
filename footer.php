</main> <!-- Fecha a tag <main> aberta no header.php -->
    </div> <!-- Fecha a tag <div> aberta no header.php -->
    
    <!-- Script para Toggle da Sidebar em Mobile -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
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
    });
    </script>
</body>
</html>