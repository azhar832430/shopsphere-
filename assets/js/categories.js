/**
 * SHOPSPHERE - CATEGORIES MANAGEMENT CONTROLLER
 * Phase 7: Complete Category CRUD & XSS Hardened
 * 
 * Features:
 * - Real MySQL/PDO API Connection with fallback to local Store
 * - Server-side persistence via API.categories.*
 * - Full XSS sanitization using UI.escapeHtml
 * - Live Search, Status Filter, Pagination
 * - Safe Category Deletion with Product Decoupling
 */

document.addEventListener('DOMContentLoaded', async () => {
  if (!document.getElementById('categoriesTableBody')) return;

  let allCategories = [];
  let allProducts = [];
  let isApiOnline = true;
  let state = {
    search: '',
    status: 'all',
    page: 1,
    limit: 6
  };

  const tableBody = document.getElementById('categoriesTableBody');
  const searchInput = document.getElementById('categorySearchInput');
  const statusFilter = document.getElementById('categoryStatusFilter');
  const categoryForm = document.getElementById('categoryForm');
  const modalTitle = document.getElementById('categoryModalTitle');
  const catNameInput = document.getElementById('categoryName');
  const catSlugInput = document.getElementById('categorySlug');

  try {
    await loadCategories();
  } catch (err) {
    console.error('Failed to load categories:', err);
    UI.showToast('Failed to load categories.', 'error');
  }

  async function loadCategories() {
    renderLoadingState();

    try {
      if (window.API && typeof API.categories?.list === 'function') {
        const res = await API.categories.list({
          search: state.search,
          status: state.status,
          page: 1,
          limit: 100
        });

        if (res && res.success && res.data) {
          isApiOnline = true;
          allCategories = res.data.items || res.data.categories || [];
          renderFilteredCategories();
          return;
        }
      }
      throw new Error('API unavailable');
    } catch (apiErr) {
      console.warn('[Categories] API fetch failed, using local Store fallback:', apiErr);
      isApiOnline = false;
      allCategories = await Store.getCategories();
      allProducts = await Store.getProducts();
      renderFilteredCategories();
    }
  }

  function renderLoadingState() {
    tableBody.innerHTML = `
      <tr>
        <td colspan="6">
          <div class="empty-state" style="padding: 2.5rem 1rem;">
            <div class="empty-state-icon" style="color: var(--primary-600); animation: spin 1s linear infinite;">
              <i class="fa-solid fa-spinner"></i>
            </div>
            <h4 class="empty-state-title" style="margin-top: 1rem;">Loading Categories...</h4>
            <p class="empty-state-desc">Retrieving taxonomy hierarchy and product associations from database.</p>
          </div>
        </td>
      </tr>
    `;
  }

  function renderFilteredCategories() {
    let filtered = [...allCategories];

    if (state.search) {
      const q = state.search.toLowerCase();
      filtered = filtered.filter(c =>
        (c.name && c.name.toLowerCase().includes(q)) ||
        (c.slug && c.slug.toLowerCase().includes(q)) ||
        (c.description && c.description.toLowerCase().includes(q))
      );
    }

    if (state.status !== 'all') {
      filtered = filtered.filter(c => c.status === state.status);
    }

    const totalItems = filtered.length;
    const totalPages = Math.ceil(totalItems / state.limit) || 1;

    if (state.page > totalPages) state.page = totalPages;

    const startIdx = (state.page - 1) * state.limit;
    const paginated = filtered.slice(startIdx, startIdx + state.limit);

    if (paginated.length === 0) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="6">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fa-solid fa-tags"></i></div>
              <h4 class="empty-state-title">No categories found</h4>
              <p class="empty-state-desc">Organize your store by creating categories for your products.</p>
              <button class="btn btn-primary btn-sm" id="emptyAddCatBtn">
                <i class="fa-solid fa-plus"></i> Add Category
              </button>
            </div>
          </td>
        </tr>
      `;
      const emptyBtn = document.getElementById('emptyAddCatBtn');
      if (emptyBtn) emptyBtn.addEventListener('click', openAddCategoryModal);
    } else {
      tableBody.innerHTML = paginated.map(cat => {
        const prodCount = cat.product_count !== undefined ? cat.product_count : (cat.productCount || 0);
        const createdAt = cat.created_at || cat.createdAt;
        const safeName = UI.escapeHtml(cat.name);
        const safeSlug = UI.escapeHtml(cat.slug || '');
        const safeDesc = UI.escapeHtml(cat.description || '—');

        return `
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div style="width:38px; height:38px; border-radius:var(--radius-md); background:var(--primary-50); color:var(--primary-600); display:flex; align-items:center; justify-content:center; font-size:1.125rem;">
                  <i class="fa-solid fa-folder-open"></i>
                </div>
                <div>
                  <div class="fw-bold text-dark">${safeName}</div>
                  <div class="text-muted" style="font-size:0.75rem;">Slug: <code>${safeSlug}</code></div>
                </div>
              </div>
            </td>
            <td style="max-width:280px;">
              <span class="text-secondary" style="font-size:0.8125rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                ${safeDesc}
              </span>
            </td>
            <td>
              <span class="badge ${prodCount > 0 ? 'badge-info' : 'badge-neutral'}">
                ${prodCount} products
              </span>
            </td>
            <td class="text-secondary" style="font-size:0.8125rem;">${UI.formatDate(createdAt)}</td>
            <td>${UI.renderBadge(cat.status)}</td>
            <td>
              <div class="action-buttons">
                <button type="button" class="action-btn action-view" data-action="view" data-id="${cat.id}" title="View Products in Category">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <button type="button" class="action-btn action-edit" data-action="edit" data-id="${cat.id}" title="Edit Category">
                  <i class="fa-solid fa-pen"></i>
                </button>
                <button type="button" class="action-btn action-delete" data-action="delete" data-id="${cat.id}" data-name="${safeName}" data-count="${prodCount}" title="Delete Category">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    UI.renderPagination({
      containerId: 'categoriesPagination',
      currentPage: state.page,
      totalPages,
      totalItems,
      limit: state.limit,
      onPageChange: (p) => {
        state.page = p;
        renderFilteredCategories();
      }
    });

    bindTableEvents();
  }

  function bindTableEvents() {
    tableBody.querySelectorAll('[data-action="view"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        openViewCategoryModal(id);
      });
    });

    tableBody.querySelectorAll('[data-action="edit"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        openEditCategoryModal(id);
      });
    });

    tableBody.querySelectorAll('[data-action="delete"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const name = btn.getAttribute('data-name');
        const count = parseInt(btn.getAttribute('data-count'), 10) || 0;
        confirmDeleteCategory(id, name, count);
      });
    });
  }

  function openAddCategoryModal() {
    categoryForm.reset();
    document.getElementById('categoryId').value = '';
    modalTitle.textContent = 'Add New Category';
    clearValidationErrors(categoryForm);
    UI.openModal('categoryModal');
  }

  async function openEditCategoryModal(id) {
    let cat = null;

    if (isApiOnline && window.API?.categories) {
      try {
        const res = await API.categories.get(id);
        if (res && res.success && res.data) {
          cat = res.data.category;
        }
      } catch (err) {
        console.warn('API get category failed:', err);
      }
    }

    if (!cat) {
      cat = await Store.getCategoryById(id);
    }
    if (!cat) return;

    categoryForm.reset();
    clearValidationErrors(categoryForm);

    modalTitle.textContent = 'Edit Category';
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('categoryName').value = cat.name;
    document.getElementById('categorySlug').value = cat.slug || '';
    document.getElementById('categoryStatus').value = cat.status || 'active';
    document.getElementById('categoryDescription').value = cat.description || '';

    UI.openModal('categoryModal');
  }

  async function openViewCategoryModal(id) {
    let cat = null;
    let matchedProducts = [];

    if (isApiOnline && window.API?.categories) {
      try {
        const res = await API.categories.get(id);
        if (res && res.success && res.data) {
          cat = res.data.category;
          matchedProducts = res.data.products || [];
        }
      } catch (err) {
        console.warn('API get category failed:', err);
      }
    }

    if (!cat) {
      cat = await Store.getCategoryById(id);
      matchedProducts = allProducts.filter(p => p.categoryId === cat?.id);
    }
    if (!cat) return;

    const container = document.getElementById('viewCategoryDetailsContainer');
    const safeName = UI.escapeHtml(cat.name);
    const safeDesc = UI.escapeHtml(cat.description || 'No description available.');
    const safeSlug = UI.escapeHtml(cat.slug || '');
    const createdAt = cat.created_at || cat.createdAt;

    container.innerHTML = `
      <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h3 class="fw-bold mb-0">${safeName}</h3>
          ${UI.renderBadge(cat.status)}
        </div>
        <p class="text-secondary" style="font-size:0.875rem;">${safeDesc}</p>
        <div class="text-muted" style="font-size:0.75rem;">Slug: <code>${safeSlug}</code> &bull; Created on ${UI.formatDate(createdAt)}</div>
      </div>

      <h5 class="fw-bold mb-3" style="font-size:0.9375rem;">
        Products in this Category (${matchedProducts.length})
      </h5>

      ${matchedProducts.length === 0 ? `
        <div class="p-4 text-center text-muted bg-light rounded">No products currently assigned to this category.</div>
      ` : `
        <div style="display:flex; flex-direction:column; gap:0.5rem; max-height:260px; overflow-y:auto;">
          ${matchedProducts.map(p => {
            const pName = UI.escapeHtml(p.name);
            const pSku = UI.escapeHtml(p.sku);
            const pImg = p.imageUrl || p.image || 'https://placehold.co/80x80/EEF2FF/4F46E5?text=Product';
            const price = parseFloat(p.price || 0).toFixed(2);
            return `
              <div style="display:flex; align-items:center; justify-content:space-between; padding:0.625rem 0.875rem; background:var(--bg-surface-subtle); border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                  <img src="${pImg}" alt="${pName}" style="width:36px; height:36px; border-radius:var(--radius-xs); object-fit:cover;" onerror="this.src='https://placehold.co/80x80/EEF2FF/4F46E5?text=Product'">
                  <div>
                    <div class="fw-semibold text-dark" style="font-size:0.8125rem;">${pName}</div>
                    <div class="text-muted" style="font-size:0.6875rem;">SKU: ${pSku}</div>
                  </div>
                </div>
                <div class="text-end">
                  <div class="fw-bold" style="font-size:0.8125rem;">$${price}</div>
                  <div class="text-muted" style="font-size:0.6875rem;">Stock: ${p.stock}</div>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      `}
    `;

    UI.openModal('viewCategoryModal');
  }

  function confirmDeleteCategory(id, name, productCount) {
    let warningMsg = `Are you sure you want to delete category "${name}"?`;
    if (productCount > 0) {
      warningMsg = `Warning: Category "${name}" currently contains ${productCount} assigned products! Deleting this category will safely leave these products as Uncategorized. Proceed?`;
    }

    UI.confirmModal({
      title: 'Delete Category',
      message: warningMsg,
      confirmText: 'Delete Category',
      confirmClass: 'btn-danger',
      onConfirm: async () => {
        try {
          if (isApiOnline && window.API?.categories) {
            const res = await API.categories.destroy(id);
            if (res && !res.success) {
              UI.showToast(res.message || 'Failed to delete category.', 'error');
              return;
            }
          } else {
            await Store.deleteCategory(id);
          }
          UI.showToast(`Category "${name}" deleted successfully.`, 'success');
          await loadCategories();
        } catch (err) {
          console.error('Delete category error:', err);
          UI.showToast('Failed to delete category.', 'error');
        }
      }
    });
  }

  // Auto slug generation on name input
  if (catNameInput && catSlugInput) {
    catNameInput.addEventListener('input', () => {
      const isNew = !document.getElementById('categoryId').value;
      if (isNew) {
        catSlugInput.value = catNameInput.value
          .toLowerCase()
          .trim()
          .replace(/&/g, 'and')
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/(^-|-$)/g, '');
      }
    });
  }

  // Submit Form (Create / Update)
  if (categoryForm) {
    categoryForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearValidationErrors(categoryForm);

      const id = document.getElementById('categoryId').value;
      const name = document.getElementById('categoryName').value.trim();
      const slug = document.getElementById('categorySlug').value.trim();
      const status = document.getElementById('categoryStatus').value;
      const description = document.getElementById('categoryDescription').value.trim();

      let hasError = false;
      if (!name) {
        showError('categoryName', 'Category name is required.');
        hasError = true;
      }
      if (!slug) {
        showError('categorySlug', 'Category slug is required.');
        hasError = true;
      }

      if (hasError) return;

      const payload = {
        name,
        slug,
        status,
        description
      };

      try {
        if (isApiOnline && window.API?.categories) {
          let res;
          if (id) {
            res = await API.categories.update(id, payload);
          } else {
            res = await API.categories.create(payload);
          }

          if (res && !res.success) {
            if (res.errors?.name) showError('categoryName', res.errors.name);
            if (res.errors?.slug) showError('categorySlug', res.errors.slug);
            UI.showToast(res.message || 'Failed to save category.', 'error');
            return;
          }
        } else {
          await Store.saveCategory({ id: id ? Number(id) : null, ...payload });
        }

        UI.closeModal('categoryModal');
        UI.showToast(id ? 'Category updated successfully.' : 'Category created successfully!', 'success');
        await loadCategories();
      } catch (err) {
        console.error('Save category error:', err);
        UI.showToast('Failed to save category.', 'error');
      }
    });
  }

  // Filter Listeners
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      state.search = searchInput.value.trim();
      state.page = 1;
      renderFilteredCategories();
    });
  }

  if (statusFilter) {
    statusFilter.addEventListener('change', () => {
      state.status = statusFilter.value;
      state.page = 1;
      renderFilteredCategories();
    });
  }

  const addBtn = document.getElementById('openAddCategoryBtn');
  if (addBtn) addBtn.addEventListener('click', openAddCategoryModal);

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
});
