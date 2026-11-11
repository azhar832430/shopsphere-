/**
 * SHOPSPHERE - CUSTOMERS MANAGEMENT CONTROLLER
 * Phase 5: Complete Customer Management CRUD
 *
 * Connected directly to MySQL via PHP Backend APIs:
 * - GET  api/customers/index.php (Server pagination, filtering, search, sorting)
 * - GET  api/customers/show.php (Single customer dossier & order history)
 * - POST api/customers/store.php (Create customer with unique email validation)
 * - POST api/customers/update.php (Update customer details with safe email uniqueness)
 * - POST api/customers/delete.php (Delete or archive if referenced in orders)
 * - Graceful fallback to Store if backend is unreachable
 */

document.addEventListener('DOMContentLoaded', async () => {
  if (!document.getElementById('customersTableBody')) return;

  // State
  let currentSettings = { currencySymbol: '$' };
  let isApiOnline = true;
  let availableCities = [];

  const state = {
    search: '',
    city: 'all',
    status: 'all',
    sortBy: 'newest',
    page: 1,
    limit: 6,
    totalPages: 1,
    totalItems: 0
  };

  // Sort mapping from UI dropdown to API query params
  const sortMapping = {
    newest:      { sort: 'id',           order: 'DESC' },
    name_asc:    { sort: 'full_name',    order: 'ASC' },
    name_desc:   { sort: 'full_name',    order: 'DESC' },
    orders_high: { sort: 'total_orders', order: 'DESC' },
    spent_high:  { sort: 'total_spent',  order: 'DESC' }
  };

  // DOM Elements
  const tableBody = document.getElementById('customersTableBody');
  const searchInput = document.getElementById('customerSearchInput');
  const cityFilter = document.getElementById('customerCityFilter');
  const statusFilter = document.getElementById('customerStatusFilter');
  const sortSelect = document.getElementById('customerSortSelect');
  const customerForm = document.getElementById('customerForm');
  const modalTitle = document.getElementById('customerModalTitle');
  const saveCustomerBtn = document.getElementById('saveCustomerBtn');

  // Load Settings
  try {
    if (window.Store && typeof Store.getSettings === 'function') {
      currentSettings = await Store.getSettings();
    }
  } catch (err) {
    console.warn('Could not load local settings, using defaults.', err);
  }

  // Initial Load
  await loadCustomers();
  checkUrlParams();

  /**
   * Helper to generate avatar URL
   */
  function resolveCustomerAvatar(name) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'Customer')}&background=4F46E5&color=fff&bold=true`;
  }

  /**
   * Populate city filter dropdown
   */
  function populateCityDropdown(cities) {
    if (!cityFilter) return;
    const selected = cityFilter.value || 'all';

    cityFilter.innerHTML = '<option value="all">All Cities</option>' +
      cities.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');

    // Restore selected value if available
    cityFilter.value = cities.includes(selected) ? selected : 'all';
  }

  /**
   * Main Load & Render function with loading, error and empty states
   */
  async function loadCustomers() {
    renderLoadingState();

    const sortConfig = sortMapping[state.sortBy] || { sort: 'id', order: 'DESC' };
    const params = {
      page: state.page,
      limit: state.limit,
      search: state.search,
      city: state.city !== 'all' ? state.city : '',
      status: state.status !== 'all' ? state.status : '',
      sort: sortConfig.sort,
      order: sortConfig.order
    };

    try {
      const response = await API.customers.list(params);

      if (response && response.success && response.data) {
        isApiOnline = true;
        const { items, meta, cities } = response.data;

        // Sync cities dropdown
        if (Array.isArray(cities)) {
          availableCities = cities;
          populateCityDropdown(cities);
        }

        state.totalPages = meta?.total_pages || 1;
        state.totalItems = meta?.total_items || 0;
        state.page = meta?.page || state.page;

        renderCustomerTable(items);
        renderPaginationControls();
        return;
      }

      // Backend returned error
      renderErrorState(response?.message || 'Failed to load customer records from database.');

    } catch (apiError) {
      console.warn('[Customers] API fetch error, falling back to local store:', apiError);
      isApiOnline = false;
      fallbackLoadFromStore();
    }
  }

  /**
   * Render loading spinner placeholder in table
   */
  function renderLoadingState() {
    tableBody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state" style="padding: 3rem 1rem;">
            <div class="empty-state-icon" style="color: var(--primary-600); animation: spin 1s linear infinite;">
              <i class="fa-solid fa-spinner"></i>
            </div>
            <h4 class="empty-state-title" style="margin-top: 1rem;">Loading Customers...</h4>
            <p class="empty-state-desc">Retrieving customer directory and transaction totals from MySQL.</p>
          </div>
        </td>
      </tr>
    `;
  }

  /**
   * Render error state in table
   */
  function renderErrorState(errorMessage) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state" style="padding: 2.5rem 1rem;">
            <div class="empty-state-icon" style="color: var(--danger-500);"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <h4 class="empty-state-title">Database Communication Error</h4>
            <p class="empty-state-desc">${escapeHtml(errorMessage)}</p>
            <button class="btn btn-primary btn-sm" id="retryCustomerLoadBtn">
              <i class="fa-solid fa-rotate"></i> Retry Connection
            </button>
          </div>
        </td>
      </tr>
    `;
    const retryBtn = document.getElementById('retryCustomerLoadBtn');
    if (retryBtn) retryBtn.addEventListener('click', loadCustomers);
  }

  /**
   * Render customer rows
   */
  function renderCustomerTable(items) {
    if (!items || items.length === 0) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="7">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fa-solid fa-users"></i></div>
              <h4 class="empty-state-title">No customers found</h4>
              <p class="empty-state-desc">Try clearing your search/filters or register a new customer profile.</p>
              <button class="btn btn-primary btn-sm" id="emptyAddCustBtn">
                <i class="fa-solid fa-user-plus"></i> Add Customer
              </button>
            </div>
          </td>
        </tr>
      `;
      const emptyBtn = document.getElementById('emptyAddCustBtn');
      if (emptyBtn) emptyBtn.addEventListener('click', openAddCustomerModal);
      return;
    }

    tableBody.innerHTML = items.map(cust => {
      const name = cust.full_name || `${cust.firstName || ''} ${cust.lastName || ''}`.trim() || 'Unknown';
      const email = cust.email || '';
      const phone = cust.phone || '—';
      const city = cust.city || '';
      const postal = cust.postal_code || cust.postalCode || '';
      const location = city ? (postal ? `${city}, ${postal}` : city) : '—';
      const orders = cust.total_orders ?? (cust.orderCount || 0);
      const spent = parseFloat(cust.total_spent ?? cust.totalSpent ?? 0);
      const status = cust.status || 'active';
      const avatar = resolveCustomerAvatar(name);

      return `
        <tr>
          <td>
            <div class="table-user-cell">
              <img src="${escapeHtml(avatar)}" alt="${escapeHtml(name)}" class="sidebar-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=4F46E5&color=fff'">
              <div class="table-user-info">
                <span class="table-primary-text">${escapeHtml(name)}</span>
                <span class="table-secondary-text"><i class="fa-regular fa-envelope me-1"></i>${escapeHtml(email)}</span>
              </div>
            </div>
          </td>
          <td><span class="text-secondary" style="font-size:0.8125rem;">${escapeHtml(phone)}</span></td>
          <td><span class="text-secondary" style="font-size:0.8125rem;">${escapeHtml(location)}</span></td>
          <td>
            <span class="badge ${orders > 0 ? 'badge-info' : 'badge-neutral'}">
              ${orders} ${orders === 1 ? 'order' : 'orders'}
            </span>
          </td>
          <td class="fw-bold">${UI.formatCurrency(spent, currentSettings.currencySymbol)}</td>
          <td>${UI.renderBadge(status)}</td>
          <td>
            <div class="action-buttons">
              <button type="button" class="action-btn action-view" data-action="view" data-id="${cust.id}" title="View Dossier & Orders">
                <i class="fa-solid fa-eye"></i>
              </button>
              <button type="button" class="action-btn action-edit" data-action="edit" data-id="${cust.id}" title="Edit Profile">
                <i class="fa-solid fa-pen"></i>
              </button>
              <button type="button" class="action-btn action-delete" data-action="delete" data-id="${cust.id}" data-name="${escapeHtml(name)}" title="Delete Customer">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');

    bindTableEvents();
  }

  /**
   * Render pagination controls
   */
  function renderPaginationControls() {
    UI.renderPagination({
      containerId: 'customersPagination',
      currentPage: state.page,
      totalPages: state.totalPages,
      totalItems: state.totalItems,
      limit: state.limit,
      onPageChange: (newPage) => {
        state.page = newPage;
        loadCustomers();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  /**
   * Bind row action events
   */
  function bindTableEvents() {
    tableBody.querySelectorAll('[data-action="view"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        openViewCustomerModal(id);
      });
    });

    tableBody.querySelectorAll('[data-action="edit"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        openEditCustomerModal(id);
      });
    });

    tableBody.querySelectorAll('[data-action="delete"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const name = btn.getAttribute('data-name');
        confirmDeleteCustomer(id, name);
      });
    });
  }

  /**
   * Add Customer Form
   */
  function openAddCustomerModal() {
    customerForm.reset();
    document.getElementById('customerId').value = '';
    modalTitle.textContent = 'Add New Customer';
    clearValidationErrors(customerForm);
    UI.openModal('customerModal');
  }

  /**
   * Edit Customer Form (Fetch from API show.php)
   */
  async function openEditCustomerModal(id) {
    let cust = null;

    try {
      const res = await API.customers.get(id);
      if (res && res.success && res.data?.customer) {
        cust = res.data.customer;
      }
    } catch (e) {
      console.warn('API get customer error, falling back to Store:', e);
    }

    if (!cust && window.Store && typeof Store.getCustomerById === 'function') {
      cust = await Store.getCustomerById(id);
    }

    if (!cust) {
      UI.showToast('Customer profile could not be loaded for editing.', 'error');
      return;
    }

    customerForm.reset();
    clearValidationErrors(customerForm);

    modalTitle.textContent = 'Edit Customer';
    document.getElementById('customerId').value = cust.id;

    // Split full_name into first and last name if needed
    let first = cust.firstName || '';
    let last = cust.lastName || '';
    if (!first && cust.full_name) {
      const parts = cust.full_name.trim().split(' ');
      first = parts[0] || '';
      last = parts.slice(1).join(' ') || '';
    }

    document.getElementById('customerFirstName').value = first;
    document.getElementById('customerLastName').value = last;
    document.getElementById('customerEmail').value = cust.email || '';
    document.getElementById('customerPhone').value = cust.phone || '';
    document.getElementById('customerAddress').value = cust.address || '';
    document.getElementById('customerCity').value = cust.city || '';
    document.getElementById('customerPostalCode').value = cust.postal_code || cust.postalCode || '';
    document.getElementById('customerStatus').value = cust.status || 'active';

    UI.openModal('customerModal');
  }

  /**
   * View Customer Dossier & Recent Orders Modal (Fetch from API show.php)
   */
  async function openViewCustomerModal(id) {
    let cust = null;
    let customerOrders = [];

    try {
      const res = await API.customers.get(id);
      if (res && res.success && res.data) {
        cust = res.data.customer;
        customerOrders = res.data.orders || [];
      }
    } catch (e) {
      console.warn('API show error, falling back to Store:', e);
    }

    if (!cust && window.Store && typeof Store.getCustomerById === 'function') {
      cust = await Store.getCustomerById(id);
      if (typeof Store.getOrders === 'function') {
        const allOrders = await Store.getOrders();
        customerOrders = allOrders.filter(o => o.customerId === cust.id);
      }
    }

    if (!cust) {
      UI.showToast('Customer profile could not be found.', 'error');
      return;
    }

    const name = cust.full_name || `${cust.firstName || ''} ${cust.lastName || ''}`.trim() || 'Customer';
    const email = cust.email || '';
    const phone = cust.phone || 'No phone number provided';
    const address = cust.address || 'No street address provided';
    const location = cust.city ? (cust.postal_code ? `${cust.city}, ${cust.postal_code}` : cust.city) : '';
    const totalOrders = cust.total_orders ?? customerOrders.length;
    const totalSpent = parseFloat(cust.total_spent ?? cust.totalSpent ?? 0);
    const avatar = resolveCustomerAvatar(name);
    const container = document.getElementById('viewCustomerDetailsContainer');

    container.innerHTML = `
      <div style="display:flex; align-items:center; gap:1.25rem; margin-bottom:1.5rem; background:var(--bg-surface-subtle); padding:1.25rem; border-radius:var(--radius-lg); border:1px solid var(--border-color);">
        <img src="${escapeHtml(avatar)}" alt="${escapeHtml(name)}" style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid var(--primary-500);">
        <div style="flex:1;">
          <div class="d-flex align-items-center gap-2 mb-1">
            <h3 class="fw-bold mb-0" style="font-size:1.25rem;">${escapeHtml(name)}</h3>
            ${UI.renderBadge(cust.status || 'active')}
          </div>
          <div class="text-secondary" style="font-size:0.8125rem;">
            <span><i class="fa-regular fa-envelope me-1"></i>${escapeHtml(email)}</span>
            <span class="mx-2">&bull;</span>
            <span><i class="fa-solid fa-phone me-1"></i>${escapeHtml(phone)}</span>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4" style="display:flex; gap:1rem; flex-wrap:wrap;">
        <div style="flex:1; min-width:240px; background:var(--bg-surface); padding:1rem; border-radius:var(--radius-md); border:1px solid var(--border-color);">
          <div class="text-muted" style="font-size:0.75rem; text-transform:uppercase; font-weight:700;">Delivery Address</div>
          <div class="mt-1" style="font-size:0.875rem; font-weight:600;">${escapeHtml(address)}</div>
          <div class="text-secondary" style="font-size:0.8125rem;">${escapeHtml(location)}</div>
        </div>
        <div style="flex:1; min-width:240px; background:var(--bg-surface); padding:1rem; border-radius:var(--radius-md); border:1px solid var(--border-color);">
          <div class="text-muted" style="font-size:0.75rem; text-transform:uppercase; font-weight:700;">Account Activity</div>
          <div class="mt-1" style="font-size:1.125rem; font-weight:800; color:var(--primary-700);">${UI.formatCurrency(totalSpent, currentSettings.currencySymbol)}</div>
          <div class="text-secondary" style="font-size:0.8125rem;">Total spent across ${totalOrders} ${totalOrders === 1 ? 'order' : 'orders'}</div>
        </div>
      </div>

      <h5 class="fw-bold mb-3" style="font-size:0.9375rem;">
        Order History (${customerOrders.length})
      </h5>

      ${customerOrders.length === 0 ? `
        <div class="p-3 text-center text-muted bg-light rounded" style="font-size:0.875rem;">This customer hasn't placed any orders yet.</div>
      ` : `
        <div class="table-responsive">
          <table class="table" style="font-size:0.8125rem;">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Payment</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${customerOrders.map(o => `
                <tr>
                  <td><a href="orders.html?view=${o.id}" class="fw-bold text-primary">#${escapeHtml(o.order_number || o.orderNumber)}</a></td>
                  <td>${UI.formatDate(o.order_date || o.orderDate)}</td>
                  <td><span class="text-secondary">${escapeHtml(o.payment_method || o.paymentMethod || 'Credit Card')}</span></td>
                  <td class="fw-bold">${UI.formatCurrency(parseFloat(o.total_amount || o.totalAmount || 0), currentSettings.currencySymbol)}</td>
                  <td>${UI.renderBadge(o.order_status || o.orderStatus)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `}
    `;

    UI.openModal('viewCustomerModal');
  }

  /**
   * Confirm and Delete / Archive
   */
  function confirmDeleteCustomer(id, name) {
    UI.confirmModal({
      title: 'Delete Customer',
      message: `Are you sure you want to remove customer account "${name}"? If this customer has existing orders, the account will be safely deactivated (archived) to preserve sales records.`,
      confirmText: 'Confirm Removal',
      confirmClass: 'btn-danger',
      onConfirm: async () => {
        try {
          const res = await API.customers.delete(id);

          if (res && res.success) {
            if (res.data?.action === 'archived') {
              UI.showToast(res.message || `Customer "${name}" has historical orders and was safely archived.`, 'warning');
            } else {
              UI.showToast(res.message || `Customer "${name}" was permanently removed.`, 'success');
            }
            await loadCustomers();
            return;
          }

          // Fallback to Store
          if (!isApiOnline && window.Store && typeof Store.deleteCustomer === 'function') {
            await Store.deleteCustomer(id);
            UI.showToast(`Customer "${name}" deleted locally.`, 'success');
            await loadCustomers();
            return;
          }

          UI.showToast(res?.message || 'Failed to delete customer.', 'error');

        } catch (err) {
          console.error('Delete customer error:', err);
          UI.showToast('Failed to delete customer due to a network error.', 'error');
        }
      }
    });
  }

  /**
   * Form Validation and Submission
   */
  if (customerForm) {
    customerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearValidationErrors(customerForm);

      const id = document.getElementById('customerId').value;
      const firstName = document.getElementById('customerFirstName').value.trim();
      const lastName = document.getElementById('customerLastName').value.trim();
      const email = document.getElementById('customerEmail').value.trim();
      const phone = document.getElementById('customerPhone').value.trim();
      const address = document.getElementById('customerAddress').value.trim();
      const city = document.getElementById('customerCity').value.trim();
      const postalCode = document.getElementById('customerPostalCode').value.trim();
      const status = document.getElementById('customerStatus').value;

      let hasError = false;

      // Client-side validations
      if (!firstName) {
        showError('customerFirstName', 'First name is required');
        hasError = true;
      }
      if (!lastName) {
        showError('customerLastName', 'Last name is required');
        hasError = true;
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email) {
        showError('customerEmail', 'Email address is required');
        hasError = true;
      } else if (!emailRegex.test(email)) {
        showError('customerEmail', 'Please enter a valid email address');
        hasError = true;
      }

      // Phone validation (7-30 chars, valid international format)
      if (phone !== '') {
        const phoneRegex = /^\+?[0-9\s\-\(\)\.]{7,30}$/;
        if (!phoneRegex.test(phone)) {
          showError('customerPhone', 'Please enter a valid phone number (7 to 30 digits/symbols)');
          hasError = true;
        }
      }

      if (hasError) return;

      const fullName = `${firstName} ${lastName}`.trim();
      const payload = {
        id: id ? parseInt(id, 10) : undefined,
        full_name: fullName,
        first_name: firstName,
        last_name: lastName,
        email,
        phone,
        address,
        city,
        postal_code: postalCode,
        status
      };

      // Disable button during submission
      if (saveCustomerBtn) {
        saveCustomerBtn.disabled = true;
        saveCustomerBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
      }

      try {
        let res;
        if (id) {
          res = await API.customers.update(id, payload);
        } else {
          res = await API.customers.create(payload);
        }

        if (res && res.success) {
          UI.closeModal('customerModal');
          UI.showToast(res.message || (id ? 'Customer updated successfully.' : 'Customer created successfully!'), 'success');
          await loadCustomers();
          return;
        }

        // Map backend validation errors to fields
        if (res && res.errors) {
          if (res.errors.full_name || res.errors.first_name) {
            showError('customerFirstName', res.errors.full_name || res.errors.first_name);
          }
          if (res.errors.last_name) {
            showError('customerLastName', res.errors.last_name);
          }
          if (res.errors.email) {
            showError('customerEmail', res.errors.email);
          }
          if (res.errors.phone) {
            showError('customerPhone', res.errors.phone);
          }
          if (res.errors.address) {
            showError('customerAddress', res.errors.address);
          }
          if (res.errors.city) {
            showError('customerCity', res.errors.city);
          }
          if (res.errors.postal_code) {
            showError('customerPostalCode', res.errors.postal_code);
          }

          UI.showToast(res.message || 'Validation error. Please verify the highlighted fields.', 'error');
          return;
        }

        // Fallback to Store
        if (!isApiOnline && window.Store && typeof Store.saveCustomer === 'function') {
          await Store.saveCustomer({
            id: id ? Number(id) : null,
            firstName,
            lastName,
            email,
            phone,
            address,
            city,
            postalCode,
            status
          });
          UI.closeModal('customerModal');
          UI.showToast(id ? 'Customer updated locally.' : 'Customer created locally!', 'success');
          await loadCustomers();
          return;
        }

        UI.showToast(res?.message || 'Failed to save customer.', 'error');

      } catch (err) {
        console.error('Save customer error:', err);
        UI.showToast('Network error while saving customer.', 'error');
      } finally {
        if (saveCustomerBtn) {
          saveCustomerBtn.disabled = false;
          saveCustomerBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Customer';
        }
      }
    });
  }

  // Filter Listeners
  let searchDebounceTimer = null;
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => {
        state.search = searchInput.value.trim();
        state.page = 1;
        loadCustomers();
      }, 300);
    });
  }

  if (cityFilter) {
    cityFilter.addEventListener('change', () => {
      state.city = cityFilter.value;
      state.page = 1;
      loadCustomers();
    });
  }

  if (statusFilter) {
    statusFilter.addEventListener('change', () => {
      state.status = statusFilter.value;
      state.page = 1;
      loadCustomers();
    });
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', () => {
      state.sortBy = sortSelect.value;
      state.page = 1;
      loadCustomers();
    });
  }

  const addBtn = document.getElementById('openAddCustomerBtn');
  if (addBtn) addBtn.addEventListener('click', openAddCustomerModal);

  // Modal dismiss buttons
  document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.closest('.modal-backdrop');
      if (target) UI.closeModal(target.id);
    });
  });

  /**
   * Fallback to Store when API is not reachable
   */
  async function fallbackLoadFromStore() {
    if (!window.Store || typeof Store.getCustomers !== 'function') return;

    let items = await Store.getCustomers();

    if (state.search) {
      const q = state.search.toLowerCase();
      items = items.filter(c =>
        `${c.firstName || ''} ${c.lastName || ''}`.toLowerCase().includes(q) ||
        (c.email && c.email.toLowerCase().includes(q)) ||
        (c.phone && c.phone.includes(q)) ||
        (c.city && c.city.toLowerCase().includes(q))
      );
    }

    if (state.city !== 'all') {
      items = items.filter(c => (c.city || '').toLowerCase() === state.city.toLowerCase());
    }

    if (state.status !== 'all') {
      items = items.filter(c => c.status === state.status);
    }

    state.totalItems = items.length;
    state.totalPages = Math.ceil(items.length / state.limit) || 1;
    if (state.page > state.totalPages) state.page = state.totalPages;

    const startIdx = (state.page - 1) * state.limit;
    const paginated = items.slice(startIdx, startIdx + state.limit);

    renderCustomerTable(paginated);
    renderPaginationControls();
  }

  function showError(fieldId, message) {
    const el = document.getElementById(fieldId);
    if (!el) return;
    el.classList.add('is-invalid');
    const feedback = el.closest('.form-group')?.querySelector('.invalid-feedback');
    if (feedback) feedback.textContent = message;
  }

  function clearValidationErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function checkUrlParams() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('search')) {
      state.search = params.get('search');
      if (searchInput) searchInput.value = state.search;
      loadCustomers();
    }
    if (params.has('edit')) {
      const editId = params.get('edit');
      openEditCustomerModal(editId);
    }
    if (params.has('view')) {
      const viewId = params.get('view');
      openViewCustomerModal(viewId);
    }
  }
});
