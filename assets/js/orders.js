/**
 * SHOPSPHERE - ORDER MANAGEMENT CONTROLLER
 * Phase 6: Complete Order Management CRUD
 *
 * Connected directly to MySQL via PHP Backend APIs:
 * - GET  api/orders/index.php (Server pagination, filters, search, sorting)
 * - GET  api/orders/show.php (Single order details, customer info, itemized lines)
 * - POST api/orders/store.php (Atomic creation with stock deduction and validation)
 * - POST api/orders/update.php (Status transition updates and stock management)
 * - POST api/orders/cancel.php (Safe cancellation with atomic stock restoration)
 * - GET  api/orders/stats.php (Order telemetry & revenue metrics)
 * - Graceful fallback to Store if backend is unreachable
 */

document.addEventListener('DOMContentLoaded', async () => {
  if (!document.getElementById('ordersTableBody')) return;

  // State
  let allCustomers = [];
  let allProducts = [];
  let currentSettings = { currencySymbol: '$', taxRate: 8.5, freeShippingThreshold: 100, shippingFlat: 15.00 };
  let isApiOnline = true;

  const state = {
    search: '',
    orderStatus: 'all',
    paymentStatus: 'all',
    sortBy: 'newest',
    page: 1,
    limit: 8,
    totalPages: 1,
    totalItems: 0
  };

  // Sort mapping from UI dropdown to API query params
  const sortMapping = {
    newest:       { sort: 'id',           order: 'DESC' },
    date_asc:     { sort: 'order_date',   order: 'ASC' },
    total_high:   { sort: 'total_amount', order: 'DESC' },
    total_low:    { sort: 'total_amount', order: 'ASC' },
    customer_asc: { sort: 'customer_name',order: 'ASC' }
  };

  // DOM Elements
  const tableBody = document.getElementById('ordersTableBody');
  const searchInput = document.getElementById('orderSearchInput');
  const orderStatusFilter = document.getElementById('orderStatusFilter');
  const paymentStatusFilter = document.getElementById('paymentStatusFilter');
  const sortSelect = document.getElementById('orderSortSelect');
  const orderForm = document.getElementById('orderForm');
  const lineItemsContainer = document.getElementById('orderLineItemsList');
  const addLineItemBtn = document.getElementById('addOrderLineItemBtn');
  const placeOrderBtn = document.getElementById('placeOrderSubmitBtn');
  const quickEditForm = document.getElementById('quickEditOrderForm');

  // Initialization
  try {
    if (window.Store && typeof Store.getSettings === 'function') {
      currentSettings = await Store.getSettings();
    }
  } catch (err) {
    console.warn('Could not load local settings, using defaults.', err);
  }

  // Pre-load reference lists (customers and products)
  await loadReferenceData();
  await loadOrders();
  checkUrlParams();

  /**
   * Load customers and products from database for order builder dropdowns
   */
  async function loadReferenceData() {
    try {
      // 1. Fetch Customers
      if (window.API && API.customers && typeof API.customers.list === 'function') {
        const custRes = await API.customers.list({ limit: 100, status: 'active' });
        if (custRes && custRes.success && custRes.data?.items) {
          allCustomers = custRes.data.items;
        }
      }
      if (allCustomers.length === 0 && window.Store && typeof Store.getCustomers === 'function') {
        allCustomers = await Store.getCustomers();
      }
      populateCustomerDropdown();

      // 2. Fetch Products
      if (window.API && API.products && typeof API.products.list === 'function') {
        const prodRes = await API.products.list({ limit: 100 });
        if (prodRes && prodRes.success && prodRes.data?.items) {
          allProducts = prodRes.data.items;
        }
      }
      if (allProducts.length === 0 && window.Store && typeof Store.getProducts === 'function') {
        allProducts = await Store.getProducts();
      }
    } catch (err) {
      console.warn('Could not load reference data from APIs, using fallback:', err);
    }
  }

  /**
   * Populate customer selector
   */
  function populateCustomerDropdown() {
    const custSelect = document.getElementById('orderCustomerSelect');
    if (!custSelect) return;

    custSelect.innerHTML = '<option value="">Select Customer...</option>' +
      allCustomers.map(c => {
        const name = c.full_name || `${c.firstName || ''} ${c.lastName || ''}`.trim();
        return `<option value="${c.id}">${escapeHtml(name)} (${escapeHtml(c.email)})</option>`;
      }).join('');
  }

  /**
   * Main Load & Render function with loading, error, and empty states
   */
  async function loadOrders() {
    renderLoadingState();

    const sortConfig = sortMapping[state.sortBy] || { sort: 'id', order: 'DESC' };
    const params = {
      page: state.page,
      limit: state.limit,
      search: state.search,
      order_status: state.orderStatus !== 'all' ? state.orderStatus : '',
      payment_status: state.paymentStatus !== 'all' ? state.paymentStatus : '',
      sort: sortConfig.sort,
      order: sortConfig.order
    };

    try {
      const response = await API.orders.list(params);

      if (response && response.success && response.data) {
        isApiOnline = true;
        const { items, meta } = response.data;

        state.totalPages = meta?.total_pages || 1;
        state.totalItems = meta?.total_items || 0;
        state.page = meta?.page || state.page;

        renderOrdersTable(items);
        renderPaginationControls();
        return;
      }

      renderErrorState(response?.message || 'Failed to load orders from database.');

    } catch (apiError) {
      console.warn('[Orders] API fetch error, falling back to local store:', apiError);
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
        <td colspan="8">
          <div class="empty-state" style="padding: 3rem 1rem;">
            <div class="empty-state-icon" style="color: var(--primary-600); animation: spin 1s linear infinite;">
              <i class="fa-solid fa-spinner"></i>
            </div>
            <h4 class="empty-state-title" style="margin-top: 1rem;">Loading Orders...</h4>
            <p class="empty-state-desc">Retrieving order fulfillment records and customer data from MySQL.</p>
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
        <td colspan="8">
          <div class="empty-state" style="padding: 2.5rem 1rem;">
            <div class="empty-state-icon" style="color: var(--danger-500);"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <h4 class="empty-state-title">Database Communication Error</h4>
            <p class="empty-state-desc">${escapeHtml(errorMessage)}</p>
            <button class="btn btn-primary btn-sm" id="retryOrdersLoadBtn">
              <i class="fa-solid fa-rotate"></i> Retry Connection
            </button>
          </div>
        </td>
      </tr>
    `;
    const retryBtn = document.getElementById('retryOrdersLoadBtn');
    if (retryBtn) retryBtn.addEventListener('click', loadOrders);
  }

  /**
   * Render order rows
   */
  function renderOrdersTable(items) {
    if (!items || items.length === 0) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="8">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fa-solid fa-cart-shopping"></i></div>
              <h4 class="empty-state-title">No orders found</h4>
              <p class="empty-state-desc">Try clearing your filters or create a new order manually.</p>
              <button class="btn btn-primary btn-sm" id="emptyCreateOrderBtn">
                <i class="fa-solid fa-plus"></i> Create New Order
              </button>
            </div>
          </td>
        </tr>
      `;
      const emptyBtn = document.getElementById('emptyCreateOrderBtn');
      if (emptyBtn) emptyBtn.addEventListener('click', openCreateOrderModal);
      return;
    }

    tableBody.innerHTML = items.map(o => {
      const orderNum = o.order_number || o.orderNumber;
      const custName = o.customer_name || o.customerName || 'Guest Customer';
      const custEmail = o.customer_email || o.customerEmail || '';
      const orderDate = o.order_date || o.orderDate;
      const count = o.item_count ?? (o.items ? o.items.length : 1);
      const totalAmount = parseFloat(o.total_amount ?? o.totalAmount ?? 0);
      const payStatus = o.payment_status || o.paymentStatus || 'pending';
      const ordStatus = o.order_status || o.orderStatus || 'pending';

      const isDelivered = ordStatus === 'delivered';
      const isCancelled = ordStatus === 'cancelled';

      return `
        <tr>
          <td>
            <a href="#" class="fw-bold text-primary action-view-order" data-id="${o.id}">
              #${escapeHtml(orderNum)}
            </a>
          </td>
          <td>
            <div class="fw-semibold text-dark">${escapeHtml(custName)}</div>
            <div class="text-muted" style="font-size:0.6875rem;">${escapeHtml(custEmail)}</div>
          </td>
          <td class="text-secondary" style="font-size:0.8125rem;">${UI.formatDate(orderDate)}</td>
          <td>
            <span class="badge badge-neutral">${count} ${count === 1 ? 'item' : 'items'}</span>
          </td>
          <td class="fw-bold text-dark">${UI.formatCurrency(totalAmount, currentSettings.currencySymbol)}</td>
          <td>${UI.renderBadge(payStatus)}</td>
          <td>${UI.renderBadge(ordStatus)}</td>
          <td>
            <div class="action-buttons">
              <button type="button" class="action-btn action-view" data-action="view" data-id="${o.id}" title="View Detailed Invoice">
                <i class="fa-solid fa-file-invoice"></i>
              </button>
              <button type="button" class="action-btn action-edit" data-action="edit" data-id="${o.id}" title="Update Order Status" ${isCancelled ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : ''}>
                <i class="fa-solid fa-pen"></i>
              </button>
              <button type="button" class="action-btn action-cancel text-danger" data-action="cancel" data-id="${o.id}" data-num="${escapeHtml(orderNum)}" title="Cancel Order & Restore Stock" ${isCancelled || isDelivered ? 'disabled style="opacity:0.3;cursor:not-allowed;"' : ''}>
                <i class="fa-solid fa-ban"></i>
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
      containerId: 'ordersPagination',
      currentPage: state.page,
      totalPages: state.totalPages,
      totalItems: state.totalItems,
      limit: state.limit,
      onPageChange: (newPage) => {
        state.page = newPage;
        loadOrders();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  /**
   * Bind row action events
   */
  function bindTableEvents() {
    tableBody.querySelectorAll('[data-action="view"], .action-view-order').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = btn.getAttribute('data-id');
        openInvoiceModal(id);
      });
    });

    tableBody.querySelectorAll('[data-action="edit"]').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        const id = btn.getAttribute('data-id');
        openEditOrderModal(id);
      });
    });

    tableBody.querySelectorAll('[data-action="cancel"]').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        const id = btn.getAttribute('data-id');
        const num = btn.getAttribute('data-num');
        confirmCancelOrder(id, num);
      });
    });
  }

  /**
   * Invoice / Detailed Order Modal (Fetch from API show.php)
   */
  async function openInvoiceModal(orderId) {
    let order = null;
    let customer = null;
    let items = [];

    try {
      const res = await API.orders.get(orderId);
      if (res && res.success && res.data) {
        order = res.data.order;
        customer = res.data.customer;
        items = res.data.items || [];
      }
    } catch (e) {
      console.warn('API get order error, falling back to Store:', e);
    }

    if (!order && window.Store && typeof Store.getOrderById === 'function') {
      order = await Store.getOrderById(orderId);
      if (order && typeof Store.getCustomerById === 'function') {
        customer = await Store.getCustomerById(order.customerId);
      }
      items = order ? (order.items || []) : [];
    }

    if (!order) {
      UI.showToast('Order details could not be found.', 'error');
      return;
    }

    const container = document.getElementById('viewInvoiceContainer');
    const orderNum = order.order_number || order.orderNumber;
    const orderDate = order.order_date || order.orderDate;
    const subtotal = parseFloat(order.subtotal || 0);
    const totalAmount = parseFloat(order.total_amount || order.totalAmount || 0);
    const payStatus = order.payment_status || order.paymentStatus || 'pending';
    const ordStatus = order.order_status || order.orderStatus || 'pending';
    const payMethod = order.payment_method || order.paymentMethod || 'Credit Card';

    const custName = customer?.full_name || `${customer?.firstName || ''} ${customer?.lastName || ''}`.trim() || order.customer_name || 'Guest Customer';
    const custEmail = customer?.email || order.customer_email || '—';
    const custPhone = customer?.phone || order.customer_phone || '—';
    const deliveryAddress = customer?.address ? `${customer.address}, ${customer.city || ''} ${customer.postal_code || ''}` : (order.shipping_address || 'Standard Delivery Address');

    container.innerHTML = `
      <div class="invoice-wrapper">
        <div class="invoice-header">
          <div class="invoice-brand">
            <h2>ShopSphere</h2>
            <div class="text-muted" style="font-size:0.8125rem;">Commercial E-Commerce Platform</div>
            <div class="text-secondary" style="font-size:0.75rem; margin-top:0.25rem;">University Web Engineering PBL Project</div>
          </div>
          <div class="invoice-meta">
            <div class="invoice-number">INVOICE #${escapeHtml(orderNum)}</div>
            <div class="text-secondary" style="font-size:0.8125rem;">Date: ${UI.formatDate(orderDate)}</div>
            <div class="mt-2 d-flex justify-content-end gap-1">
              ${UI.renderBadge(ordStatus)}
              ${UI.renderBadge(payStatus)}
            </div>
          </div>
        </div>

        <div class="invoice-grid">
          <div>
            <div class="invoice-section-title">Billed To</div>
            <div class="fw-bold" style="font-size:0.9375rem;">${escapeHtml(custName)}</div>
            <div class="invoice-address-box">
              <i class="fa-regular fa-envelope me-1"></i>${escapeHtml(custEmail)}<br>
              <i class="fa-solid fa-phone me-1"></i>${escapeHtml(custPhone)}
            </div>
          </div>
          <div>
            <div class="invoice-section-title">Shipping & Payment</div>
            <div class="invoice-address-box">
              <i class="fa-solid fa-location-dot me-1"></i>${escapeHtml(deliveryAddress)}<br>
              Payment Method: <strong>${escapeHtml(payMethod)}</strong>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table" style="font-size:0.875rem;">
            <thead>
              <tr>
                <th>Item Description & SKU</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Line Total</th>
              </tr>
            </thead>
            <tbody>
              ${items.map(item => {
                const name = item.product_name || item.productName || 'Product';
                const sku = item.product_sku || item.sku || '';
                const qty = item.quantity || 1;
                const price = parseFloat(item.unit_price ?? item.price ?? 0);
                const lineTotal = parseFloat(item.subtotal ?? (price * qty));

                return `
                  <tr>
                    <td>
                      <span class="fw-bold text-dark">${escapeHtml(name)}</span>
                      ${sku ? `<br><small class="text-muted">SKU: <code>${escapeHtml(sku)}</code></small>` : ''}
                    </td>
                    <td class="text-center">${qty}</td>
                    <td class="text-end">${UI.formatCurrency(price, currentSettings.currencySymbol)}</td>
                    <td class="text-end fw-semibold">${UI.formatCurrency(lineTotal, currentSettings.currencySymbol)}</td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
        </div>

        <div class="invoice-summary-box">
          <table class="invoice-summary-table">
            <tr>
              <td class="text-muted">Items Subtotal:</td>
              <td class="fw-semibold">${UI.formatCurrency(subtotal, currentSettings.currencySymbol)}</td>
            </tr>
            <tr class="invoice-grand-total">
              <td>Grand Total:</td>
              <td>${UI.formatCurrency(totalAmount, currentSettings.currencySymbol)}</td>
            </tr>
          </table>
        </div>

        ${order.notes ? `
          <div class="mt-4 p-3 bg-light rounded" style="font-size:0.8125rem;">
            <strong>Order Notes:</strong> ${escapeHtml(order.notes)}
          </div>
        ` : ''}
      </div>
    `;

    // Hook print invoice button
    const printBtn = document.getElementById('printInvoiceBtn');
    if (printBtn) {
      printBtn.onclick = () => window.print();
    }

    UI.openModal('invoiceModal');
  }

  /**
   * Create New Order Modal
   */
  async function openCreateOrderModal() {
    orderForm.reset();
    document.getElementById('orderId').value = '';
    document.getElementById('orderModalTitle').textContent = 'Create New Customer Order';
    lineItemsContainer.innerHTML = '';
    clearValidationErrors(orderForm);

    // Ensure we have fresh product stock counts before opening builder
    await loadReferenceData();

    // Add first line item row
    addOrderLineItem();
    recalculateOrderTotals();

    UI.openModal('orderModal');
  }

  /**
   * Dynamic Line Item Adder with stock checks
   */
  function addOrderLineItem(productId = '', quantity = 1) {
    const row = document.createElement('div');
    row.className = 'order-line-item-row';

    const productOptions = allProducts.map(p => {
      const pId = p.id;
      const price = parseFloat(p.price || 0);
      const stock = parseInt(p.stock ?? 0, 10);
      const isSelected = Number(productId) === Number(pId);
      return `<option value="${pId}" data-price="${price}" data-stock="${stock}" ${isSelected ? 'selected' : ''}>
        ${escapeHtml(p.name)} ($${price.toFixed(2)}) - Available: ${stock}
      </option>`;
    }).join('');

    row.innerHTML = `
      <div>
        <label class="form-label" style="font-size:0.75rem;">Product <span class="required">*</span></label>
        <select class="form-control form-control-sm order-product-select" required>
          <option value="">Select product...</option>
          ${productOptions}
        </select>
      </div>
      <div>
        <label class="form-label" style="font-size:0.75rem;">Qty <span class="required">*</span></label>
        <input type="number" class="form-control form-control-sm order-qty-input" min="1" value="${quantity}" required>
      </div>
      <div>
        <label class="form-label" style="font-size:0.75rem;">Unit Price</label>
        <input type="text" class="form-control form-control-sm order-price-input" readonly placeholder="$0.00">
      </div>
      <div>
        <label class="form-label" style="font-size:0.75rem;">Subtotal</label>
        <input type="text" class="form-control form-control-sm order-subtotal-input" readonly placeholder="$0.00">
      </div>
      <div style="display:flex; align-items:flex-end;">
        <button type="button" class="btn btn-outline btn-sm btn-remove-item text-danger" title="Remove item">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    `;

    lineItemsContainer.appendChild(row);

    const prodSelect = row.querySelector('.order-product-select');
    const qtyInput = row.querySelector('.order-qty-input');
    const removeBtn = row.querySelector('.btn-remove-item');

    const updateRow = () => {
      const selectedOption = prodSelect.options[prodSelect.selectedIndex];
      const price = parseFloat(selectedOption?.getAttribute('data-price') || 0);
      const stock = parseInt(selectedOption?.getAttribute('data-stock') || 0, 10);
      let qty = parseInt(qtyInput.value, 10) || 1;

      if (qty < 1) {
        qty = 1;
        qtyInput.value = 1;
      }

      if (qty > stock) {
        UI.showToast(`Warning: only ${stock} units available in inventory for this product.`, 'warning');
      }

      row.querySelector('.order-price-input').value = UI.formatCurrency(price, currentSettings.currencySymbol);
      row.querySelector('.order-subtotal-input').value = UI.formatCurrency(price * qty, currentSettings.currencySymbol);

      recalculateOrderTotals();
    };

    prodSelect.addEventListener('change', updateRow);
    qtyInput.addEventListener('input', updateRow);

    removeBtn.addEventListener('click', () => {
      if (lineItemsContainer.children.length > 1) {
        row.remove();
        recalculateOrderTotals();
      } else {
        UI.showToast('Order must contain at least one product item.', 'warning');
      }
    });

    if (productId) {
      updateRow();
    }
  }

  if (addLineItemBtn) {
    addLineItemBtn.addEventListener('click', () => addOrderLineItem());
  }

  /**
   * Recalculate Subtotal and Grand Total
   */
  function recalculateOrderTotals() {
    let subtotal = 0;

    lineItemsContainer.querySelectorAll('.order-line-item-row').forEach(row => {
      const prodSelect = row.querySelector('.order-product-select');
      const qtyInput = row.querySelector('.order-qty-input');
      const selectedOption = prodSelect.options[prodSelect.selectedIndex];
      const price = parseFloat(selectedOption?.getAttribute('data-price') || 0);
      const qty = parseInt(qtyInput.value, 10) || 0;
      subtotal += price * qty;
    });

    const taxRate = (currentSettings.taxRate || 8.5) / 100;
    const tax = subtotal * taxRate;
    const shipping = subtotal >= (currentSettings.freeShippingThreshold || 100) || subtotal === 0 ? 0 : (currentSettings.shippingFlat || 15.00);
    const discount = parseFloat(document.getElementById('orderDiscountInput')?.value || 0);
    const grandTotal = Math.max(0, subtotal + tax + shipping - discount);

    const setElemText = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = UI.formatCurrency(val, currentSettings.currencySymbol);
    };

    setElemText('calcSubtotal', subtotal);
    setElemText('calcTax', tax);
    const shipElem = document.getElementById('calcShipping');
    if (shipElem) {
      shipElem.innerHTML = shipping === 0 && subtotal > 0 ? '<span class="text-success fw-bold">FREE</span>' : UI.formatCurrency(shipping, currentSettings.currencySymbol);
    }
    setElemText('calcGrandTotal', grandTotal);

    orderForm.dataset.subtotal = subtotal;
    orderForm.dataset.tax = tax;
    orderForm.dataset.shipping = shipping;
    orderForm.dataset.discount = discount;
    orderForm.dataset.grandTotal = grandTotal;
  }

  const discountInput = document.getElementById('orderDiscountInput');
  if (discountInput) {
    discountInput.addEventListener('input', recalculateOrderTotals);
  }

  // Auto-fill delivery address when customer is selected
  const custSelect = document.getElementById('orderCustomerSelect');
  if (custSelect) {
    custSelect.addEventListener('change', async () => {
      const custId = custSelect.value;
      if (custId) {
        const found = allCustomers.find(c => String(c.id) === String(custId));
        if (found) {
          const addressInput = document.getElementById('orderShippingAddress');
          if (addressInput && !addressInput.value) {
            addressInput.value = `${found.address || ''}, ${found.city || ''} ${found.postal_code || found.postalCode || ''}`.trim().replace(/^,/, '');
          }
        }
      }
    });
  }

  /**
   * Order Create Form Submission
   */
  if (orderForm) {
    orderForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearValidationErrors(orderForm);

      const customerId = document.getElementById('orderCustomerSelect').value;
      const paymentMethod = document.getElementById('orderPaymentMethod').value;
      const paymentStatus = document.getElementById('orderPaymentStatus').value;
      const orderStatus = document.getElementById('orderStatus').value;
      const shippingAddress = document.getElementById('orderShippingAddress').value.trim();
      const notes = document.getElementById('orderNotes').value.trim();

      let hasError = false;

      if (!customerId) {
        showError('orderCustomerSelect', 'Please choose a customer account');
        hasError = true;
      }
      if (!shippingAddress) {
        showError('orderShippingAddress', 'Delivery shipping address is required');
        hasError = true;
      }

      // Collect line items
      const items = [];
      lineItemsContainer.querySelectorAll('.order-line-item-row').forEach(row => {
        const prodSelect = row.querySelector('.order-product-select');
        const qtyInput = row.querySelector('.order-qty-input');
        const prodId = prodSelect.value;
        const qty = parseInt(qtyInput.value, 10);

        if (prodId && qty > 0) {
          const selectedOption = prodSelect.options[prodSelect.selectedIndex];
          const stock = parseInt(selectedOption.getAttribute('data-stock') || 0, 10);

          if (qty > stock) {
            showError('orderCustomerSelect', `Requested quantity (${qty}) exceeds available stock (${stock})`);
            hasError = true;
          }

          items.push({
            product_id: parseInt(prodId, 10),
            quantity: qty
          });
        }
      });

      if (items.length === 0) {
        UI.showToast('Please select at least one valid product line item.', 'warning');
        return;
      }

      if (hasError) return;

      const subtotal = parseFloat(orderForm.dataset.subtotal || 0);
      const tax = parseFloat(orderForm.dataset.tax || 0);
      const shipping = parseFloat(orderForm.dataset.shipping || 0);
      const discount = parseFloat(orderForm.dataset.discount || 0);

      const orderPayload = {
        customer_id: parseInt(customerId, 10),
        items,
        payment_method: paymentMethod,
        payment_status: paymentStatus,
        order_status: orderStatus,
        notes: (notes ? `${notes} (Shipping: ${shippingAddress})` : `Shipping: ${shippingAddress}`),
        tax,
        shipping,
        discount
      };

      if (placeOrderBtn) {
        placeOrderBtn.disabled = true;
        placeOrderBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Placing Order...';
      }

      try {
        const res = await API.orders.create(orderPayload);

        if (res && res.success && res.data?.order) {
          UI.closeModal('orderModal');
          UI.showToast(res.message || `Order #${res.data.order.order_number} created successfully!`, 'success');
          await loadOrders();
          await loadReferenceData(); // Refresh product stock counters
          return;
        }

        if (res && res.errors) {
          UI.showToast(res.message || 'Order creation failed. Check inventory levels.', 'error');
          return;
        }

        // Fallback to Store
        if (!isApiOnline && window.Store && typeof Store.saveOrder === 'function') {
          const localOrder = await Store.saveOrder({
            customerId: Number(customerId),
            items,
            subtotal,
            tax,
            shipping,
            discount,
            totalAmount: parseFloat(orderForm.dataset.grandTotal || 0),
            paymentMethod,
            paymentStatus,
            orderStatus,
            shippingAddress,
            notes
          });
          UI.closeModal('orderModal');
          UI.showToast(`Order #${localOrder.orderNumber} placed locally!`, 'success');
          await loadOrders();
          return;
        }

        UI.showToast(res?.message || 'Failed to place order.', 'error');

      } catch (err) {
        console.error('Order creation error:', err);
        UI.showToast('Network error while placing order.', 'error');
      } finally {
        if (placeOrderBtn) {
          placeOrderBtn.disabled = false;
          placeOrderBtn.innerHTML = '<i class="fa-solid fa-cart-check"></i> Place Order';
        }
      }
    });
  }

  /**
   * Edit Existing Order (Quick Status Edit Modal)
   */
  async function openEditOrderModal(orderId) {
    let order = null;

    try {
      const res = await API.orders.get(orderId);
      if (res && res.success && res.data?.order) {
        order = res.data.order;
      }
    } catch (e) {
      console.warn('API get order error:', e);
    }

    if (!order && window.Store && typeof Store.getOrderById === 'function') {
      order = await Store.getOrderById(orderId);
    }

    if (!order) {
      UI.showToast('Order could not be loaded.', 'error');
      return;
    }

    const orderNum = order.order_number || order.orderNumber;
    const ordStatus = order.order_status || order.orderStatus;
    const payStatus = order.payment_status || order.paymentStatus;

    document.getElementById('quickEditOrderId').value = order.id;
    document.getElementById('quickEditOrderNum').textContent = '#' + orderNum;
    document.getElementById('quickEditOrderStatus').value = ordStatus;
    document.getElementById('quickEditPaymentStatus').value = payStatus;
    document.getElementById('quickEditNotes').value = order.notes || '';

    UI.openModal('quickEditOrderModal');
  }

  // Quick edit form submit
  if (quickEditForm) {
    quickEditForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('quickEditOrderId').value;
      const orderStatus = document.getElementById('quickEditOrderStatus').value;
      const paymentStatus = document.getElementById('quickEditPaymentStatus').value;
      const notes = document.getElementById('quickEditNotes').value.trim();

      try {
        const res = await API.orders.update(id, {
          order_status: orderStatus,
          payment_status: paymentStatus,
          notes
        });

        if (res && res.success) {
          UI.closeModal('quickEditOrderModal');
          UI.showToast(res.message || 'Order status updated successfully.', 'success');
          await loadOrders();
          await loadReferenceData(); // Refresh product stock if cancelled
          return;
        }

        UI.showToast(res?.message || 'Failed to update order.', 'error');

      } catch (err) {
        console.error('Update order error:', err);
        UI.showToast('Failed to update order due to network error.', 'error');
      }
    });
  }

  /**
   * Cancel Order with Confirmation & Stock Restoration
   */
  function confirmCancelOrder(id, orderNumber) {
    UI.confirmModal({
      title: 'Cancel Order',
      message: `Are you sure you want to cancel order #${orderNumber}? Reserved product stock will be automatically restored to your catalog inventory.`,
      confirmText: 'Cancel Order & Restore Stock',
      confirmClass: 'btn-danger',
      onConfirm: async () => {
        try {
          const res = await API.orders.cancel(id);

          if (res && res.success) {
            UI.showToast(res.message || `Order #${orderNumber} cancelled and stock restored.`, 'success');
            await loadOrders();
            await loadReferenceData(); // Refresh product stock counters
            return;
          }

          UI.showToast(res?.message || 'Failed to cancel order.', 'error');

        } catch (err) {
          console.error('Cancel order error:', err);
          UI.showToast('Failed to cancel order due to a network error.', 'error');
        }
      }
    });
  }

  // Filter Event Listeners
  let searchDebounceTimer = null;
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => {
        state.search = searchInput.value.trim();
        state.page = 1;
        loadOrders();
      }, 300);
    });
  }

  if (orderStatusFilter) {
    orderStatusFilter.addEventListener('change', () => {
      state.orderStatus = orderStatusFilter.value;
      state.page = 1;
      loadOrders();
    });
  }

  if (paymentStatusFilter) {
    paymentStatusFilter.addEventListener('change', () => {
      state.paymentStatus = paymentStatusFilter.value;
      state.page = 1;
      loadOrders();
    });
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', () => {
      state.sortBy = sortSelect.value;
      state.page = 1;
      loadOrders();
    });
  }

  const createBtn = document.getElementById('openCreateOrderBtn');
  if (createBtn) createBtn.addEventListener('click', openCreateOrderModal);

  // Modal dismiss buttons
  document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.closest('.modal-backdrop');
      if (target) UI.closeModal(target.id);
    });
  });

  /**
   * Fallback to Store when API is unreachable
   */
  async function fallbackLoadFromStore() {
    if (!window.Store || typeof Store.getOrders !== 'function') return;

    let items = await Store.getOrders();

    if (state.search) {
      const q = state.search.toLowerCase();
      items = items.filter(o =>
        (o.orderNumber || '').toLowerCase().includes(q) ||
        (o.customerName || '').toLowerCase().includes(q)
      );
    }

    if (state.orderStatus !== 'all') {
      items = items.filter(o => o.orderStatus === state.orderStatus);
    }

    if (state.paymentStatus !== 'all') {
      items = items.filter(o => o.paymentStatus === state.paymentStatus);
    }

    state.totalItems = items.length;
    state.totalPages = Math.ceil(items.length / state.limit) || 1;
    if (state.page > state.totalPages) state.page = state.totalPages;

    const startIdx = (state.page - 1) * state.limit;
    const paginated = items.slice(startIdx, startIdx + state.limit);

    renderOrdersTable(paginated);
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
    if (params.has('view')) {
      const orderId = params.get('view');
      openInvoiceModal(orderId);
    } else if (params.has('create')) {
      openCreateOrderModal();
    }
  }
});
