/**
 * SHOPSPHERE - CLIENT-SIDE DATA STORE & MOCK API REPOSITORY
 * Phase 1: Frontend Architecture
 * 
 * ARCHITECTURE NOTE FOR FUTURE PHP / MYSQL INTEGRATION:
 * -------------------------------------------------------------
 * This module implements a Promise-based Repository Pattern. In Phase 2,
 * the internal localStorage calls inside each method (e.g. `getProducts()`)
 * can be replaced with native `fetch('/api/products.php')` calls with NO
 * modifications required to the UI page controllers!
 *
 * Example Future PHP migration:
 * async getProducts() {
 *   const response = await fetch('/api/products/read.php');
 *   return await response.json();
 * }
 */

const STORAGE_KEYS = {
  PRODUCTS: 'shopsphere_products_v1',
  CATEGORIES: 'shopsphere_categories_v1',
  CUSTOMERS: 'shopsphere_customers_v1',
  ORDERS: 'shopsphere_orders_v1',
  SETTINGS: 'shopsphere_settings_v1',
  ACTIVITIES: 'shopsphere_activities_v1',
  INIT_FLAG: 'shopsphere_initialized_v1'
};

// Default seed categories
const DEFAULT_CATEGORIES = [
  { id: 1, name: 'Smartphones & Tablets', slug: 'smartphones-tablets', description: 'Next-generation flagship phones and pro tablets', status: 'active', createdAt: '2026-01-15' },
  { id: 2, name: 'Laptops & Computers', slug: 'laptops-computers', description: 'Ultra-thin laptops, desktop workstations, and displays', status: 'active', createdAt: '2026-01-18' },
  { id: 3, name: 'Audio & Headphones', slug: 'audio-headphones', description: 'Studio monitors, ANC wireless headphones and earbuds', status: 'active', createdAt: '2026-01-20' },
  { id: 4, name: 'Wearables & Smartwatches', slug: 'wearables-smartwatches', description: 'Health monitoring smartwatches and fitness trackers', status: 'active', createdAt: '2026-02-01' },
  { id: 5, name: 'Smart Home & IoT', slug: 'smart-home-iot', description: 'Intelligent security cameras, ambient lighting and hubs', status: 'active', createdAt: '2026-02-10' },
  { id: 6, name: 'Gaming & VR', slug: 'gaming-vr', description: 'Consoles, mechanical keyboards, gaming mice and VR headsets', status: 'active', createdAt: '2026-02-15' },
  { id: 7, name: 'Accessories & Cables', slug: 'accessories-cables', description: 'GaN fast chargers, Thunderbolt docks, and premium sleeves', status: 'active', createdAt: '2026-02-22' }
];

// Default seed products (realistic specs, prices, and stock)
const DEFAULT_PRODUCTS = [
  {
    id: 1,
    name: 'SpherePhone 15 Pro Max',
    sku: 'SP-15PM-256',
    categoryId: 1,
    description: 'Titanium aerospace enclosure with 6.7-inch Super Retina OLED, 48MP triple telephoto optics, and all-day battery life.',
    price: 1199.00,
    stock: 24,
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-02-01'
  },
  {
    id: 2,
    name: 'AeroBook Ultra M3 16"',
    sku: 'AB-M3-16-1TB',
    categoryId: 2,
    description: '16-core workstation notebook with Liquid Retina XDR screen, 36GB unified memory, and 22-hour battery.',
    price: 2499.00,
    stock: 12,
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-02-05'
  },
  {
    id: 3,
    name: 'SonicPulse ANC Studio Headphones',
    sku: 'SP-ANC-900',
    categoryId: 3,
    description: 'Hybrid dual-chamber active noise cancellation with 40mm beryllium drivers and lossless spatial audio.',
    price: 349.00,
    stock: 45,
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-02-10'
  },
  {
    id: 4,
    name: 'OmniWatch Horizon 2',
    sku: 'OW-HZ-46',
    categoryId: 4,
    description: 'Sapphire crystal smartwatch with ECG sensor, blood oxygen tracking, offline maps, and dual-frequency GPS.',
    price: 399.00,
    stock: 18,
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-02-12'
  },
  {
    id: 5,
    name: 'Nexus 4K Smart Camera Hub',
    sku: 'NX-CAM-4K',
    categoryId: 5,
    description: 'Dual-lens 4K night-vision security dome with AI edge recognition and built-in Zigbee home gateway.',
    price: 179.00,
    stock: 8, // Low stock for dashboard testing
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1557324232-b8917d3c3dcb?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-02-15'
  },
  {
    id: 6,
    name: 'Vortex Mechanical Pro Keyboard',
    sku: 'VT-KB-PRO87',
    categoryId: 6,
    description: 'Hot-swappable custom linear switches, CNC machined brass weight plate, gasket mount, and per-key RGB.',
    price: 189.50,
    stock: 32,
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-02-18'
  },
  {
    id: 7,
    name: 'HyperDrive 140W GaN Charger',
    sku: 'HD-GAN-140',
    categoryId: 7,
    description: 'Quad-port high-efficiency GaN charger supporting USB Power Delivery 3.1 with intelligent thermal throttle.',
    price: 79.99,
    stock: 60,
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-02-20'
  },
  {
    id: 8,
    name: 'SpherePad Pro 12.9" M2',
    sku: 'SP-PAD-129',
    categoryId: 1,
    description: 'Mini-LED display tablet with stylus hover responsiveness, dual thunderbolt ports, and HDR editing suite.',
    price: 1099.00,
    stock: 5, // Low stock
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-02-25'
  },
  {
    id: 9,
    name: 'TrueAir Wireless Earbuds V3',
    sku: 'TA-EAR-V3',
    categoryId: 3,
    description: 'Ergonomic IPX7 water-resistant earbuds with wireless charging case and low latency gaming audio profile.',
    price: 149.00,
    stock: 0, // Out of stock
    status: 'inactive',
    imageUrl: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-03-01'
  },
  {
    id: 10,
    name: 'Quantum Precision Wireless Mouse',
    sku: 'QM-MSE-8K',
    categoryId: 6,
    description: 'Ultralight 49-gram magnesium alloy mouse featuring 32,000 DPI optical sensor and 8000Hz polling rate.',
    price: 129.99,
    stock: 4, // Low stock
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-03-03'
  },
  {
    id: 11,
    name: 'Clarity 27" 4K Creator Monitor',
    sku: 'CL-MON-274K',
    categoryId: 2,
    description: 'Color-accurate IPS Black panel with 99% DCI-P3 gamut, built-in 96W KVM dock and zero-bezel chassis.',
    price: 649.00,
    stock: 14,
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-03-05'
  },
  {
    id: 12,
    name: 'Aura Smart Mood Lamp',
    sku: 'AU-SMP-LMP',
    categoryId: 5,
    description: 'Synchronized ambient lighting supporting Matter, HomeKit, and circadian rhythm auto-dimming.',
    price: 89.00,
    stock: 28,
    status: 'active',
    imageUrl: 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=400&auto=format&fit=crop&q=80',
    createdAt: '2026-03-08'
  }
];

// Default seed customers
const DEFAULT_CUSTOMERS = [
  {
    id: 1,
    firstName: 'Marcus',
    lastName: 'Vance',
    email: 'marcus.vance@techcorp.io',
    phone: '+1 (415) 882-9014',
    address: '742 Montgomery St, Suite 400',
    city: 'San Francisco',
    postalCode: '94111',
    status: 'active',
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80',
    createdAt: '2026-01-10'
  },
  {
    id: 2,
    firstName: 'Elena',
    lastName: 'Rostova',
    email: 'elena.rostova@designlab.co',
    phone: '+1 (212) 493-2190',
    address: '185 Broadway Ave, Fl 9',
    city: 'New York',
    postalCode: '10007',
    status: 'active',
    avatar: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=100&auto=format&fit=crop&q=80',
    createdAt: '2026-01-14'
  },
  {
    id: 3,
    firstName: 'David',
    lastName: 'Chen',
    email: 'david.chen@ventures.com',
    phone: '+1 (206) 774-8831',
    address: '1201 3rd Avenue, Penthouse',
    city: 'Seattle',
    postalCode: '98101',
    status: 'active',
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop&q=80',
    createdAt: '2026-01-22'
  },
  {
    id: 4,
    firstName: 'Sophia',
    lastName: 'Williams',
    email: 'sophia.williams@gmail.com',
    phone: '+1 (512) 349-1102',
    address: '304 Congress Ave',
    city: 'Austin',
    postalCode: '78701',
    status: 'active',
    avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=100&auto=format&fit=crop&q=80',
    createdAt: '2026-02-03'
  },
  {
    id: 5,
    firstName: 'Liam',
    lastName: 'O\'Connor',
    email: 'liam.oconnor@dublintech.eu',
    phone: '+1 (312) 654-8900',
    address: '222 W Merchandise Mart Plaza',
    city: 'Chicago',
    postalCode: '60654',
    status: 'active',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&auto=format&fit=crop&q=80',
    createdAt: '2026-02-11'
  },
  {
    id: 6,
    firstName: 'Amara',
    lastName: 'Kanu',
    email: 'amara.kanu@creativehouse.org',
    phone: '+1 (404) 923-4411',
    address: '1075 Peachtree St NE',
    city: 'Atlanta',
    postalCode: '30309',
    status: 'active',
    avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100&auto=format&fit=crop&q=80',
    createdAt: '2026-02-18'
  },
  {
    id: 7,
    firstName: 'Julian',
    lastName: 'Moreau',
    email: 'julian.moreau@polytechnic.fr',
    phone: '+1 (617) 555-0199',
    address: '77 Massachusetts Ave',
    city: 'Cambridge',
    postalCode: '02139',
    status: 'blocked',
    avatar: 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=100&auto=format&fit=crop&q=80',
    createdAt: '2026-02-24'
  }
];

// Default seed orders
const DEFAULT_ORDERS = [
  {
    id: 1,
    orderNumber: 'ORD-7081',
    customerId: 1,
    orderDate: '2026-03-08 14:22',
    items: [
      { productId: 1, productName: 'SpherePhone 15 Pro Max', price: 1199.00, quantity: 1, subtotal: 1199.00 },
      { productId: 7, productName: 'HyperDrive 140W GaN Charger', price: 79.99, quantity: 1, subtotal: 79.99 }
    ],
    subtotal: 1278.99,
    tax: 108.71,
    shipping: 0.00,
    discount: 50.00,
    totalAmount: 1337.70,
    paymentMethod: 'Credit Card',
    paymentStatus: 'paid',
    orderStatus: 'delivered',
    shippingAddress: '742 Montgomery St, Suite 400, San Francisco, CA 94111',
    notes: 'Please leave with front desk concierge.',
    createdAt: '2026-03-08'
  },
  {
    id: 2,
    orderNumber: 'ORD-7082',
    customerId: 2,
    orderDate: '2026-03-09 09:45',
    items: [
      { productId: 2, productName: 'AeroBook Ultra M3 16"', price: 2499.00, quantity: 1, subtotal: 2499.00 },
      { productId: 11, productName: 'Clarity 27" 4K Creator Monitor', price: 649.00, quantity: 1, subtotal: 649.00 }
    ],
    subtotal: 3148.00,
    tax: 267.58,
    shipping: 25.00,
    discount: 0.00,
    totalAmount: 3440.58,
    paymentMethod: 'PayPal',
    paymentStatus: 'paid',
    orderStatus: 'shipped',
    shippingAddress: '185 Broadway Ave, Fl 9, New York, NY 10007',
    notes: 'Require signature upon delivery.',
    createdAt: '2026-03-09'
  },
  {
    id: 3,
    orderNumber: 'ORD-7083',
    customerId: 3,
    orderDate: '2026-03-10 11:15',
    items: [
      { productId: 4, productName: 'OmniWatch Horizon 2', price: 399.00, quantity: 1, subtotal: 399.00 },
      { productId: 3, productName: 'SonicPulse ANC Studio Headphones', price: 349.00, quantity: 1, subtotal: 349.00 }
    ],
    subtotal: 748.00,
    tax: 63.58,
    shipping: 15.00,
    discount: 25.00,
    totalAmount: 801.58,
    paymentMethod: 'Credit Card',
    paymentStatus: 'paid',
    orderStatus: 'processing',
    shippingAddress: '1201 3rd Avenue, Penthouse, Seattle, WA 98101',
    notes: '',
    createdAt: '2026-03-10'
  },
  {
    id: 4,
    orderNumber: 'ORD-7084',
    customerId: 4,
    orderDate: '2026-03-11 10:30',
    items: [
      { productId: 6, productName: 'Vortex Mechanical Pro Keyboard', price: 189.50, quantity: 1, subtotal: 189.50 },
      { productId: 10, productName: 'Quantum Precision Wireless Mouse', price: 129.99, quantity: 1, subtotal: 129.99 }
    ],
    subtotal: 319.49,
    tax: 27.16,
    shipping: 15.00,
    discount: 0.00,
    totalAmount: 361.65,
    paymentMethod: 'Credit Card',
    paymentStatus: 'paid',
    orderStatus: 'pending',
    shippingAddress: '304 Congress Ave, Austin, TX 78701',
    notes: 'Gift wrap requested.',
    createdAt: '2026-03-11'
  },
  {
    id: 5,
    orderNumber: 'ORD-7085',
    customerId: 5,
    orderDate: '2026-03-11 13:05',
    items: [
      { productId: 5, productName: 'Nexus 4K Smart Camera Hub', price: 179.00, quantity: 2, subtotal: 358.00 },
      { productId: 12, productName: 'Aura Smart Mood Lamp', price: 89.00, quantity: 2, subtotal: 178.00 }
    ],
    subtotal: 536.00,
    tax: 45.56,
    shipping: 0.00,
    discount: 20.00,
    totalAmount: 561.56,
    paymentMethod: 'Bank Transfer',
    paymentStatus: 'pending',
    orderStatus: 'pending',
    shippingAddress: '222 W Merchandise Mart Plaza, Chicago, IL 60654',
    notes: 'Awaiting wire verification.',
    createdAt: '2026-03-11'
  },
  {
    id: 6,
    orderNumber: 'ORD-7086',
    customerId: 6,
    orderDate: '2026-03-11 15:40',
    items: [
      { productId: 8, productName: 'SpherePad Pro 12.9" M2', price: 1099.00, quantity: 1, subtotal: 1099.00 }
    ],
    subtotal: 1099.00,
    tax: 93.42,
    shipping: 0.00,
    discount: 0.00,
    totalAmount: 1192.42,
    paymentMethod: 'Credit Card',
    paymentStatus: 'paid',
    orderStatus: 'processing',
    shippingAddress: '1075 Peachtree St NE, Atlanta, GA 30309',
    notes: '',
    createdAt: '2026-03-11'
  }
];

// Default store settings
const DEFAULT_SETTINGS = {
  storeName: 'ShopSphere Technologies',
  storeTagline: 'Next-Gen Commercial E-Commerce Platform',
  currency: 'USD',
  currencySymbol: '$',
  taxRate: 8.5,
  shippingFlat: 15.00,
  freeShippingThreshold: 100.00,
  contactEmail: 'admin@shopsphere.io',
  contactPhone: '+1 (800) 555-SPHERE',
  address: '100 Silicon Boulevard, San Francisco, CA 94105',
  lowStockThreshold: 10,
  adminUser: {
    name: 'Alexander Wright',
    email: 'a.wright@shopsphere.io',
    role: 'Principal Administrator',
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80'
  }
};

// Default activity logs
const DEFAULT_ACTIVITIES = [
  { id: 1, type: 'order', text: 'New order #ORD-7086 placed by Amara Kanu ($1,192.42)', timestamp: '15 mins ago' },
  { id: 2, type: 'order', text: 'Order #ORD-7085 wire transfer awaiting clearance', timestamp: '1 hour ago' },
  { id: 3, type: 'stock', text: 'Low stock warning: SpherePad Pro 12.9" has 5 units remaining', timestamp: '2 hours ago' },
  { id: 4, type: 'customer', text: 'New customer account created: Julian Moreau', timestamp: '4 hours ago' },
  { id: 5, type: 'order', text: 'Order #ORD-7081 successfully delivered to Marcus Vance', timestamp: 'Yesterday' }
];

/**
 * Main Store Object
 */
const Store = {
  /**
   * Initializes local storage with defaults if not already present.
   */
  init() {
    if (!localStorage.getItem(STORAGE_KEYS.INIT_FLAG)) {
      this.resetData();
    }
  },

  /**
   * Resets local storage to default initial mock data.
   */
  resetData() {
    localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(DEFAULT_PRODUCTS));
    localStorage.setItem(STORAGE_KEYS.CATEGORIES, JSON.stringify(DEFAULT_CATEGORIES));
    localStorage.setItem(STORAGE_KEYS.CUSTOMERS, JSON.stringify(DEFAULT_CUSTOMERS));
    localStorage.setItem(STORAGE_KEYS.ORDERS, JSON.stringify(DEFAULT_ORDERS));
    localStorage.setItem(STORAGE_KEYS.SETTINGS, JSON.stringify(DEFAULT_SETTINGS));
    localStorage.setItem(STORAGE_KEYS.ACTIVITIES, JSON.stringify(DEFAULT_ACTIVITIES));
    localStorage.setItem(STORAGE_KEYS.INIT_FLAG, 'true');
    console.log('[ShopSphere Store] Seed data initialized.');
  },

  // --------------------------------------------------------------------------
  // PRODUCTS REPOSITORY
  // --------------------------------------------------------------------------
  async getProducts() {
    this.init();
    const data = JSON.parse(localStorage.getItem(STORAGE_KEYS.PRODUCTS) || '[]');
    return data;
  },

  async getProductById(id) {
    const products = await this.getProducts();
    return products.find(p => p.id === Number(id)) || null;
  },

  async saveProduct(productData) {
    const products = await this.getProducts();
    let savedProduct;

    if (productData.id) {
      // Edit existing
      const index = products.findIndex(p => p.id === Number(productData.id));
      if (index !== -1) {
        products[index] = {
          ...products[index],
          ...productData,
          id: Number(productData.id),
          price: parseFloat(productData.price),
          stock: parseInt(productData.stock, 10),
          categoryId: Number(productData.categoryId),
          updatedAt: new Date().toISOString().split('T')[0]
        };
        savedProduct = products[index];
        this.addActivity('product', `Updated product: ${savedProduct.name}`);
      }
    } else {
      // Create new
      const nextId = products.length > 0 ? Math.max(...products.map(p => p.id)) + 1 : 1;
      savedProduct = {
        ...productData,
        id: nextId,
        price: parseFloat(productData.price),
        stock: parseInt(productData.stock, 10),
        categoryId: Number(productData.categoryId),
        createdAt: new Date().toISOString().split('T')[0]
      };
      products.unshift(savedProduct);
      this.addActivity('product', `Added new product: ${savedProduct.name} (${savedProduct.sku})`);
    }

    localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(products));
    return savedProduct;
  },

  async deleteProduct(id) {
    let products = await this.getProducts();
    const target = products.find(p => p.id === Number(id));
    products = products.filter(p => p.id !== Number(id));
    localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(products));
    if (target) {
      this.addActivity('product', `Deleted product: ${target.name}`);
    }
    return true;
  },

  // --------------------------------------------------------------------------
  // CATEGORIES REPOSITORY
  // --------------------------------------------------------------------------
  async getCategories() {
    this.init();
    const categories = JSON.parse(localStorage.getItem(STORAGE_KEYS.CATEGORIES) || '[]');
    const products = await this.getProducts();

    // Augment with live product counts
    return categories.map(cat => {
      const productCount = products.filter(p => p.categoryId === cat.id).length;
      return { ...cat, productCount };
    });
  },

  async getCategoryById(id) {
    const categories = await this.getCategories();
    return categories.find(c => c.id === Number(id)) || null;
  },

  async saveCategory(catData) {
    const categories = JSON.parse(localStorage.getItem(STORAGE_KEYS.CATEGORIES) || '[]');
    let savedCategory;

    if (catData.id) {
      const index = categories.findIndex(c => c.id === Number(catData.id));
      if (index !== -1) {
        categories[index] = {
          ...categories[index],
          ...catData,
          id: Number(catData.id)
        };
        savedCategory = categories[index];
        this.addActivity('category', `Updated category: ${savedCategory.name}`);
      }
    } else {
      const nextId = categories.length > 0 ? Math.max(...categories.map(c => c.id)) + 1 : 1;
      savedCategory = {
        ...catData,
        id: nextId,
        slug: catData.slug || catData.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''),
        createdAt: new Date().toISOString().split('T')[0]
      };
      categories.unshift(savedCategory);
      this.addActivity('category', `Added new category: ${savedCategory.name}`);
    }

    localStorage.setItem(STORAGE_KEYS.CATEGORIES, JSON.stringify(categories));
    return savedCategory;
  },

  async deleteCategory(id) {
    let categories = JSON.parse(localStorage.getItem(STORAGE_KEYS.CATEGORIES) || '[]');
    const target = categories.find(c => c.id === Number(id));
    categories = categories.filter(c => c.id !== Number(id));
    localStorage.setItem(STORAGE_KEYS.CATEGORIES, JSON.stringify(categories));
    if (target) {
      this.addActivity('category', `Deleted category: ${target.name}`);
    }
    return true;
  },

  // --------------------------------------------------------------------------
  // CUSTOMERS REPOSITORY
  // --------------------------------------------------------------------------
  async getCustomers() {
    this.init();
    const customers = JSON.parse(localStorage.getItem(STORAGE_KEYS.CUSTOMERS) || '[]');
    const orders = await this.getOrders();

    // Augment with order count and total spent
    return customers.map(cust => {
      const custOrders = orders.filter(o => o.customerId === cust.id);
      const totalSpent = custOrders.reduce((sum, o) => sum + (o.totalAmount || 0), 0);
      return {
        ...cust,
        orderCount: custOrders.length,
        totalSpent: parseFloat(totalSpent.toFixed(2))
      };
    });
  },

  async getCustomerById(id) {
    const customers = await this.getCustomers();
    return customers.find(c => c.id === Number(id)) || null;
  },

  async saveCustomer(customerData) {
    const customers = JSON.parse(localStorage.getItem(STORAGE_KEYS.CUSTOMERS) || '[]');
    let savedCustomer;

    if (customerData.id) {
      const index = customers.findIndex(c => c.id === Number(customerData.id));
      if (index !== -1) {
        customers[index] = {
          ...customers[index],
          ...customerData,
          id: Number(customerData.id)
        };
        savedCustomer = customers[index];
        this.addActivity('customer', `Updated customer: ${savedCustomer.firstName} ${savedCustomer.lastName}`);
      }
    } else {
      const nextId = customers.length > 0 ? Math.max(...customers.map(c => c.id)) + 1 : 1;
      savedCustomer = {
        ...customerData,
        id: nextId,
        avatar: customerData.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(customerData.firstName + ' ' + customerData.lastName)}&background=4F46E5&color=fff`,
        status: customerData.status || 'active',
        createdAt: new Date().toISOString().split('T')[0]
      };
      customers.unshift(savedCustomer);
      this.addActivity('customer', `Registered new customer: ${savedCustomer.firstName} ${savedCustomer.lastName}`);
    }

    localStorage.setItem(STORAGE_KEYS.CUSTOMERS, JSON.stringify(customers));
    return savedCustomer;
  },

  async deleteCustomer(id) {
    let customers = JSON.parse(localStorage.getItem(STORAGE_KEYS.CUSTOMERS) || '[]');
    const target = customers.find(c => c.id === Number(id));
    customers = customers.filter(c => c.id !== Number(id));
    localStorage.setItem(STORAGE_KEYS.CUSTOMERS, JSON.stringify(customers));
    if (target) {
      this.addActivity('customer', `Deleted customer: ${target.firstName} ${target.lastName}`);
    }
    return true;
  },

  // --------------------------------------------------------------------------
  // ORDERS REPOSITORY
  // --------------------------------------------------------------------------
  async getOrders() {
    this.init();
    const orders = JSON.parse(localStorage.getItem(STORAGE_KEYS.ORDERS) || '[]');
    const customers = JSON.parse(localStorage.getItem(STORAGE_KEYS.CUSTOMERS) || '[]');

    // Augment with customer details
    return orders.map(order => {
      const cust = customers.find(c => c.id === order.customerId);
      return {
        ...order,
        customerName: cust ? `${cust.firstName} ${cust.lastName}` : 'Guest Customer',
        customerEmail: cust ? cust.email : ''
      };
    });
  },

  async getOrderById(id) {
    const orders = await this.getOrders();
    return orders.find(o => o.id === Number(id)) || null;
  },

  async saveOrder(orderData) {
    const orders = JSON.parse(localStorage.getItem(STORAGE_KEYS.ORDERS) || '[]');
    let savedOrder;

    if (orderData.id) {
      const index = orders.findIndex(o => o.id === Number(orderData.id));
      if (index !== -1) {
        orders[index] = {
          ...orders[index],
          ...orderData,
          id: Number(orderData.id)
        };
        savedOrder = orders[index];
        this.addActivity('order', `Updated order #${savedOrder.orderNumber} status to ${savedOrder.orderStatus}`);
      }
    } else {
      const nextId = orders.length > 0 ? Math.max(...orders.map(o => o.id)) + 1 : 1;
      const orderNum = `ORD-${7080 + nextId}`;
      const now = new Date();
      const formattedDate = `${now.toISOString().split('T')[0]} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

      savedOrder = {
        ...orderData,
        id: nextId,
        orderNumber: orderNum,
        customerId: Number(orderData.customerId),
        orderDate: formattedDate,
        createdAt: now.toISOString().split('T')[0]
      };

      // Deduct stock from products for ordered items
      const products = await this.getProducts();
      if (Array.isArray(savedOrder.items)) {
        savedOrder.items.forEach(item => {
          const prod = products.find(p => p.id === Number(item.productId));
          if (prod) {
            prod.stock = Math.max(0, prod.stock - Number(item.quantity));
            if (prod.stock === 0) prod.status = 'inactive';
          }
        });
        localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(products));
      }

      orders.unshift(savedOrder);
      this.addActivity('order', `Created order #${savedOrder.orderNumber} for total $${savedOrder.totalAmount.toFixed(2)}`);
    }

    localStorage.setItem(STORAGE_KEYS.ORDERS, JSON.stringify(orders));
    return savedOrder;
  },

  async deleteOrder(id) {
    let orders = JSON.parse(localStorage.getItem(STORAGE_KEYS.ORDERS) || '[]');
    const target = orders.find(o => o.id === Number(id));
    orders = orders.filter(o => o.id !== Number(id));
    localStorage.setItem(STORAGE_KEYS.ORDERS, JSON.stringify(orders));
    if (target) {
      this.addActivity('order', `Deleted order #${target.orderNumber}`);
    }
    return true;
  },

  // --------------------------------------------------------------------------
  // SETTINGS & SYSTEM STATS
  // --------------------------------------------------------------------------
  async getSettings() {
    this.init();
    return JSON.parse(localStorage.getItem(STORAGE_KEYS.SETTINGS) || JSON.stringify(DEFAULT_SETTINGS));
  },

  async saveSettings(settingsData) {
    const current = await this.getSettings();
    const updated = { ...current, ...settingsData };
    localStorage.setItem(STORAGE_KEYS.SETTINGS, JSON.stringify(updated));
    this.addActivity('system', 'Updated store configuration and preferences');
    return updated;
  },

  async getActivities(limit = 6) {
    this.init();
    const activities = JSON.parse(localStorage.getItem(STORAGE_KEYS.ACTIVITIES) || '[]');
    return activities.slice(0, limit);
  },

  addActivity(type, text) {
    const activities = JSON.parse(localStorage.getItem(STORAGE_KEYS.ACTIVITIES) || '[]');
    const newActivity = {
      id: Date.now(),
      type,
      text,
      timestamp: 'Just now'
    };
    activities.unshift(newActivity);
    if (activities.length > 25) activities.pop();
    localStorage.setItem(STORAGE_KEYS.ACTIVITIES, JSON.stringify(activities));
  },

  /**
   * Computes comprehensive analytics for the Dashboard
   */
  async getStats() {
    const products = await this.getProducts();
    const categories = await this.getCategories();
    const customers = await this.getCustomers();
    const orders = await this.getOrders();
    const settings = await this.getSettings();

    // 1. Total Revenue (Sum of all orders that are paid or valid)
    const totalRevenue = orders
      .filter(o => o.paymentStatus !== 'failed' && o.paymentStatus !== 'refunded')
      .reduce((sum, o) => sum + (o.totalAmount || 0), 0);

    // 2. Low stock threshold
    const lowStockThreshold = settings.lowStockThreshold || 10;
    const lowStockProducts = products.filter(p => p.stock <= lowStockThreshold);

    // 3. Top selling products (calculated by sum of quantities across orders)
    const productSalesMap = {};
    orders.forEach(order => {
      if (order.items && Array.isArray(order.items)) {
        order.items.forEach(item => {
          if (!productSalesMap[item.productId]) {
            productSalesMap[item.productId] = { quantity: 0, revenue: 0 };
          }
          productSalesMap[item.productId].quantity += (item.quantity || 1);
          productSalesMap[item.productId].revenue += (item.subtotal || (item.price * item.quantity) || 0);
        });
      }
    });

    const topProducts = products.map(p => {
      const sales = productSalesMap[p.id] || { quantity: 0, revenue: 0 };
      const cat = categories.find(c => c.id === p.categoryId);
      return {
        ...p,
        categoryName: cat ? cat.name : 'General',
        totalSold: sales.quantity,
        revenueGenerated: sales.revenue
      };
    }).sort((a, b) => b.totalSold - a.totalSold).slice(0, 5);

    // 4. Sales Overview Chart Data (Monthly simulation or recent 6 months)
    const chartLabels = ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];
    const currentPeriodSales = [12400, 18900, 29400, 21800, 26700, Math.round(totalRevenue)];
    const previousPeriodSales = [9800, 14200, 22100, 17500, 20300, 23400];

    // 5. Order Status Breakdown
    const orderStatuses = {
      delivered: orders.filter(o => o.orderStatus === 'delivered').length,
      shipped: orders.filter(o => o.orderStatus === 'shipped').length,
      processing: orders.filter(o => o.orderStatus === 'processing').length,
      pending: orders.filter(o => o.orderStatus === 'pending').length,
      cancelled: orders.filter(o => o.orderStatus === 'cancelled').length
    };

    return {
      totalRevenue: parseFloat(totalRevenue.toFixed(2)),
      totalOrders: orders.length,
      totalProducts: products.length,
      totalCustomers: customers.length,
      lowStockCount: lowStockProducts.length,
      lowStockProducts,
      recentOrders: orders.slice(0, 5),
      topProducts,
      chartLabels,
      currentPeriodSales,
      previousPeriodSales,
      orderStatuses
    };
  }
};

// Expose globally
window.Store = Store;

