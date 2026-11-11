/**
 * SHOPSPHERE - CLIENT API SERVICE & FETCH HELPER
 * Phase 3: Backend & API Foundation
 * 
 * Centralized Fetch API abstraction handling:
 * - Credentials & session persistence
 * - CSRF token synchronization
 * - Standardized JSON envelope parsing
 * - Automatic error toasts & 401 Unauthorized handling
 */

const API = {
  // Relative API root endpoint
  baseURL: 'api',

  // Active CSRF token in client memory
  csrfToken: sessionStorage.getItem('shopsphere_csrf_token') || null,

  /**
   * Set or update active CSRF token.
   */
  setCsrfToken(token) {
    this.csrfToken = token;
    if (token) {
      sessionStorage.setItem('shopsphere_csrf_token', token);
    } else {
      sessionStorage.removeItem('shopsphere_csrf_token');
    }
  },

  /**
   * Core request engine using Fetch API.
   *
   * @param {string} endpoint e.g. 'health.php' or 'auth/login.php'
   * @param {object} options standard fetch options
   * @returns {Promise<{success: boolean, message: string, data?: object, errors?: object}>}
   */
  async request(endpoint, options = {}) {
    const url = `${this.baseURL}/${endpoint.replace(/^\//, '')}`;

    const headers = {
      'Accept': 'application/json',
      ...(options.headers || {})
    };

    // Attach JSON content-type if body is provided
    if (options.body && typeof options.body === 'string' && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    // Attach CSRF token on state-changing requests
    if (this.csrfToken && ['POST', 'PUT', 'DELETE', 'PATCH'].includes((options.method || 'GET').toUpperCase())) {
      headers['X-CSRF-Token'] = this.csrfToken;
    }

    const config = {
      credentials: 'same-origin', // Preserves PHP session cookie
      ...options,
      headers
    };

    try {
      const response = await fetch(url, config);
      let payload;

      try {
        payload = await response.json();
      } catch (err) {
        payload = {
          success: false,
          message: `Server returned non-JSON response (HTTP ${response.status}).`,
          errors: { status: response.status }
        };
      }

      // Automatically store returned CSRF token if present
      if (payload?.data?.csrf_token) {
        this.setCsrfToken(payload.data.csrf_token);
      }

      // Handle specific HTTP Status Codes
      if (response.status === 401) {
        this.setCsrfToken(null);
        if (window.UI && typeof UI.showToast === 'function') {
          UI.showToast(payload.message || 'Session expired. Please sign in again.', 'warning');
        }
      } else if (!response.ok && !payload.success) {
        // Log API validation/error messages
        console.warn(`[ShopSphere API Error ${response.status}]`, payload);
      }

      return payload;

    } catch (networkError) {
      console.error('[ShopSphere API Network Failure]', networkError);
      const fallback = {
        success: false,
        message: 'Unable to reach backend server. Please verify Apache/PHP is running.',
        errors: { network: networkError.message }
      };

      if (window.UI && typeof UI.showToast === 'function') {
        UI.showToast(fallback.message, 'error');
      }

      return fallback;
    }
  },

  /**
   * Helper for GET requests.
   */
  async get(endpoint, params = null) {
    let query = '';
    if (params) {
      query = '?' + new URLSearchParams(params).toString();
    }
    return this.request(endpoint + query, { method: 'GET' });
  },

  /**
   * Helper for POST requests.
   */
  async post(endpoint, data = {}) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  /**
   * Health Check Endpoint.
   */
  async health() {
    return this.get('health.php');
  },

  /**
   * Authentication Sub-module
   */
  auth: {
    /**
     * Submit login credentials to POST /api/auth/login.php
     */
    async login(usernameOrEmail, password) {
      const res = await API.post('auth/login.php', {
        username_or_email: usernameOrEmail,
        password: password
      });

      if (res.success && res.data?.user) {
        sessionStorage.setItem('shopsphere_auth_user', JSON.stringify(res.data.user));
      }

      return res;
    },

    /**
     * Terminate active session via POST /api/auth/logout.php
     */
    async logout() {
      const res = await API.post('auth/logout.php', {});
      API.setCsrfToken(null);
      sessionStorage.removeItem('shopsphere_auth_user');
      return res;
    },

    /**
     * Fetch active session user via GET /api/auth/me.php
     */
    async me() {
      const res = await API.get('auth/me.php');
      if (res.success && res.data?.user) {
        sessionStorage.setItem('shopsphere_auth_user', JSON.stringify(res.data.user));
      } else {
        sessionStorage.removeItem('shopsphere_auth_user');
      }
      return res;
    },

    /**
     * Retrieve cached session user without network roundtrip.
     */
    getCachedUser() {
      try {
        const raw = sessionStorage.getItem('shopsphere_auth_user');
        return raw ? JSON.parse(raw) : null;
      } catch (e) {
        return null;
      }
    }
  },

  /**
   * Product Management Sub-module (Phase 4: Product Management CRUD)
   */
  products: {
    /**
     * Fetch paginated list of products with filtering, search, and sorting.
     * @param {object} params { page, limit, search, category_id, stock_status, sort, order }
     */
    async list(params = {}) {
      return API.get('products/index.php', params);
    },

    /**
     * Fetch single product by ID.
     * @param {number|string} id
     */
    async get(id) {
      return API.get('products/show.php', { id });
    },

    /**
     * Create a new product. Accepts either FormData (with file) or plain object/JSON.
     * @param {FormData|object} payload
     */
    async create(payload) {
      if (typeof FormData !== 'undefined' && payload instanceof FormData) {
        return API.request('products/store.php', {
          method: 'POST',
          body: payload
        });
      }
      return API.post('products/store.php', payload);
    },

    /**
     * Update an existing product. Accepts either FormData or plain object/JSON.
     * @param {number|string} id
     * @param {FormData|object} payload
     */
    async update(id, payload) {
      if (typeof FormData !== 'undefined' && payload instanceof FormData) {
        if (!payload.has('id')) {
          payload.append('id', id);
        }
        return API.request(`products/update.php?id=${encodeURIComponent(id)}`, {
          method: 'POST',
          body: payload
        });
      }
      const data = { ...payload, id };
      return API.post(`products/update.php?id=${encodeURIComponent(id)}`, data);
    },

    /**
     * Delete or archive a product.
     * @param {number|string} id
     */
    async delete(id) {
      return API.post('products/delete.php', { id });
    },

    /**
     * Retrieve aggregate product metrics for dashboard & stock telemetry.
     */
    async stats() {
      return API.get('products/stats.php');
    }
  },

  /**
   * Customer Management Sub-module (Phase 5: Customer Management CRUD)
   */
  customers: {
    /**
     * Fetch paginated list of customers with search, city/status filters, and sorting.
     * @param {object} params { page, limit, search, city, status, sort, order }
     */
    async list(params = {}) {
      return API.get('customers/index.php', params);
    },

    /**
     * Fetch customer dossier and order history by ID.
     * @param {number|string} id
     */
    async get(id) {
      return API.get('customers/show.php', { id });
    },

    /**
     * Create a new customer record.
     * @param {object} payload
     */
    async create(payload) {
      return API.post('customers/store.php', payload);
    },

    /**
     * Update an existing customer record.
     * @param {number|string} id
     * @param {object} payload
     */
    async update(id, payload) {
      const data = { ...payload, id };
      return API.post(`customers/update.php?id=${encodeURIComponent(id)}`, data);
    },

    /**
     * Delete or archive a customer.
     * @param {number|string} id
     */
    async delete(id) {
      return API.post('customers/delete.php', { id });
    },

    /**
     * Retrieve aggregate customer metrics for dashboard telemetry.
     */
    async stats() {
      return API.get('customers/stats.php');
    }
  },

  /**
   * Order Management Sub-module (Phase 6: Order Management CRUD)
   */
  orders: {
    /**
     * Fetch paginated list of orders with search, status filters, and sorting.
     * @param {object} params { page, limit, search, order_status, payment_status, date_from, date_to, sort, order }
     */
    async list(params = {}) {
      return API.get('orders/index.php', params);
    },

    /**
     * Fetch detailed order invoice and line items by ID or order number.
     * @param {number|string} id
     */
    async get(id) {
      return API.get('orders/show.php', { id });
    },

    /**
     * Create a new order with atomic stock deduction and multi-item line repeater.
     * @param {object} payload { customer_id, items, payment_method, payment_status, order_status, notes, tax, shipping, discount }
     */
    async create(payload) {
      return API.post('orders/store.php', payload);
    },

    /**
     * Update order status, payment status, and notes.
     * @param {number|string} id
     * @param {object} payload { order_status, payment_status, notes }
     */
    async update(id, payload) {
      const data = { ...payload, id };
      return API.post(`orders/update.php?id=${encodeURIComponent(id)}`, data);
    },

    /**
     * Safely cancel an order and restore product inventory stock exactly once.
     * @param {number|string} id
     */
    async cancel(id) {
      return API.post('orders/cancel.php', { id });
    },

    /**
     * Retrieve aggregate order metrics & recent orders for dashboard.
     */
    async stats() {
      return API.get('orders/stats.php');
    }
  },

  /**
   * Category Management Sub-module (Phase 7: Database-Connected Category CRUD)
   */
  categories: {
    /**
     * Fetch list of categories with search, status filter, and dynamic product counts.
     * @param {object} params { search, status, page, limit, sort, order }
     */
    async list(params = {}) {
      return API.get('categories/index.php', params);
    },

    /**
     * Fetch category by ID or slug with assigned products list.
     * @param {number|string} id
     */
    async get(id) {
      return API.get('categories/show.php', { id });
    },

    /**
     * Create a new category.
     * @param {object} payload { name, slug, description, status }
     */
    async create(payload) {
      return API.post('categories/store.php', payload);
    },

    /**
     * Update an existing category.
     * @param {number|string} id
     * @param {object} payload { name, slug, description, status }
     */
    async update(id, payload) {
      const data = { ...payload, id };
      return API.post(`categories/update.php?id=${encodeURIComponent(id)}`, data);
    },

    /**
     * Delete a category (reassigns assigned products to NULL).
     * @param {number|string} id
     */
    async destroy(id) {
      return API.post('categories/destroy.php', { id });
    },

    /**
     * Alias for destroy.
     * @param {number|string} id
     */
    async delete(id) {
      return this.destroy(id);
    }
  }
};

// Expose globally
window.API = API;

