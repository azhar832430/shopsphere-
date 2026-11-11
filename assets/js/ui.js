/**
 * SHOPSPHERE - UI COMPONENT & INTERACTION HELPER
 * Modals, Toasts, Dropdowns, Sidebar Drawer, Formatters, Pagination
 */

const UI = {
  /**
   * Initializes all shared shell components (Sidebar, Topbar, Dropdowns, Theme)
   */
  initShell() {
    this.initSidebar();
    this.initHeader();
    this.initTheme();
    this.setupGlobalSearch();
    this.setupToastContainer();
    this.checkAdminAuth();
  },

  /**
   * Safe HTML escaping helper to prevent XSS injection
   * @param {string|any} str 
   * @returns {string} Escaped string safe for innerHTML insertion
   */
  escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
  },

  /**
   * Session authentication guard for protected administrative dashboard views.
   * If backend is active and session is unauthenticated, redirects to index.html with warning.
   */
  async checkAdminAuth() {
    const path = window.location.pathname.toLowerCase();
    const isLoginPage = path.endsWith('index.html') || path.endsWith('/') || path.endsWith('/shopsphere') || path.endsWith('/shopsphere/');
    if (isLoginPage) return;

    if (window.API && typeof API.auth?.me === 'function') {
      try {
        const res = await API.auth.me();
        if (res && !res.success && res.errors?.auth === 'Unauthenticated') {
          console.warn('[Auth Guard] Unauthorized access detected, redirecting to login.');
          sessionStorage.setItem('shopsphere_redirect_after_login', window.location.href);
          window.location.href = 'index.html?unauthorized=1';
          return;
        }
        if (res && res.success && res.data?.user) {
          const nameEl = document.querySelector('.user-name');
          const roleEl = document.querySelector('.user-role');
          if (nameEl && res.data.user.full_name) nameEl.textContent = res.data.user.full_name;
          if (roleEl && res.data.user.role) roleEl.textContent = res.data.user.role;
        }
      } catch (err) {
        console.info('[Auth Guard] Operating under offline/mock mode.');
      }
    }
  },

  /**
   * Setup Toast Container in DOM if not present
   */
  setupToastContainer() {
    if (!document.getElementById('toastContainer')) {
      const container = document.createElement('div');
      container.id = 'toastContainer';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
  },

  /**
   * Show animated Toast Notification
   * @param {string} message 
   * @param {'success'|'error'|'warning'|'info'} type 
   * @param {string} [title] 
   * @param {number} [duration=4000] 
   */
  showToast(message, type = 'info', title = null, duration = 4000) {
    this.setupToastContainer();
    const container = document.getElementById('toastContainer');

    const icons = {
      success: 'fa-solid fa-circle-check',
      error: 'fa-solid fa-triangle-exclamation',
      warning: 'fa-solid fa-circle-exclamation',
      info: 'fa-solid fa-circle-info'
    };

    const titles = {
      success: 'Success',
      error: 'Error Notice',
      warning: 'Warning',
      info: 'Information'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <div class="toast-icon"><i class="${icons[type] || icons.info}"></i></div>
      <div class="toast-content">
        <div class="toast-title">${title || titles[type]}</div>
        <div class="toast-message">${message}</div>
      </div>
      <button type="button" class="toast-close" aria-label="Close Notification">&times;</button>
      <div class="toast-progress"></div>
    `;

    container.appendChild(toast);

    // Trigger animate in
    requestAnimationFrame(() => {
      toast.classList.add('show');
    });

    const closeToast = () => {
      toast.classList.remove('show');
      setTimeout(() => {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 350);
    };

    toast.querySelector('.toast-close').addEventListener('click', closeToast);

    // Auto dismiss
    const timer = setTimeout(closeToast, duration);
    toast.addEventListener('mouseenter', () => clearTimeout(timer));
  },

  /**
   * Open Modal by ID
   */
  openModal(modalId) {
    const backdrop = document.getElementById(modalId);
    if (!backdrop) return;
    backdrop.classList.add('show');
    document.body.style.overflow = 'hidden';

    // Auto-focus first input if any
    const firstInput = backdrop.querySelector('input:not([type="hidden"]), select, textarea');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 150);
    }
  },

  /**
   * Close Modal by ID
   */
  closeModal(modalId) {
    const backdrop = document.getElementById(modalId);
    if (!backdrop) return;
    backdrop.classList.remove('show');
    document.body.style.overflow = '';
  },

  /**
   * Generic Confirmation Modal Dialog
   */
  confirmModal({ title = 'Confirm Action', message = 'Are you sure you want to proceed?', confirmText = 'Confirm', cancelText = 'Cancel', confirmClass = 'btn-danger', onConfirm }) {
    let confirmBackdrop = document.getElementById('globalConfirmModal');
    if (!confirmBackdrop) {
      confirmBackdrop = document.createElement('div');
      confirmBackdrop.id = 'globalConfirmModal';
      confirmBackdrop.className = 'modal-backdrop';
      confirmBackdrop.innerHTML = `
        <div class="modal modal-sm" role="dialog" aria-modal="true">
          <div class="modal-header">
            <h3 class="modal-title" id="confirmModalTitle"></h3>
            <button type="button" class="modal-close" data-action="cancel">&times;</button>
          </div>
          <div class="modal-body">
            <p id="confirmModalMessage" class="text-secondary" style="font-size:0.9375rem;line-height:1.5;"></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="cancel" id="confirmCancelBtn"></button>
            <button type="button" class="btn" id="confirmActionBtn"></button>
          </div>
        </div>
      `;
      document.body.appendChild(confirmBackdrop);
    }

    document.getElementById('confirmModalTitle').textContent = title;
    document.getElementById('confirmModalMessage').textContent = message;

    const cancelBtn = document.getElementById('confirmCancelBtn');
    cancelBtn.textContent = cancelText;

    const actionBtn = document.getElementById('confirmActionBtn');
    actionBtn.className = `btn ${confirmClass}`;
    actionBtn.textContent = confirmText;

    const handleConfirm = () => {
      cleanup();
      UI.closeModal('globalConfirmModal');
      if (typeof onConfirm === 'function') onConfirm();
    };

    const handleCancel = () => {
      cleanup();
      UI.closeModal('globalConfirmModal');
    };

    const cleanup = () => {
      actionBtn.removeEventListener('click', handleConfirm);
      cancelBtn.removeEventListener('click', handleCancel);
      confirmBackdrop.querySelectorAll('[data-action="cancel"]').forEach(btn => {
        btn.removeEventListener('click', handleCancel);
      });
    };

    actionBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', handleCancel);
    confirmBackdrop.querySelectorAll('[data-action="cancel"]').forEach(btn => {
      btn.addEventListener('click', handleCancel);
    });

    UI.openModal('globalConfirmModal');
  },

  /**
   * Currency Formatter
   */
  formatCurrency(amount, symbol = '$') {
    const num = parseFloat(amount || 0);
    return symbol + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  },

  /**
   * Date Formatter
   */
  formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  },

  /**
   * Renders modern badge HTML based on status
   */
  renderBadge(status) {
    const s = String(status || '').toLowerCase().trim();
    let badgeClass = 'badge-neutral';
    let label = status;

    switch (s) {
      case 'active':
      case 'paid':
      case 'delivered':
      case 'in stock':
        badgeClass = 'badge-success';
        break;
      case 'processing':
      case 'shipped':
      case 'pending':
        badgeClass = 'badge-info';
        break;
      case 'low stock':
      case 'warning':
      case 'inactive':
        badgeClass = 'badge-warning';
        break;
      case 'out of stock':
      case 'cancelled':
      case 'refunded':
      case 'failed':
      case 'blocked':
        badgeClass = 'badge-danger';
        break;
    }

    return `<span class="badge ${badgeClass}"><span class="badge-dot"></span>${label}</span>`;
  },

  /**
   * Sidebar Navigation Logic (Collapsible on Desktop, Off-canvas Drawer on Mobile)
   */
  initSidebar() {
    const sidebar = document.querySelector('.app-sidebar');
    const desktopToggle = document.getElementById('desktopSidebarToggle');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const backdrop = document.getElementById('sidebarBackdrop');

    // Restore desktop collapsed preference
    const isCollapsed = localStorage.getItem('shopsphere_sidebar_collapsed') === 'true';
    if (isCollapsed) {
      document.body.classList.add('sidebar-collapsed');
    }

    if (desktopToggle) {
      desktopToggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-collapsed');
        const collapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem('shopsphere_sidebar_collapsed', collapsed);
      });
    }

    if (mobileMenuBtn && sidebar && backdrop) {
      mobileMenuBtn.addEventListener('click', () => {
        sidebar.classList.add('show-mobile');
        backdrop.classList.add('show');
      });

      const closeMobileSidebar = () => {
        sidebar.classList.remove('show-mobile');
        backdrop.classList.remove('show');
      };

      backdrop.addEventListener('click', closeMobileSidebar);

      // Close when clicking nav link on mobile
      sidebar.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
          if (window.innerWidth <= 1024) closeMobileSidebar();
        });
      });
    }

    // Mark current active link
    this.setActiveNavLink();
  },

  /**
   * Sets active navigation highlighting based on current page
   */
  setActiveNavLink() {
    const currentPath = window.location.pathname.split('/').pop() || 'dashboard.html';
    const navItems = document.querySelectorAll('.nav-item');

    navItems.forEach(item => {
      const link = item.querySelector('.nav-link');
      if (link) {
        const href = link.getAttribute('href');
        if (href === currentPath || (currentPath === 'index.html' && href === 'dashboard.html')) {
          item.classList.add('active');
        } else {
          item.classList.remove('active');
        }
      }
    });
  },

  /**
   * Topbar Dropdowns & Actions
   */
  initHeader() {
    // Dropdown toggles
    const dropdownTriggers = document.querySelectorAll('[data-toggle="dropdown"]');

    dropdownTriggers.forEach(trigger => {
      trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const targetId = trigger.getAttribute('data-target');
        const menu = targetId ? document.getElementById(targetId) : trigger.nextElementSibling;

        // Close other dropdowns
        document.querySelectorAll('.dropdown-menu.show').forEach(m => {
          if (m !== menu) m.classList.remove('show');
        });

        if (menu) {
          menu.classList.toggle('show');
        }
      });
    });

    // Close dropdowns on outside click
    document.addEventListener('click', () => {
      document.querySelectorAll('.dropdown-menu.show').forEach(m => {
        m.classList.remove('show');
      });
    });

    // Quick Logout Button
    const logoutButtons = document.querySelectorAll('.action-logout');
    logoutButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        UI.confirmModal({
          title: 'Sign Out Admin',
          message: 'Are you sure you want to end your current ShopSphere administrative session?',
          confirmText: 'Sign Out',
          confirmClass: 'btn-danger',
          onConfirm: async () => {
            if (window.API && typeof API.auth?.logout === 'function') {
              try { await API.auth.logout(); } catch (err) {}
            }
            UI.showToast('Signed out successfully. Redirecting...', 'info');
            setTimeout(() => {
              window.location.href = 'index.html';
            }, 600);
          }
        });
      });
    });
  },

  /**
   * Theme Manager (Dark/Light mode toggle)
   */
  initTheme() {
    const currentTheme = localStorage.getItem('shopsphere_theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);

    const themeToggleBtn = document.getElementById('themeToggleBtn');
    if (themeToggleBtn) {
      this.updateThemeIcon(themeToggleBtn, currentTheme);
      themeToggleBtn.addEventListener('click', () => {
        const active = document.documentElement.getAttribute('data-theme');
        const nextTheme = active === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', nextTheme);
        localStorage.setItem('shopsphere_theme', nextTheme);
        this.updateThemeIcon(themeToggleBtn, nextTheme);
        UI.showToast(`Switched to ${nextTheme} theme mode`, 'info');
      });
    }
  },

  updateThemeIcon(btn, theme) {
    const icon = btn.querySelector('i');
    if (icon) {
      icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }
  },

  /**
   * Setup Global Header Search Shortcut
   */
  setupGlobalSearch() {
    const searchInput = document.getElementById('globalSearchInput');
    if (!searchInput) return;

    // Shortcut Ctrl+K / Cmd+K
    window.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        searchInput.focus();
      }
    });

    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const query = searchInput.value.trim();
        if (query) {
          // If already on products page, filter; otherwise navigate
          if (window.location.pathname.includes('products.html')) {
            const prodSearch = document.getElementById('productSearchInput');
            if (prodSearch) {
              prodSearch.value = query;
              prodSearch.dispatchEvent(new Event('input'));
            }
          } else {
            window.location.href = `products.html?search=${encodeURIComponent(query)}`;
          }
        }
      }
    });
  },

  /**
   * Render Pagination Component
   */
  renderPagination({ containerId, currentPage, totalPages, totalItems, limit, onPageChange }) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (totalPages <= 1) {
      container.innerHTML = `
        <div class="text-muted" style="font-size:0.8125rem;">Showing ${totalItems} ${totalItems === 1 ? 'record' : 'records'}</div>
      `;
      return;
    }

    const startItem = (currentPage - 1) * limit + 1;
    const endItem = Math.min(currentPage * limit, totalItems);

    let pagesHtml = '';

    // Prev Button
    pagesHtml += `
      <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
          <i class="fa-solid fa-chevron-left"></i>
        </a>
      </li>
    `;

    // Page Numbers
    for (let p = 1; p <= totalPages; p++) {
      if (p === 1 || p === totalPages || (p >= currentPage - 1 && p <= currentPage + 1)) {
        pagesHtml += `
          <li class="page-item ${p === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" data-page="${p}">${p}</a>
          </li>
        `;
      } else if (p === currentPage - 2 || p === currentPage + 2) {
        pagesHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
      }
    }

    // Next Button
    pagesHtml += `
      <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
          <i class="fa-solid fa-chevron-right"></i>
        </a>
      </li>
    `;

    container.innerHTML = `
      <div class="text-muted" style="font-size:0.8125rem;">
        Showing <span class="fw-bold text-dark">${startItem}-${endItem}</span> of <span class="fw-bold text-dark">${totalItems}</span>
      </div>
      <ul class="pagination">
        ${pagesHtml}
      </ul>
    `;

    // Bind page click events
    container.querySelectorAll('.page-link[data-page]').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const page = parseInt(link.getAttribute('data-page'), 10);
        if (page >= 1 && page <= totalPages && page !== currentPage) {
          onPageChange(page);
        }
      });
    });
  }
};

// Global assignment
window.UI = UI;
window.escapeHtml = UI.escapeHtml.bind(UI);

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  UI.initShell();
});

