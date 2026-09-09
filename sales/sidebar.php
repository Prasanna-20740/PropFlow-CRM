<?php
/**
 * PropFlow CRM - Unified Sales Sidebar
 * Include this file in every sales page for a consistent sidebar.
 *
 * Required before including:
 *   $activePage = 'dashboard' | 'leads' | 'pipeline' | 'bookings' | 'profile'
 */

$activePage = $activePage ?? '';
?>

<!-- =========================================================
     SIDEBAR CSS (Unified)
     ========================================================= -->

<style>

/* ----- Sidebar ----- */

.pf-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 272px;
    background: #0b1324;
    color: #fff;
    padding: 0;
    z-index: 50;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    transition: width .25s cubic-bezier(.4,0,.2,1);
}

/* Scrollbar */
.pf-sidebar::-webkit-scrollbar { width: 4px; }
.pf-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,.1);
    border-radius: 4px;
}

/* ----- Brand ----- */

.pf-sidebar .pf-brand {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 26px 22px 30px;
    flex-shrink: 0;
}

.pf-sidebar .pf-brand-logo {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(37,99,235,.35);
}

.pf-sidebar .pf-brand-name {
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -.3px;
    white-space: nowrap;
    background: linear-gradient(135deg, #e0eaff, #fff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ----- Section Title ----- */

.pf-sidebar .pf-nav-title {
    color: #5c6a82;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    padding: 4px 26px 12px;
    flex-shrink: 0;
}

/* ----- Nav Items ----- */

.pf-sidebar .pf-nav {
    flex: 1 1 auto;
    padding: 0 14px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.pf-sidebar .pf-nav-item {
    display: flex;
    align-items: center;
    gap: 13px;
    height: 44px;
    padding: 0 14px;
    border-radius: 10px;
    color: #8d99b0;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    position: relative;
    transition:
        background .2s cubic-bezier(.4,0,.2,1),
        color .2s cubic-bezier(.4,0,.2,1),
        transform .15s ease;
    cursor: pointer;
}

.pf-sidebar .pf-nav-item:hover {
    background: rgba(255,255,255,.06);
    color: #d0d7e5;
    transform: translateX(2px);
}

.pf-sidebar .pf-nav-item.active {
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    color: #fff;
    font-weight: 650;
    box-shadow: 0 4px 15px rgba(37,99,235,.3);
}

.pf-sidebar .pf-nav-item.active:hover {
    transform: none;
}

.pf-sidebar .pf-nav-icon {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pf-sidebar .pf-nav-icon svg {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.pf-sidebar .pf-nav-label {
    white-space: nowrap;
}

/* ----- Sidebar Bottom (Logout) ----- */

.pf-sidebar .pf-nav-bottom {
    flex-shrink: 0;
    padding: 16px 14px 22px;
    margin-top: auto;
    border-top: 1px solid rgba(255,255,255,.07);
}

.pf-sidebar .pf-nav-bottom .pf-nav-item {
    color: #7a8599;
}

.pf-sidebar .pf-nav-bottom .pf-nav-item:hover {
    background: rgba(239,68,68,.08);
    color: #f87171;
}

/* ----- Mobile Toggle ----- */

.pf-sidebar-toggle {
    display: none;
    position: fixed;
    top: 18px;
    left: 16px;
    z-index: 60;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 1px solid #e1e7f0;
    background: #fff;
    color: #344054;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    transition: .2s;
}

.pf-sidebar-toggle svg {
    width: 20px;
    height: 20px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.pf-sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.35);
    z-index: 40;
    backdrop-filter: blur(2px);
}

/* ----- Responsive ----- */

@media (max-width: 1024px) {

    .pf-sidebar {
        width: 272px;
        transform: translateX(-100%);
        transition:
            transform .3s cubic-bezier(.4,0,.2,1);
    }

    .pf-sidebar.open {
        transform: translateX(0);
    }

    .pf-sidebar-toggle {
        display: flex;
    }

    .pf-sidebar-overlay.open {
        display: block;
    }
}

</style>

<!-- =========================================================
     SIDEBAR HTML (Unified)
     ========================================================= -->

<!-- Mobile Toggle -->
<button
    class="pf-sidebar-toggle"
    id="pfSidebarToggle"
    aria-label="Toggle sidebar"
    type="button"
>
    <svg viewBox="0 0 24 24">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
    </svg>
</button>

<!-- Overlay -->
<div class="pf-sidebar-overlay" id="pfSidebarOverlay"></div>

<!-- Sidebar -->
<aside class="pf-sidebar" id="pfSidebar">

    <!-- Brand -->
    <div class="pf-brand">
        <div class="pf-brand-logo">P</div>
        <div class="pf-brand-name">PropFlow CRM</div>
    </div>

    <!-- Section Title -->
    <div class="pf-nav-title">SALES WORKSPACE</div>

    <!-- Navigation -->
    <nav class="pf-nav">

        <!-- Dashboard -->
        <a
            href="dashboard.php"
            class="pf-nav-item<?= $activePage === 'dashboard' ? ' active' : '' ?>"
        >
            <span class="pf-nav-icon">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                </svg>
            </span>
            <span class="pf-nav-label">Dashboard</span>
        </a>

        <!-- My Leads -->
        <a
            href="leads.php"
            class="pf-nav-item<?= $activePage === 'leads' ? ' active' : '' ?>"
        >
            <span class="pf-nav-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </span>
            <span class="pf-nav-label">My Leads</span>
        </a>

        <!-- Pipeline -->
        <a
            href="pipeline.php"
            class="pf-nav-item<?= $activePage === 'pipeline' ? ' active' : '' ?>"
        >
            <span class="pf-nav-icon">
                <svg viewBox="0 0 24 24">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
            </span>
            <span class="pf-nav-label">Pipeline</span>
        </a>

        <!-- Bookings -->
        <a
            href="bookings.php"
            class="pf-nav-item<?= $activePage === 'bookings' ? ' active' : '' ?>"
        >
            <span class="pf-nav-icon">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </span>
            <span class="pf-nav-label">Bookings</span>
        </a>

        <!-- Profile -->
        <a
            href="profile.php"
            class="pf-nav-item<?= $activePage === 'profile' ? ' active' : '' ?>"
        >
            <span class="pf-nav-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </span>
            <span class="pf-nav-label">Profile</span>
        </a>

    </nav>

    <!-- Logout -->
    <div class="pf-nav-bottom">
        <a href="../logout.php" class="pf-nav-item">
            <span class="pf-nav-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </span>
            <span class="pf-nav-label">Logout</span>
        </a>
    </div>

</aside>

<!-- Sidebar Toggle Script -->
<script>
(function(){
    var btn = document.getElementById('pfSidebarToggle');
    var sidebar = document.getElementById('pfSidebar');
    var overlay = document.getElementById('pfSidebarOverlay');
    if(!btn||!sidebar||!overlay) return;
    function toggle(){
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');
    }
    btn.addEventListener('click', toggle);
    overlay.addEventListener('click', toggle);
})();
</script>
