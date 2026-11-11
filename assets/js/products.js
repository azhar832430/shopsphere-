/**
 * SHOPSPHERE - PRODUCTS MANAGEMENT CONTROLLER
 * Phase 4: Complete Product Management CRUD
 *
 * Connected directly to MySQL via PHP Backend APIs:
 * - GET  api/products/index.php (Server pagination, filtering, search, sorting)
 * - GET  api/products/show.php (Single product view)
 * - POST api/products/store.php (Create with file upload or URL)
 * - POST api/products/update.php (Update with file upload or URL)
 * - POST api/products/delete.php (Delete or archive if referenced in orders)
 * - Graceful fallback to Store if backend is unreachable
 */

document.addEventListener('DOMContentLoaded', async () => {
  if (!document.getElementById('productsTableBody')) return;

  // State
  let allCategories = [];
  let currentSettings = { currencySymbol: '$', lowStockThreshold: 10 };
  let isApiOnline = true;

  const state = {
    search: '',
    categoryId: 'all',
    stockStatus: 'all',
    sortBy: 'newest',
    page: 1,
    limit: 8,
    totalPages: 1,
    totalItems: 0
  };

  // Sort mapping from UI dropdown values to API params
  const sortMapping = {
    newest:     { sort: 'id',    order: 'DESC' },
    name_asc:   { sort: 'name',  order: 'ASC' },
    name_desc:  { sort: 'name',  order: 'DESC' },
    price_low:  { sort: 'price', order: 'ASC' },
    price_high: { sort: 'price', order: 'DESC' },
    stock_low:  { sort: 'stock', order: 'ASC' },
    stock_high: { sort: 'stock', order: 'DESC' }
  };

  // DOM Elements
  const tableBody = document.getElementById('productsTableBody');
  const searchInput = document.getElementById('productSearchInput');
  const categoryFilter = document.getElementById('productCategoryFilter');
  const stockFilter = document.getElementById('productStockFilter');
  const sortSelect = document.getElementById('productSortSelect');
  const productForm = document.getElementById('productForm');
  const productModalTitle = document.getElementById('productModalTitle');
  const imageInput = document.getElementById('productImage');
  const imageUrlInput = document.getElementById('productImageUrl');
  const imagePreview = document.getElementById('productImagePreview');
  const saveProductBtn = document.getElementById('saveProductBtn');

  // Initialization
  try {
    if (window.Store && typeof Store.getSettings === 'function') {
      currentSettings = await Store.getSettings();
    }
  } catch (err) {
    console.warn('Could not load local settings, using defaults.', err);
  }

  // Initial load
  await loadCategories();
  await loadProducts();
  checkUrlParams();

  /**
   * Helper to resolve product image URL/path safely
   */
  function resolveProductImage(img) {
    if (!img) return 'https://placehold.co/400x300/EEF2FF/4F46E5?text=No+Image';
    if (img.startsWith('http://') || img.startsWith('https://') || img.startsWith('data:')) {
      return img;
    }
    // Relative upload path, e.g. "uploads/products/prod_xxx.jpg"
    return img;
  }

  /**
   * Fetch and populate categories (from API or Store fallback)
   */
  async function loadCategories() {
    try {
      if (window.Store && typeof Store.getCategories === 'function') {
        allCategories = await Store.getCategories();
      }
      populateCategoryDropdowns();
    } catch (err) {
      console.warn('Category load fallback warning:', err);
    }
  }

  /**
   * Populate category selects (filter dropdown & form dropdown)
   */
  function populateCategoryDropdowns() {
    if (categoryFilter) {
      const selected = categoryFilter.value || 'all';
      categoryFilter.innerHTML = '<option value="all">All Categories</option>' +
        allCategories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
      categoryFilter.value = selected;
    }

    const formCatSelect = document.getElementById('productCategorySelect');
    if (formCatSelect) {
      formCatSelect.innerHTML = '<option value="">Select Category...</option>' +
        allCategories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    }
  }

  /**
   * Main Load & Render function with loading and error states
   */
  async function loadProducts() {
    renderLoadingState();

    const sortConfig = sortMapping[state.sortBy] || { sort: 'id', order: 'DESC' };
    const params = {
      page: state.page,
      limit: state.limit,
      search: state.search,
      category_id: state.categoryId !== 'all' ? state.categoryId : '',
      stock_status: state.stockStatus,
      sort: sortConfig.sort,
      order: sortConfig.order
    };

    try {
      const response = await API.products.list(params);

      if (response && response.success && response.data) {
        isApiOnline = true;
        const { items, meta, categories } = response.data;

        // If categories were returned by the API, sync them
        if (Array.isArray(categories) && categories.length > 0) {
          allCategories = categories;
          populateCategoryDropdowns();
        }

        state.totalPages = meta?.total_pages || 1;
        state.totalItems = meta?.total_items || 0;
        state.page = meta?.page || state.page;

        renderProductTable(items);
        renderPaginationControls();
        return;
      }

      // If backend returned error, show message
      renderErrorState(response?.message || 'Failed to load products from database.');

    } catch (apiError) {
      console.warn('[Products] API fetch error, falling back to local store:', apiError);
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
            <h4 class="empty-state-title" style="margin-top: 1rem;">Loading Products...</h4>
            <p class="empty-state-desc">Retrieving inventory records from MySQL database.</p>
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
            <button class="btn btn-primary btn-sm" id="retryLoadBtn">
              <i class="fa-solid fa-rotate"></i> Retry Connection
            </button>
          </div>
        </td>
      </tr>
    `;
    const retryBtn = document.getElementById('retryLoadBtn');
    if (retryBtn) retryBtn.addEventListener('click', loadProducts);
  }

  /**
   * Render Products Table Rows
   */
  function renderProductTable(items) {
    if (!items || items.length === 0) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="7">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fa-solid fa-box-open"></i></div>
              <h4 class="empty-state-title">No products found</h4>
              <p class="empty-state-desc">Try clearing your search/filters or add a new product to your inventory catalog.</p>
              <button class="btn btn-primary btn-sm" id="emptyAddProductBtn">
                <i class="fa-solid fa-plus"></i> Add New Product
              </button>
            </div>
          </td>
        </tr>
      `;
      const emptyBtn = document.getElementById('emptyAddProductBtn');
      if (emptyBtn) emptyBtn.addEventListener('click', openAddProductModal);
      return;
    }

    tableBody.innerHTML = items.map(p => {
      const catName = p.category_name || (allCategories.find(c => c.id == (p.category_id || p.categoryId))?.name) || 'Uncategorized';
      const price = parseFloat(p.price) || 0;
      const stock = parseInt(p.stock, 10) || 0;
      const status = p.status || 'active';
      const created = p.created_at || p.createdAt || '';
      const imgUrl = resolveProductImage(p.image || p.imageUrl);

      let stockBadge = '';
      if (stock === 0) {
        stockBadge = '<span class="badge badge-danger"><span class="badge-dot"></span>Out of Stock</span>';
      } else if (stock <= (currentSettings.lowStockThreshold || 10)) {
        stockBadge = `<span class="badge badge-warning"><span class="badge-dot"></span>Low (${stock})</span>`;
      } else {
        stockBadge = `<span class="badge badge-success"><span class="badge-dot"></span>${stock} units</span>`;
      }

      return `
        <tr>
          <td>
            <div class="table-product-cell">
              <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(p.name)}" class="table-img-thumb" onerror="this.src='https://placehold.co/80x80/EEF2FF/4F46E5?text=Product'">
              <div class="table-product-info">
                <span class="table-primary-text" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</span>
                <span class="table-secondary-text">SKU: <code class="fw-semibold">${escapeHtml(p.sku)}</code></span>
              </div>
            </div>
          </td>
          <td><span class="badge badge-neutral">${escapeHtml(catName)}</span></td>
          <td class="fw-bold">${UI.formatCurrency(price, currentSettings.currencySymbol)}</td>
          <td>${stockBadge}</td>
          <td>${UI.renderBadge(status)}</td>
          <td class="text-secondary" style="font-size:0.75rem;">${created ? UI.formatDate(created) : '—'}</td>
          <td>
            <div class="action-buttons">
              <button type="button" class="action-btn action-view" data-action="view" data-id="${p.id}" title="View Details">
                <i class="fa-solid fa-eye"></i>
              </button>
              <button type="button" class="action-btn action-edit" data-action="edit" data-id="${p.id}" title="Edit Product">
                <i class="fa-solid fa-pen"></i>
              </button>
              <button type="button" class="action-btn action-delete" data-action="delete" data-id="${p.id}" data-name="${escapeHtml(p.name)}" title="Delete Product">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');

    bindTableActionEvents();
  }

  /**
   * Render Pagination Controls
   */
  function renderPaginationControls() {
    UI.renderPagination({
      containerId: 'productsPagination',
      currentPage: state.page,
      totalPages: state.totalPages,
      totalItems: state.totalItems,
      limit: state.limit,
      onPageChange: (newPage) => {
        state.page = newPage;
        loadProducts();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  /**
   * Bind Click Events on Row Action Buttons
   */
  function bindTableActionEvents() {
    tableBody.querySelectorAll('[data-action="view"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        openViewProductModal(id);
      });
    });

    tableBody.querySelectorAll('[data-action="edit"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        openEditProductModal(id);
      });
    });

    tableBody.querySelectorAll('[data-action="delete"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const name = btn.getAttribute('data-name');
        confirmDeleteProduct(id, name);
      });
    });
  }

  /**
   * View Product Modal (Fetch from API show.php)
   */
  async function openViewProductModal(id) {
    let product = null;

    try {
      const res = await API.products.get(id);
      if (res && res.success && res.data?.product) {
        product = res.data.product;
      }
    } catch (e) {
      console.warn('API show error, fallback to Store:', e);
    }

    if (!product && window.Store && typeof Store.getProductById === 'function') {
      product = await Store.getProductById(id);
    }

    if (!product) {
      UI.showToast('Product details could not be found.', 'error');
      return;
    }

    const catName = product.category_name || (allCategories.find(c => c.id == (product.category_id || product.categoryId))?.name) || 'Uncategorized';
    const price = parseFloat(product.price) || 0;
    const stock = parseInt(product.stock, 10) || 0;
    const imgUrl = resolveProductImage(product.image || product.imageUrl);
    const viewContainer = document.getElementById('viewProductDetailsContainer');

    viewContainer.innerHTML = `
      <div style="display:flex; gap:1.5rem; flex-wrap:wrap;">
        <div style="flex:0 0 220px; text-align:center;">
          <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(product.name)}" style="width:100%; height:220px; object-fit:cover; border-radius:var(--radius-lg); border:1px solid var(--border-color); box-shadow:var(--shadow-sm);" onerror="this.src='https://placehold.co/220x220/EEF2FF/4F46E5?text=Product'">
          <div class="mt-3">${UI.renderBadge(product.status)}</div>
        </div>
        <div style="flex:1; min-width:260px;">
          <div class="badge badge-neutral mb-2">${escapeHtml(catName)}</div>
          <h3 style="font-size:1.375rem; font-weight:800; color:var(--text-primary); margin-bottom:0.25rem;">${escapeHtml(product.name)}</h3>
          <p class="text-muted" style="font-size:0.8125rem; margin-bottom:1rem;">SKU: <strong class="text-dark">${escapeHtml(product.sku)}</strong></p>
          
          <div style="display:flex; align-items:baseline; gap:1rem; margin-bottom:1.25rem; background:var(--bg-surface-subtle); padding:0.875rem 1rem; border-radius:var(--radius-md);">
            <div>
              <span class="text-muted" style="font-size:0.75rem; display:block;">UNIT PRICE</span>
              <span style="font-size:1.5rem; font-weight:800; color:var(--primary-700);">${UI.formatCurrency(price, currentSettings.currencySymbol)}</span>
            </div>
            <div style="border-left:1px solid var(--border-color); padding-left:1rem;">
              <span class="text-muted" style="font-size:0.75rem; display:block;">STOCK AVAILABLE</span>
              <span style="font-size:1.125rem; font-weight:700;">${stock} units</span>
            </div>
          </div>

          <div>
            <h5 style="font-size:0.875rem; font-weight:700; margin-bottom:0.35rem;">Product Description</h5>
            <p style="font-size:0.875rem; color:var(--text-secondary); line-height:1.6;">${escapeHtml(product.description || 'No description provided.')}</p>
          </div>
        </div>
      </div>
    `;

    UI.openModal('viewProductModal');
  }

  /**
   * Add Product Form
   */
  function openAddProductModal() {
    productForm.reset();
    document.getElementById('productId').value = '';
    productModalTitle.textContent = 'Add New Product';
    imagePreview.src = 'https://placehold.co/400x300/EEF2FF/4F46E5?text=Product+Image';
    clearValidationErrors(productForm);
    UI.openModal('productModal');
  }

  /**
   * Edit Product Form (Populate from API show.php)
   */
  async function openEditProductModal(id) {
    let product = null;

    try {
      const res = await API.products.get(id);
      if (res && res.success && res.data?.product) {
        product = res.data.product;
      }
    } catch (e) {
      console.warn('API get error on edit, fallback to Store:', e);
    }

    if (!product && window.Store && typeof Store.getProductById === 'function') {
      product = await Store.getProductById(id);
    }

    if (!product) {
      UI.showToast('Product could not be loaded for editing.', 'error');
      return;
    }

    productForm.reset();
    clearValidationErrors(productForm);

    productModalTitle.textContent = 'Edit Product';
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name || '';
    document.getElementById('productSku').value = product.sku || '';
    document.getElementById('productCategorySelect').value = product.category_id || product.categoryId || '';
    document.getElementById('productPrice').value = product.price || '';
    document.getElementById('productStock').value = product.stock ?? 0;
    document.getElementById('productStatus').value = product.status || 'active';
    document.getElementById('productDescription').value = product.description || '';

    const imgVal = product.image || product.imageUrl || '';
    if (imageUrlInput) imageUrlInput.value = imgVal.startsWith('http') ? imgVal : '';
    imagePreview.src = resolveProductImage(imgVal);

    UI.openModal('productModal');
  }

  /**
   * Confirm and Delete / Archive
   */
  function confirmDeleteProduct(id, name) {
    UI.confirmModal({
      title: 'Delete Product',
      message: `Are you sure you want to remove "${name}" from your catalog? If this product has historical orders, it will be safely deactivated (archived) to preserve sales records.`,
      confirmText: 'Confirm Removal',
      confirmClass: 'btn-danger',
      onConfirm: async () => {
        try {
          const res = await API.products.delete(id);

          if (res && res.success) {
            if (res.data?.action === 'archived') {
              UI.showToast(res.message || `Product "${name}" was deactivated (archived) because it is linked to existing customer orders.`, 'warning');
            } else {
              UI.showToast(res.message || `Product "${name}" deleted successfully.`, 'success');
            }
            await loadProducts();
            return;
          }

          // If API call fails, try Store fallback
          if (!isApiOnline && window.Store && typeof Store.deleteProduct === 'function') {
            await Store.deleteProduct(id);
            UI.showToast(`Product "${name}" removed from local storage.`, 'success');
            await loadProducts();
            return;
          }

          UI.showToast(res?.message || 'Failed to delete product.', 'error');

        } catch (err) {
          console.error('Delete product error:', err);
          UI.showToast('Failed to delete product due to a network error.', 'error');
        }
      }
    });
  }

  /**
   * Form Submit & Validation (Handles multipart FormData or JSON)
   */
  if (productForm) {
    productForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearValidationErrors(productForm);

      const id = document.getElementById('productId').value;
      const name = document.getElementById('productName').value.trim();
      const sku = document.getElementById('productSku').value.trim();
      const categoryId = document.getElementById('productCategorySelect').value;
      const priceStr = document.getElementById('productPrice').value.trim();
      const stockStr = document.getElementById('productStock').value.trim();
      const status = document.getElementById('productStatus').value;
      const description = document.getElementById('productDescription').value.trim();
      const imageUrl = imageUrlInput ? imageUrlInput.value.trim() : '';
      const file = imageInput?.files?.[0] || null;

      let hasError = false;

      // Client-side validations
      if (!name) {
        showError('productName', 'Product name is required');
        hasError = true;
      }
      if (!sku) {
        showError('productSku', 'Product SKU is required');
        hasError = true;
      }
      if (!categoryId) {
        showError('productCategorySelect', 'Please select a product category');
        hasError = true;
      }

      const price = parseFloat(priceStr);
      if (isNaN(price) || price < 0) {
        showError('productPrice', 'Price must be a valid positive number');
        hasError = true;
      }

      const stock = parseInt(stockStr, 10);
      if (isNaN(stock) || stock < 0) {
        showError('productStock', 'Stock quantity must be 0 or greater');
        hasError = true;
      }

      // Check file size (2MB) on client
      if (file && file.size > 2 * 1024 * 1024) {
        showError('productImage', 'Image file exceeds maximum 2MB size limit');
        hasError = true;
      }

      if (hasError) return;

      // Disable button during submission to prevent duplicate clicks
      if (saveProductBtn) {
        saveProductBtn.disabled = true;
        saveProductBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
      }

      try {
        let res;

        if (file) {
          // Multipart form data upload
          const formData = new FormData();
          if (id) formData.append('id', id);
          formData.append('name', name);
          formData.append('sku', sku);
          formData.append('category_id', categoryId);
          formData.append('price', price.toFixed(2));
          formData.append('stock', stock.toString());
          formData.append('status', status);
          formData.append('description', description);
          formData.append('image_file', file);

          if (id) {
            res = await API.products.update(id, formData);
          } else {
            res = await API.products.create(formData);
          }
        } else {
          // Standard JSON payload
          const payload = {
            id: id ? parseInt(id, 10) : undefined,
            name,
            sku,
            category_id: parseInt(categoryId, 10),
            price,
            stock,
            status,
            description,
            image: imageUrl || (id ? undefined : 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&auto=format&fit=crop&q=80')
          };

          if (id) {
            res = await API.products.update(id, payload);
          } else {
            res = await API.products.create(payload);
          }
        }

        if (res && res.success) {
          UI.closeModal('productModal');
          UI.showToast(res.message || (id ? 'Product updated successfully.' : 'Product created successfully!'), 'success');
          await loadProducts();
          return;
        }

        // Handle backend validation errors
        if (res && res.errors) {
          if (res.errors.name) showError('productName', res.errors.name);
          if (res.errors.sku) showError('productSku', res.errors.sku);
          if (res.errors.category_id) showError('productCategorySelect', res.errors.category_id);
          if (res.errors.price) showError('productPrice', res.errors.price);
          if (res.errors.stock) showError('productStock', res.errors.stock);
          if (res.errors.image || res.errors.upload) showError('productImage', res.errors.image || res.errors.upload);

          UI.showToast(res.message || 'Validation error. Please verify the highlighted fields.', 'error');
          return;
        }

        // Fallback to local store if backend was offline
        if (!isApiOnline && window.Store && typeof Store.saveProduct === 'function') {
          await Store.saveProduct({
            id: id ? Number(id) : null,
            name,
            sku,
            categoryId: Number(categoryId),
            price,
            stock,
            status,
            description,
            imageUrl: imageUrl || 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&auto=format&fit=crop&q=80'
          });
          UI.closeModal('productModal');
          UI.showToast(id ? 'Product updated locally.' : 'Product created locally!', 'success');
          await loadProducts();
          return;
        }

        UI.showToast(res?.message || 'Failed to save product.', 'error');

      } catch (err) {
        console.error('Save product error:', err);
        UI.showToast('Network error while saving product.', 'error');
      } finally {
        if (saveProductBtn) {
          saveProductBtn.disabled = false;
          saveProductBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Product';
        }
      }
    });
  }

  // Live preview image when URL typed
  if (imageUrlInput && imagePreview) {
    imageUrlInput.addEventListener('input', () => {
      const url = imageUrlInput.value.trim();
      imagePreview.src = url || 'https://placehold.co/400x300/EEF2FF/4F46E5?text=Product+Image';
    });
  }

  // File upload preview
  if (imageInput && imagePreview) {
    imageInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        if (!file.type.startsWith('image/')) {
          showError('productImage', 'Please select a valid image file (PNG, JPG, WEBP)');
          return;
        }
        if (file.size > 2 * 1024 * 1024) {
          showError('productImage', 'Image file cannot exceed 2MB');
          return;
        }
        const reader = new FileReader();
        reader.onload = (event) => {
          imagePreview.src = event.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Search filter event (with debounce)
  let searchDebounceTimer = null;
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => {
        state.search = searchInput.value.trim();
        state.page = 1;
        loadProducts();
      }, 300);
    });
  }

  // Category filter
  if (categoryFilter) {
    categoryFilter.addEventListener('change', () => {
      state.categoryId = categoryFilter.value;
      state.page = 1;
      loadProducts();
    });
  }

  // Stock status filter
  if (stockFilter) {
    stockFilter.addEventListener('change', () => {
      state.stockStatus = stockFilter.value;
      state.page = 1;
      loadProducts();
    });
  }

  // Sort select
  if (sortSelect) {
    sortSelect.addEventListener('change', () => {
      state.sortBy = sortSelect.value;
      state.page = 1;
      loadProducts();
    });
  }

  const addBtn = document.getElementById('openAddProductBtn');
  if (addBtn) addBtn.addEventListener('click', openAddProductModal);

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
    if (!window.Store || typeof Store.getProducts !== 'function') return;

    let items = await Store.getProducts();

    if (state.search) {
      const q = state.search.toLowerCase();
      items = items.filter(p =>
        p.name.toLowerCase().includes(q) ||
        p.sku.toLowerCase().includes(q) ||
        (p.description && p.description.toLowerCase().includes(q))
      );
    }

    if (state.categoryId !== 'all') {
      items = items.filter(p => p.categoryId === Number(state.categoryId) || p.category_id === Number(state.categoryId));
    }

    if (state.stockStatus === 'in_stock') {
      items = items.filter(p => p.stock > 0);
    } else if (state.stockStatus === 'low_stock') {
      items = items.filter(p => p.stock > 0 && p.stock <= (currentSettings.lowStockThreshold || 10));
    } else if (state.stockStatus === 'out_of_stock') {
      items = items.filter(p => p.stock === 0);
    }

    state.totalItems = items.length;
    state.totalPages = Math.ceil(items.length / state.limit) || 1;
    if (state.page > state.totalPages) state.page = state.totalPages;

    const startIdx = (state.page - 1) * state.limit;
    const paginated = items.slice(startIdx, startIdx + state.limit);

    renderProductTable(paginated);
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
      loadProducts();
    }
    if (params.has('edit')) {
      const editId = params.get('edit');
      openEditProductModal(editId);
    }
  }
});
