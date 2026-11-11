/**
 * SHOPSPHERE - DASHBOARD CONTROLLER
 * Metrics, Sales Trends (Chart.js), Order Breakdown, Top Products, Low Stock
 */

document.addEventListener('DOMContentLoaded', async () => {
  // Only execute on dashboard page
  if (!document.getElementById('dashboardMetricsGrid')) return;

  let salesChart = null;
  let statusChart = null;

  try {
    await loadDashboardData();
  } catch (error) {
    console.error('Dashboard initialization failed:', error);
    UI.showToast('Failed to load dashboard metrics.', 'error');
  }

  /**
   * Main data loader
   */
  async function loadDashboardData() {
    const stats = await Store.getStats();
    const settings = await Store.getSettings();

    // Augment with real-time MySQL product stats if API is available
    try {
      if (window.API && API.products && typeof API.products.stats === 'function') {
        const pStatsRes = await API.products.stats();
        if (pStatsRes && pStatsRes.success && pStatsRes.data) {
          const d = pStatsRes.data;
          stats.totalProducts = d.total_products;
          stats.lowStockCount = d.low_stock;
          if (Array.isArray(d.top_low_stock) && d.top_low_stock.length > 0) {
            stats.lowStockProducts = d.top_low_stock.map(p => ({
              id: p.id,
              name: p.name,
              sku: p.sku,
              stock: p.stock,
              categoryName: p.category_name || 'Uncategorized',
              imageUrl: p.image || 'https://placehold.co/80x80/EEF2FF/4F46E5?text=Product'
            }));
          }
        }
      }
    } catch (apiErr) {
      console.warn('[Dashboard] Could not fetch MySQL product metrics, using Store fallback:', apiErr);
    }

    // Augment with real-time MySQL customer stats if API is available
    try {
      if (window.API && API.customers && typeof API.customers.stats === 'function') {
        const cStatsRes = await API.customers.stats();
        if (cStatsRes && cStatsRes.success && cStatsRes.data) {
          const cd = cStatsRes.data;
          stats.totalCustomers = cd.total_customers;
          stats.activeCustomers = cd.active_customers;
          stats.newCustomers = cd.new_customers;
        }
      }
    } catch (apiErr) {
      console.warn('[Dashboard] Could not fetch MySQL customer metrics, using Store fallback:', apiErr);
    }

    // Augment with real-time MySQL orders and revenue stats if API is available
    try {
      if (window.API && API.orders && typeof API.orders.stats === 'function') {
        const oStatsRes = await API.orders.stats();
        if (oStatsRes && oStatsRes.success && oStatsRes.data) {
          const od = oStatsRes.data;
          stats.totalOrders = od.total_orders;
          stats.totalRevenue = od.total_revenue;
          if (od.order_statuses) {
            stats.orderStatuses = { ...stats.orderStatuses, ...od.order_statuses };
          }
          if (Array.isArray(od.recent_orders) && od.recent_orders.length > 0) {
            stats.recentOrders = od.recent_orders;
          }
        }
      }
    } catch (apiErr) {
      console.warn('[Dashboard] Could not fetch MySQL order metrics, using Store fallback:', apiErr);
    }

    // 1. Render Top KPI Cards
    renderStatCards(stats, settings);

    // 2. Render Charts
    renderSalesChart(stats);
    renderStatusChart(stats);

    // 3. Render Recent Orders Table
    renderRecentOrders(stats.recentOrders, settings);

    // 4. Render Top Selling Products
    renderTopProducts(stats.topProducts, settings);

    // 5. Render Low Stock Alerts
    renderLowStock(stats.lowStockProducts, settings);

    // 6. Render Recent Activities
    renderRecentActivities();
  }

  /**
   * Render KPI Summary Cards
   */
  function renderStatCards(stats, settings) {
    const container = document.getElementById('dashboardMetricsGrid');
    if (!container) return;

    container.innerHTML = `
      <!-- Total Revenue -->
      <div class="stat-card">
        <div class="stat-card-info">
          <span class="stat-card-title">Total Revenue</span>
          <span class="stat-card-value">${UI.formatCurrency(stats.totalRevenue, settings.currencySymbol)}</span>
          <span class="stat-card-trend up">
            <i class="fa-solid fa-arrow-trend-up"></i> +14.2%
            <span class="trend-period">vs last month</span>
          </span>
        </div>
        <div class="stat-card-icon primary">
          <i class="fa-solid fa-dollar-sign"></i>
        </div>
      </div>

      <!-- Total Orders -->
      <div class="stat-card">
        <div class="stat-card-info">
          <span class="stat-card-title">Total Orders</span>
          <span class="stat-card-value">${stats.totalOrders}</span>
          <span class="stat-card-trend up">
            <i class="fa-solid fa-arrow-trend-up"></i> +8.5%
            <span class="trend-period">vs last month</span>
          </span>
        </div>
        <div class="stat-card-icon success">
          <i class="fa-solid fa-cart-shopping"></i>
        </div>
      </div>

      <!-- Total Products -->
      <div class="stat-card">
        <div class="stat-card-info">
          <span class="stat-card-title">Catalog Products</span>
          <span class="stat-card-value">${stats.totalProducts}</span>
          <span class="stat-card-trend ${stats.lowStockCount > 0 ? 'down' : 'up'}">
            <i class="fa-solid ${stats.lowStockCount > 0 ? 'fa-triangle-exclamation' : 'fa-check'}"></i> 
            ${stats.lowStockCount} low stock
          </span>
        </div>
        <div class="stat-card-icon warning">
          <i class="fa-solid fa-box-archive"></i>
        </div>
      </div>

      <!-- Total Customers -->
      <div class="stat-card">
        <div class="stat-card-info">
          <span class="stat-card-title">Active Customers</span>
          <span class="stat-card-value">${stats.totalCustomers}</span>
          <span class="stat-card-trend up">
            <i class="fa-solid fa-arrow-trend-up"></i> +18.0%
            <span class="trend-period">new accounts</span>
          </span>
        </div>
        <div class="stat-card-icon info">
          <i class="fa-solid fa-users"></i>
        </div>
      </div>
    `;
  }

  /**
   * Render Chart.js Sales Overview Area Chart
   */
  function renderSalesChart(stats) {
    const ctx = document.getElementById('salesOverviewChart');
    if (!ctx) return;
    if (typeof Chart === 'undefined') {
      console.warn('[Dashboard] Chart.js library unavailable (offline/unreachable CDN). Skipping sales chart.');
      return;
    }

    if (salesChart) salesChart.destroy();

    salesChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: stats.chartLabels,
        datasets: [
          {
            label: 'Current Period ($)',
            data: stats.currentPeriodSales,
            borderColor: '#4F46E5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            fill: true,
            tension: 0.35,
            borderWidth: 2.5,
            pointBackgroundColor: '#4F46E5',
            pointRadius: 4,
            pointHoverRadius: 6
          },
          {
            label: 'Previous Period ($)',
            data: stats.previousPeriodSales,
            borderColor: '#94A3B8',
            borderDash: [5, 5],
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'top',
            labels: {
              boxWidth: 12,
              font: { family: 'Plus Jakarta Sans', size: 12, weight: 600 }
            }
          },
          tooltip: {
            backgroundColor: '#0F172A',
            titleFont: { family: 'Plus Jakarta Sans', size: 13, weight: 700 },
            bodyFont: { family: 'Inter', size: 12 },
            padding: 10,
            cornerRadius: 8,
            callbacks: {
              label: function(context) {
                return ` ${context.dataset.label}: $${context.parsed.y.toLocaleString()}`;
              }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false }
          },
          y: {
            grid: { color: 'rgba(226, 232, 240, 0.6)' },
            ticks: {
              callback: function(val) {
                return '$' + (val / 1000) + 'k';
              }
            }
          }
        }
      }
    });

    // Time filter pills
    document.querySelectorAll('[data-chart-period]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('[data-chart-period]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const period = btn.getAttribute('data-chart-period');
        if (period === '30d') {
          salesChart.data.labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
          salesChart.data.datasets[0].data = [4200, 6800, 8100, 9400];
          salesChart.data.datasets[1].data = [3500, 5200, 7000, 7800];
        } else if (period === '7d') {
          salesChart.data.labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
          salesChart.data.datasets[0].data = [1200, 980, 1600, 2100, 1850, 2400, 2900];
          salesChart.data.datasets[1].data = [1000, 800, 1200, 1500, 1600, 2000, 2200];
        } else {
          salesChart.data.labels = stats.chartLabels;
          salesChart.data.datasets[0].data = stats.currentPeriodSales;
          salesChart.data.datasets[1].data = stats.previousPeriodSales;
        }
        salesChart.update();
      });
    });
  }

  /**
   * Render Chart.js Order Breakdown Doughnut Chart
   */
  function renderStatusChart(stats) {
    const ctx = document.getElementById('orderStatusChart');
    if (!ctx) return;
    if (typeof Chart === 'undefined') {
      console.warn('[Dashboard] Chart.js library unavailable (offline/unreachable CDN). Skipping status chart.');
      return;
    }

    if (statusChart) statusChart.destroy();

    const statuses = stats.orderStatuses;

    statusChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Delivered', 'Shipped', 'Processing', 'Pending', 'Cancelled'],
        datasets: [{
          data: [
            statuses.delivered,
            statuses.shipped,
            statuses.processing,
            statuses.pending,
            statuses.cancelled
          ],
          backgroundColor: [
            '#10B981', // Delivered (Emerald)
            '#3B82F6', // Shipped (Blue)
            '#8B5CF6', // Processing (Purple)
            '#F59E0B', // Pending (Amber)
            '#EF4444'  // Cancelled (Red)
          ],
          borderWidth: 2,
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 10,
              padding: 12,
              font: { family: 'Plus Jakarta Sans', size: 11, weight: 600 }
            }
          }
        },
        cutout: '68%'
      }
    });
  }

  /**
   * Render Recent Orders Table
   */
  function renderRecentOrders(recentOrders, settings) {
    const tbody = document.getElementById('recentOrdersTableBody');
    if (!tbody) return;

    if (!recentOrders || recentOrders.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No recent orders recorded.</td></tr>`;
      return;
    }

    tbody.innerHTML = recentOrders.map(order => `
      <tr>
        <td>
          <a href="orders.html?view=${order.id}" class="fw-bold text-primary">#${UI.escapeHtml(order.orderNumber)}</a>
        </td>
        <td>
          <span class="fw-semibold">${UI.escapeHtml(order.customerName)}</span>
        </td>
        <td class="text-secondary">${UI.formatDate(order.orderDate)}</td>
        <td class="fw-bold">${UI.formatCurrency(order.totalAmount, settings.currencySymbol)}</td>
        <td>${UI.renderBadge(order.orderStatus)}</td>
        <td>
          <div class="action-buttons">
            <a href="orders.html?view=${order.id}" class="action-btn action-view" title="View Order Details">
              <i class="fa-solid fa-eye"></i>
            </a>
          </div>
        </td>
      </tr>
    `).join('');
  }

  /**
   * Render Top Selling Products Widget
   */
  function renderTopProducts(topProducts, settings) {
    const container = document.getElementById('topProductsList');
    if (!container) return;

    if (!topProducts || topProducts.length === 0) {
      container.innerHTML = `<div class="text-muted text-center py-3">No top products data yet.</div>`;
      return;
    }

    container.innerHTML = topProducts.map((p, idx) => `
      <div class="top-product-item">
        <span class="product-rank-badge">#${idx + 1}</span>
        <img src="${p.imageUrl}" alt="${UI.escapeHtml(p.name)}" class="top-product-thumb" onerror="this.src='https://placehold.co/80x80/EEF2FF/4F46E5?text=Product'">
        <div class="top-product-meta">
          <div class="top-product-name" title="${UI.escapeHtml(p.name)}">${UI.escapeHtml(p.name)}</div>
          <div class="top-product-category">${UI.escapeHtml(p.categoryName)}</div>
        </div>
        <div class="top-product-sales">
          <div class="top-product-revenue">${UI.formatCurrency(p.revenueGenerated, settings.currencySymbol)}</div>
          <div class="top-product-count">${p.totalSold} sold</div>
        </div>
      </div>
    `).join('');
  }

  /**
   * Render Low Stock Alert Products
   */
  function renderLowStock(lowStockProducts) {
    const container = document.getElementById('lowStockList');
    if (!container) return;

    if (!lowStockProducts || lowStockProducts.length === 0) {
      container.innerHTML = `
        <div class="text-center py-3 text-success" style="font-size:0.875rem;">
          <i class="fa-solid fa-circle-check mb-1" style="font-size:1.25rem;"></i>
          <div>All catalog products are adequately stocked!</div>
        </div>
      `;
      return;
    }

    container.innerHTML = lowStockProducts.slice(0, 4).map(p => `
      <div class="low-stock-item ${p.stock <= 3 ? 'danger-stock' : ''}">
        <div class="low-stock-meta">
          <img src="${p.imageUrl}" alt="${UI.escapeHtml(p.name)}" class="low-stock-thumb" onerror="this.src='https://placehold.co/80x80/EEF2FF/4F46E5?text=Product'">
          <div>
            <div class="low-stock-name">${UI.escapeHtml(p.name)}</div>
            <div class="low-stock-sku">SKU: ${UI.escapeHtml(p.sku)}</div>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge ${p.stock === 0 ? 'badge-danger' : 'badge-warning'}">
            ${p.stock} units left
          </span>
          <a href="products.html?edit=${p.id}" class="btn btn-outline btn-sm" title="Restock">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </a>
        </div>
      </div>
    `).join('');
  }

  /**
   * Render Recent Activities Feed
   */
  async function renderRecentActivities() {
    const container = document.getElementById('recentActivityFeed');
    if (!container) return;

    const activities = await Store.getActivities(5);

    const icons = {
      order: 'fa-solid fa-cart-shopping',
      product: 'fa-solid fa-box',
      customer: 'fa-solid fa-user-plus',
      stock: 'fa-solid fa-triangle-exclamation',
      category: 'fa-solid fa-tags',
      system: 'fa-solid fa-gear'
    };

    container.innerHTML = activities.map(act => `
      <div class="timeline-item">
        <div class="timeline-icon-box">
          <i class="${icons[act.type] || 'fa-solid fa-bell'}"></i>
        </div>
        <div class="timeline-content">
          <div class="timeline-text">${act.text}</div>
          <div class="timeline-time">${act.timestamp}</div>
        </div>
      </div>
    `).join('');
  }
});

