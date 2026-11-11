<?php
/**
 * ShopSphere - Create New Order API Endpoint
 * Phase 6: Complete Order Management CRUD
 * 
 * Route: POST /api/orders/store.php
 * Transactional creation with atomic stock reservation and validation.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/auth.php';

// Method check
requireMethod('POST');

// Authentication & CSRF
requireAuth();
requireCsrf();

$input = getJsonInput();

$customerId    = isset($input['customer_id']) ? (int) $input['customer_id'] : 0;
$itemsInput    = $input['items'] ?? [];
$paymentMethod = sanitizeString($input['payment_method'] ?? 'Credit Card');
$paymentStatus = strtolower(sanitizeString($input['payment_status'] ?? 'pending'));

// Lifecycle Rule (Task 1): Newly created orders MUST always start with 'pending' status.
// Client-provided initial order status is strictly ignored to protect stock deduction integrity.
$orderStatus = 'pending';
$notes       = sanitizeString($input['notes'] ?? '');

$tax      = max(0.00, (float) ($input['tax'] ?? 0.00));
$shipping = max(0.00, (float) ($input['shipping'] ?? 0.00));
$discount = max(0.00, (float) ($input['discount'] ?? 0.00));

// 1. Initial Validation
$errors = [];

if ($customerId <= 0) {
    $errors['customer_id'] = 'Valid customer ID is required.';
}

if (!is_array($itemsInput) || empty($itemsInput)) {
    $errors['items'] = 'Order must contain at least one product item.';
}

// Allowed initial payment statuses: 'pending', 'paid', 'failed' (cannot be 'refunded' on creation)
$validPaymentStatuses = ['pending', 'paid', 'failed'];
if (!in_array($paymentStatus, $validPaymentStatuses, true)) {
    $paymentStatus = 'pending';
}

// Consolidate duplicate products if provided multiple times
$consolidatedItems = [];
if (is_array($itemsInput)) {
    foreach ($itemsInput as $index => $item) {
        $pId = (int) ($item['product_id'] ?? ($item['productId'] ?? 0));
        $qty = (int) ($item['quantity'] ?? ($item['qty'] ?? 0));

        if ($pId <= 0) {
            $errors["items.{$index}.product_id"] = 'Each item must have a valid product ID.';
            continue;
        }

        if ($qty <= 0) {
            $errors["items.{$index}.quantity"] = 'Quantity must be at least 1 unit.';
            continue;
        }

        if (isset($consolidatedItems[$pId])) {
            $consolidatedItems[$pId] += $qty;
        } else {
            $consolidatedItems[$pId] = $qty;
        }
    }
}

if (!empty($errors)) {
    sendError('Validation failed. Please correct the highlighted errors.', $errors, 400);
}

try {
    $pdo = Database::getConnection();

    // 2. Validate Customer Existence and Status
    $custStmt = $pdo->prepare('SELECT id, full_name, email, status FROM customers WHERE id = :id LIMIT 1');
    $custStmt->execute(['id' => $customerId]);
    $customer = $custStmt->fetch();

    if (!$customer) {
        sendError('Customer not found.', ['customer_id' => "Customer with ID {$customerId} does not exist."], 404);
    }

    if ($customer['status'] === 'blocked') {
        sendError('Customer account is blocked.', ['customer_id' => 'Cannot create orders for a blocked customer account.'], 403);
    }

    // 3. Begin Transaction for Atomic Order Creation & Stock Reservation
    $pdo->beginTransaction();

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $forUpdateClause = ($driver === 'mysql') ? ' FOR UPDATE' : '';

    $orderSubtotal = 0.00;
    $validatedOrderItems = [];

    // Lock and validate each product
    foreach ($consolidatedItems as $productId => $quantity) {
        $prodStmt = $pdo->prepare("SELECT id, name, sku, price, stock, status FROM products WHERE id = :id" . $forUpdateClause);
        $prodStmt->execute(['id' => $productId]);
        $product = $prodStmt->fetch();

        if (!$product) {
            $pdo->rollBack();
            sendError("Product ID {$productId} was not found in catalog.", [
                'product_id' => "Product ID {$productId} does not exist."
            ], 400);
        }

        if ($product['status'] === 'inactive') {
            $pdo->rollBack();
            sendError("Product \"{$product['name']}\" is currently inactive and cannot be ordered.", [
                'product_id' => "Product {$product['name']} is inactive."
            ], 400);
        }

        $availableStock = (int) $product['stock'];
        if ($availableStock < $quantity) {
            $pdo->rollBack();
            sendError("Insufficient stock for \"{$product['name']}\". Requested: {$quantity}, Available in inventory: {$availableStock}.", [
                'stock' => "Insufficient stock for product ID {$productId}."
            ], 400);
        }

        $unitPrice = (float) $product['price'];
        $lineSubtotal = round($unitPrice * $quantity, 2);
        $orderSubtotal += $lineSubtotal;

        $validatedOrderItems[] = [
            'product_id'   => (int) $product['id'],
            'product_name' => $product['name'],
            'product_sku'  => $product['sku'],
            'quantity'     => $quantity,
            'unit_price'   => $unitPrice,
            'subtotal'     => $lineSubtotal,
            'old_stock'    => $availableStock,
            'new_stock'    => $availableStock - $quantity
        ];
    }

    // 4. Calculate Final Total Amount
    $totalAmount = max(0.00, round($orderSubtotal + $tax + $shipping - $discount, 2));

    // 5. Generate Unique Order Number
    $orderNumber = generateOrderNumber($pdo);

    // 6. Insert Order Header Record
    $orderInsertStmt = $pdo->prepare("
        INSERT INTO orders (
            order_number,
            customer_id,
            order_date,
            subtotal,
            total_amount,
            payment_method,
            payment_status,
            order_status,
            notes,
            created_at,
            updated_at
        ) VALUES (
            :order_number,
            :customer_id,
            CURRENT_TIMESTAMP,
            :subtotal,
            :total_amount,
            :payment_method,
            :payment_status,
            :order_status,
            :notes,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
    ");

    $orderInsertStmt->execute([
        'order_number'   => $orderNumber,
        'customer_id'    => $customerId,
        'subtotal'       => $orderSubtotal,
        'total_amount'   => $totalAmount,
        'payment_method' => $paymentMethod,
        'payment_status' => $paymentStatus,
        'order_status'   => $orderStatus,
        'notes'          => $notes !== '' ? $notes : null
    ]);

    $newOrderId = (int) $pdo->lastInsertId();

    // 7. Insert Itemized Line Items & Deduct Stock Atomically
    $itemInsertStmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id,
            product_id,
            quantity,
            unit_price,
            subtotal,
            created_at
        ) VALUES (
            :order_id,
            :product_id,
            :quantity,
            :unit_price,
            :subtotal,
            CURRENT_TIMESTAMP
        )
    ");

    $stockDeductStmt = $pdo->prepare("
        UPDATE products 
        SET stock = stock - :quantity, updated_at = CURRENT_TIMESTAMP 
        WHERE id = :product_id AND stock >= :quantity
    ");

    foreach ($validatedOrderItems as $item) {
        $itemInsertStmt->execute([
            'order_id'   => $newOrderId,
            'product_id' => $item['product_id'],
            'quantity'   => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'subtotal'   => $item['subtotal']
        ]);

        $stockDeductStmt->execute([
            'quantity'   => $item['quantity'],
            'product_id' => $item['product_id']
        ]);

        if ($stockDeductStmt->rowCount() === 0) {
            $pdo->rollBack();
            sendError("Failed to reserve stock for \"{$item['product_name']}\". Inventory changed concurrently.", [
                'stock' => "Insufficient stock for product ID {$item['product_id']}."
            ], 400);
        }
    }

    // 8. Commit Transaction
    $pdo->commit();

    sendSuccess([
        'order' => [
            'id'             => $newOrderId,
            'order_number'   => $orderNumber,
            'customer_id'    => $customerId,
            'customer_name'  => $customer['full_name'],
            'customer_email' => $customer['email'],
            'subtotal'       => $orderSubtotal,
            'total_amount'   => $totalAmount,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'order_status'   => $orderStatus,
            'items_count'    => count($validatedOrderItems),
            'items'          => $validatedOrderItems
        ]
    ], "Order #{$orderNumber} created successfully with stock deducted.", 201);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendServerError('An error occurred during order creation and stock reservation.', ['error' => $e->getMessage()]);
}

/**
 * Generate sequential unique order number (e.g. ORD-7093, ORD-7094)
 */
function generateOrderNumber(PDO $pdo): string {
    $stmt = $pdo->query("SELECT order_number FROM orders WHERE order_number LIKE 'ORD-%' ORDER BY id DESC LIMIT 1");
    $last = $stmt ? $stmt->fetchColumn() : null;

    $nextNum = 7100;
    if ($last && preg_match('/ORD-(\d+)/', $last, $matches)) {
        $nextNum = max($nextNum, ((int) $matches[1]) + 1);
    }

    do {
        $candidate = 'ORD-' . $nextNum;
        $check = $pdo->prepare('SELECT id FROM orders WHERE order_number = :num LIMIT 1');
        $check->execute(['num' => $candidate]);
        if (!$check->fetch()) {
            return $candidate;
        }
        $nextNum++;
    } while ($nextNum < 999999);

    return 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
}

