        </main>
    </div>

    <!-- Admin general scripts -->
    <script>
    function toggleAdminSidebar(e) {
        if (e) e.stopPropagation();
        const sidebar = document.getElementById('adminSidebar');
        sidebar.classList.toggle('active');
    }
    
    document.addEventListener('click', (e) => {
        const sidebar = document.getElementById('adminSidebar');
        const toggleBtn = document.getElementById('mobileSidebarToggle');
        
        if (sidebar && sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && (!toggleBtn || !toggleBtn.contains(e.target))) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    function checkAdminWidth() {
        if (window.innerWidth > 992) {
            const sidebar = document.getElementById('adminSidebar');
            if (sidebar) sidebar.classList.remove('active');
        }
    }
    window.addEventListener('resize', checkAdminWidth);
    </script>
</body>
</html>
