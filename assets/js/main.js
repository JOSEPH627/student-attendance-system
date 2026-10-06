document.addEventListener('DOMContentLoaded', function () {

    const menuToggle = document.getElementById('mobileMenuToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const dashboardLayout = document.querySelector('.dashboard-layout');

    if (!menuToggle || !dashboardLayout) {
        return;
    }

    function openSidebar() {
        dashboardLayout.classList.add('sidebar-open');

        menuToggle.setAttribute('aria-expanded', 'true');

        if (sidebarOverlay) {
            sidebarOverlay.setAttribute('aria-hidden', 'false');
        }

        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        dashboardLayout.classList.remove('sidebar-open');

        menuToggle.setAttribute('aria-expanded', 'false');

        if (sidebarOverlay) {
            sidebarOverlay.setAttribute('aria-hidden', 'true');
        }

        document.body.style.overflow = '';
    }

    menuToggle.addEventListener('click', function () {
        if (dashboardLayout.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    if (sidebarClose) {
        sidebarClose.addEventListener('click', function () {
            closeSidebar();
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            closeSidebar();
        });
    }

    const navigationLinks = document.querySelectorAll(
        '.sidebar-navigation a'
    );

    navigationLinks.forEach(function (link) {

        link.addEventListener('click', function () {

            if (window.innerWidth <= 750) {
                closeSidebar();
            }

        });

    });

    document.addEventListener('keydown', function (event) {

        if (
            event.key === 'Escape' &&
            dashboardLayout.classList.contains('sidebar-open')
        ) {
            closeSidebar();
        }

    });

    window.addEventListener('resize', function () {

        if (window.innerWidth > 750) {
            closeSidebar();
        }

    });

});