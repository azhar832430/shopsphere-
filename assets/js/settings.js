/**
 * SHOPSPHERE - SETTINGS & SYSTEM CONFIGURATION CONTROLLER
 * General Settings, Tax Rates, Currency, Admin Profile & Demo Data Factory Reset
 */

document.addEventListener('DOMContentLoaded', async () => {
  if (!document.getElementById('generalSettingsForm')) return;

  const generalForm = document.getElementById('generalSettingsForm');
  const profileForm = document.getElementById('adminProfileForm');
  const resetDemoBtn = document.getElementById('resetDemoDataBtn');
  const exportDataBtn = document.getElementById('exportJsonDataBtn');
  const clearDataBtn = document.getElementById('clearAllDataBtn');

  try {
    const settings = await Store.getSettings();
    populateSettings(settings);
  } catch (err) {
    console.error('Failed to load settings:', err);
    UI.showToast('Failed to load system settings.', 'error');
  }

  function populateSettings(s) {
    // Store Information
    document.getElementById('storeName').value = s.storeName || '';
    document.getElementById('storeTagline').value = s.storeTagline || '';
    document.getElementById('currency').value = s.currency || 'USD';
    document.getElementById('currencySymbol').value = s.currencySymbol || '$';
    document.getElementById('taxRate').value = s.taxRate || 8.5;
    document.getElementById('shippingFlat').value = s.shippingFlat || 15.00;
    document.getElementById('freeShippingThreshold').value = s.freeShippingThreshold || 100.00;
    document.getElementById('lowStockThreshold').value = s.lowStockThreshold || 10;
    document.getElementById('contactEmail').value = s.contactEmail || '';
    document.getElementById('contactPhone').value = s.contactPhone || '';
    document.getElementById('storeAddress').value = s.address || '';

    // Admin Profile
    if (s.adminUser) {
      document.getElementById('adminName').value = s.adminUser.name || '';
      document.getElementById('adminEmail').value = s.adminUser.email || '';
      document.getElementById('adminRole').value = s.adminUser.role || '';
      document.getElementById('adminAvatarUrl').value = s.adminUser.avatar || '';
      const avatarImg = document.getElementById('adminAvatarPreview');
      if (avatarImg && s.adminUser.avatar) {
        avatarImg.src = s.adminUser.avatar;
      }
    }
  }

  // Save General Settings
  if (generalForm) {
    generalForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const storeName = document.getElementById('storeName').value.trim();
      const storeTagline = document.getElementById('storeTagline').value.trim();
      const currency = document.getElementById('currency').value;
      const currencySymbol = document.getElementById('currencySymbol').value.trim();
      const taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
      const shippingFlat = parseFloat(document.getElementById('shippingFlat').value) || 0;
      const freeShippingThreshold = parseFloat(document.getElementById('freeShippingThreshold').value) || 0;
      const lowStockThreshold = parseInt(document.getElementById('lowStockThreshold').value, 10) || 10;
      const contactEmail = document.getElementById('contactEmail').value.trim();
      const contactPhone = document.getElementById('contactPhone').value.trim();
      const address = document.getElementById('storeAddress').value.trim();

      await Store.saveSettings({
        storeName,
        storeTagline,
        currency,
        currencySymbol,
        taxRate,
        shippingFlat,
        freeShippingThreshold,
        lowStockThreshold,
        contactEmail,
        contactPhone,
        address
      });

      UI.showToast('Store configuration saved successfully.', 'success');
    });
  }

  // Save Admin Profile
  if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const name = document.getElementById('adminName').value.trim();
      const email = document.getElementById('adminEmail').value.trim();
      const role = document.getElementById('adminRole').value.trim();
      const avatar = document.getElementById('adminAvatarUrl').value.trim();

      const settings = await Store.getSettings();
      settings.adminUser = { name, email, role, avatar };

      await Store.saveSettings(settings);
      UI.showToast('Admin profile updated.', 'success');
    });

    const avatarInput = document.getElementById('adminAvatarUrl');
    const avatarImg = document.getElementById('adminAvatarPreview');
    if (avatarInput && avatarImg) {
      avatarInput.addEventListener('input', () => {
        avatarImg.src = avatarInput.value.trim() || 'https://placehold.co/100x100?text=Avatar';
      });
    }
  }

  // Reset to Demo Defaults
  if (resetDemoBtn) {
    resetDemoBtn.addEventListener('click', () => {
      UI.confirmModal({
        title: 'Reset Demo Data',
        message: 'This will restore all default products, categories, orders, customers, and system settings to original sample records. Continue?',
        confirmText: 'Reset Factory Demo Data',
        confirmClass: 'btn-danger',
        onConfirm: () => {
          Store.resetData();
          UI.showToast('Demo data restored successfully. Reloading...', 'success');
          setTimeout(() => window.location.reload(), 900);
        }
      });
    });
  }

  // Export JSON Backup
  if (exportDataBtn) {
    exportDataBtn.addEventListener('click', () => {
      const exportObject = {
        products: JSON.parse(localStorage.getItem(STORAGE_KEYS.PRODUCTS) || '[]'),
        categories: JSON.parse(localStorage.getItem(STORAGE_KEYS.CATEGORIES) || '[]'),
        customers: JSON.parse(localStorage.getItem(STORAGE_KEYS.CUSTOMERS) || '[]'),
        orders: JSON.parse(localStorage.getItem(STORAGE_KEYS.ORDERS) || '[]'),
        settings: JSON.parse(localStorage.getItem(STORAGE_KEYS.SETTINGS) || '{}'),
        exportedAt: new Date().toISOString()
      };

      const dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(exportObject, null, 2));
      const downloadAnchor = document.createElement('a');
      downloadAnchor.setAttribute('href', dataStr);
      downloadAnchor.setAttribute('download', `shopsphere-backup-${new Date().toISOString().split('T')[0]}.json`);
      document.body.appendChild(downloadAnchor);
      downloadAnchor.click();
      downloadAnchor.remove();

      UI.showToast('System data exported as JSON.', 'info');
    });
  }

  // Clear All Data
  if (clearDataBtn) {
    clearDataBtn.addEventListener('click', () => {
      UI.confirmModal({
        title: 'Clear All Store Records',
        message: 'Are you sure you want to completely erase all products, categories, orders, and customer records? You will start with an empty store.',
        confirmText: 'Wipe All Data',
        confirmClass: 'btn-danger',
        onConfirm: () => {
          localStorage.setItem(STORAGE_KEYS.PRODUCTS, '[]');
          localStorage.setItem(STORAGE_KEYS.CATEGORIES, '[]');
          localStorage.setItem(STORAGE_KEYS.CUSTOMERS, '[]');
          localStorage.setItem(STORAGE_KEYS.ORDERS, '[]');
          UI.showToast('All store records erased.', 'warning');
          setTimeout(() => window.location.reload(), 800);
        }
      });
    });
  }
});

