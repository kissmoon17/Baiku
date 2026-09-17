<?php

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db.php';

function respond($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requestJson(): array
{
    $raw = file_get_contents('php://input');

    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        /*
         * GET /api.php?action=products
         *
         * Optional:
         *   category=Helmets
         *   featured=1
         */
        case 'products':
            $sql = "
                SELECT
                    id,
                    name,
                    description,
                    price,
                    category,
                    image,
                    featured,
                    stock
                FROM products
                WHERE active = 1
            ";

            $params = [];

            if (!empty($_GET['category'])) {
                $allowedCategories = ['Helmets', 'Jackets', 'Pants', 'Boots'];

                if (!in_array($_GET['category'], $allowedCategories, true)) {
                    respond(['success' => false, 'message' => 'Invalid category.'], 400);
                }

                $sql .= " AND category = :category";
                $params['category'] = $_GET['category'];
            }

            if (isset($_GET['featured']) && $_GET['featured'] === '1') {
                $sql .= " AND featured = 1";
            }

            $sql .= " ORDER BY created_at DESC, id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $products = $stmt->fetchAll();

            foreach ($products as &$product) {
                $product['id'] = (int) $product['id'];
                $product['price'] = (float) $product['price'];
                $product['featured'] = (bool) $product['featured'];
                $product['stock'] = (int) $product['stock'];
            }

            respond([
                'success' => true,
                'count' => count($products),
                'products' => $products
            ]);
            break;


        /*
         * GET /api.php?action=product&id=1
         */
        case 'product':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                respond(['success' => false, 'message' => 'A valid product ID is required.'], 400);
            }

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    name,
                    description,
                    price,
                    category,
                    image,
                    featured,
                    stock
                FROM products
                WHERE id = :id AND active = 1
                LIMIT 1
            ");

            $stmt->execute(['id' => $id]);
            $product = $stmt->fetch();

            if (!$product) {
                respond(['success' => false, 'message' => 'Product not found.'], 404);
            }

            $product['id'] = (int) $product['id'];
            $product['price'] = (float) $product['price'];
            $product['featured'] = (bool) $product['featured'];
            $product['stock'] = (int) $product['stock'];

            respond([
                'success' => true,
                'product' => $product
            ]);
            break;


        /*
         * POST /api.php?action=newsletter
         * Body:
         * { "email": "customer@example.com" }
         */
        case 'newsletter':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['success' => false, 'message' => 'POST request required.'], 405);
            }

            $data = requestJson();
            $email = trim($data['email'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respond(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO newsletter_subscribers (email)
                    VALUES (:email)
                ");

                $stmt->execute(['email' => $email]);

                respond([
                    'success' => true,
                    'message' => 'You are now subscribed to Baiku.'
                ], 201);

            } catch (PDOException $e) {
                if ((int) $e->errorInfo[1] === 1062) {
                    respond([
                        'success' => true,
                        'message' => 'This email is already subscribed.'
                    ]);
                }

                throw $e;
            }
            break;



        case 'register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['success' => false, 'message' => 'POST request required.'], 405);
            }
            $data = requestJson();
            $name = trim($data['name'] ?? '');
            $email = strtolower(trim($data['email'] ?? ''));
            $password = (string)($data['password'] ?? '');

            if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                respond(['success' => false, 'message' => 'Name must be between 2 and 100 characters.'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respond(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
            }
            if (strlen($password) < 6) {
                respond(['success' => false, 'message' => 'Password must contain at least 6 characters.'], 400);
            }

            $check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $check->execute(['email' => $email]);
            if ($check->fetch()) {
                respond(['success' => false, 'message' => 'An account with this email already exists.'], 409);
            }

            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role)
                VALUES (:name, :email, :password, 'customer')
            ");
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ]);

            $userId = (int)$pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_role'] = 'customer';

            respond([
                'success' => true,
                'message' => 'Account created successfully.',
                'user' => ['id'=>$userId, 'name'=>$name, 'email'=>$email, 'role'=>'customer']
            ], 201);

        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['success' => false, 'message' => 'POST request required.'], 405);
            }
            $data = requestJson();
            $email = strtolower(trim($data['email'] ?? ''));
            $password = (string)($data['password'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                respond(['success' => false, 'message' => 'Email and password are required.'], 400);
            }

            $stmt = $pdo->prepare("
                SELECT id, name, email, password, role
                FROM users WHERE email = :email LIMIT 1
            ");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_role'] = $user['role'];

            respond([
                'success' => true,
                'message' => 'Login successful.',
                'user' => ['id'=>(int)$user['id'], 'name'=>$user['name'], 'email'=>$user['email'], 'role'=>$user['role']]
            ]);
            break;

        case 'logout':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['success' => false, 'message' => 'POST request required.'], 405);
            }
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']);
            }
            session_destroy();
            respond(['success' => true, 'message' => 'Logged out successfully.']);
            break;

        case 'session':
            if (!empty($_SESSION['user_id'])) {
                $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => (int)$_SESSION['user_id']]);
                $user = $stmt->fetch();
                if ($user) {
                    respond([
                        'success'=>true, 'authenticated'=>true,
                        'user'=>['id'=>(int)$user['id'], 'name'=>$user['name'], 'email'=>$user['email'], 'role'=>$user['role']]
                    ]);
                }
            }
            respond(['success'=>true, 'authenticated'=>false, 'user'=>null]);
            break;


        case 'cart':
            if (empty($_SESSION['user_id'])) {
                respond(['success' => false, 'message' => 'Please sign in to use your saved cart.'], 401);
            }
            $userId = (int)$_SESSION['user_id'];

            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $stmt = $pdo->prepare("
                    SELECT c.product_id AS id, c.quantity AS qty,
                           p.name, p.price, p.image, p.category, p.stock
                    FROM cart_items c
                    INNER JOIN products p ON p.id = c.product_id
                    WHERE c.user_id = :user_id AND p.active = 1
                    ORDER BY c.updated_at DESC, c.id DESC
                ");
                $stmt->execute(['user_id' => $userId]);
                $items = $stmt->fetchAll();
                foreach ($items as &$item) {
                    $item['id'] = (int)$item['id'];
                    $item['qty'] = (int)$item['qty'];
                    $item['price'] = (float)$item['price'];
                    $item['stock'] = (int)$item['stock'];
                }
                respond(['success' => true, 'items' => $items]);
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['success' => false, 'message' => 'GET or POST request required.'], 405);
            }

            $data = requestJson();
            $rawItems = $data['items'] ?? [];
            if (!is_array($rawItems)) {
                respond(['success' => false, 'message' => 'Cart items must be an array.'], 400);
            }

            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM cart_items WHERE user_id = :user_id")->execute(['user_id' => $userId]);
                $productStmt = $pdo->prepare("SELECT id, stock, active FROM products WHERE id = :id LIMIT 1");
                $insertStmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)");

                foreach ($rawItems as $item) {
                    $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
                    $quantity = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT);
                    if (!$productId || $productId < 1 || $quantity === false || $quantity < 1) continue;
                    $productStmt->execute(['id' => $productId]);
                    $product = $productStmt->fetch();
                    if (!$product || !(int)$product['active'] || (int)$product['stock'] < 1) continue;
                    $quantity = min($quantity, (int)$product['stock']);
                    $insertStmt->execute(['user_id' => $userId, 'product_id' => $productId, 'quantity' => $quantity]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            respond(['success' => true, 'message' => 'Cart synced successfully.']);

        case 'create_order':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['success' => false, 'message' => 'POST request required.'], 405);
            }
            if (empty($_SESSION['user_id'])) {
                respond(['success' => false, 'message' => 'Please sign in before placing an order.'], 401);
            }

            $data = requestJson();
            $userId = (int)$_SESSION['user_id'];
            $name = trim((string)($data['name'] ?? ''));
            $phone = trim((string)($data['phone'] ?? ''));
            $address = trim((string)($data['address'] ?? ''));
            $city = trim((string)($data['city'] ?? ''));
            $country = trim((string)($data['country'] ?? 'Nepal')) ?: 'Nepal';
            $paymentMethod = trim((string)($data['payment_method'] ?? ''));
            $allowedPayments = ['Cash on delivery', 'Demo card payment'];

            if (mb_strlen($name) < 2 || mb_strlen($name) > 100) respond(['success' => false, 'message' => 'Please enter a valid full name.'], 400);
            if ($phone === '' || mb_strlen($phone) > 30) respond(['success' => false, 'message' => 'Please enter a valid phone number.'], 400);
            if (mb_strlen($address) < 3 || mb_strlen($address) > 255) respond(['success' => false, 'message' => 'Please enter a valid shipping address.'], 400);
            if (mb_strlen($city) < 2 || mb_strlen($city) > 100) respond(['success' => false, 'message' => 'Please enter a valid city.'], 400);
            if (mb_strlen($country) > 100 || !in_array($paymentMethod, $allowedPayments, true)) respond(['success' => false, 'message' => 'Please select a valid payment method.'], 400);

            $userStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = :id LIMIT 1");
            $userStmt->execute(['id' => $userId]);
            $user = $userStmt->fetch();
            if (!$user) respond(['success' => false, 'message' => 'Your account could not be found.'], 401);

            $pdo->beginTransaction();
            try {
                $cartStmt = $pdo->prepare("SELECT product_id, quantity FROM cart_items WHERE user_id = :user_id ORDER BY id ASC");
                $cartStmt->execute(['user_id' => $userId]);
                $cartItems = $cartStmt->fetchAll();
                if (!$cartItems) {
                    $pdo->rollBack();
                    respond(['success' => false, 'message' => 'Your cart is empty.'], 400);
                }

                $productStmt = $pdo->prepare("SELECT id, name, price, stock, active FROM products WHERE id = :id FOR UPDATE");
                $orderLines = [];
                $total = 0.0;
                foreach ($cartItems as $cartItem) {
                    $productId = (int)$cartItem['product_id'];
                    $quantity = (int)$cartItem['quantity'];
                    $productStmt->execute(['id' => $productId]);
                    $product = $productStmt->fetch();
                    if (!$product || !(int)$product['active']) throw new RuntimeException('One of the products in your cart is no longer available.');
                    if ($quantity < 1) throw new RuntimeException('Your cart contains an invalid quantity.');
                    if ($quantity > (int)$product['stock']) throw new RuntimeException($product['name'] . ' does not have enough stock. Available: ' . (int)$product['stock'] . '.');
                    $unitPrice = (float)$product['price'];
                    $subtotal = round($unitPrice * $quantity, 2);
                    $total += $subtotal;
                    $orderLines[] = ['product_id'=>$productId,'product_name'=>$product['name'],'unit_price'=>$unitPrice,'quantity'=>$quantity,'subtotal'=>$subtotal];
                }

                $orderStmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, phone, address, city, country, total_amount, status, payment_method) VALUES (:user_id, :customer_name, :customer_email, :phone, :address, :city, :country, :total_amount, 'Pending', :payment_method)");
                $orderStmt->execute([
                    'user_id'=>$userId, 'customer_name'=>$name, 'customer_email'=>$user['email'], 'phone'=>$phone,
                    'address'=>$address, 'city'=>$city, 'country'=>$country, 'total_amount'=>round($total,2), 'payment_method'=>$paymentMethod
                ]);
                $orderId = (int)$pdo->lastInsertId();

                $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, subtotal) VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity, :subtotal)");
                $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - :quantity WHERE id = :id");
                foreach ($orderLines as $line) {
                    $itemStmt->execute([
                        'order_id'=>$orderId, 'product_id'=>$line['product_id'], 'product_name'=>$line['product_name'],
                        'unit_price'=>$line['unit_price'], 'quantity'=>$line['quantity'], 'subtotal'=>$line['subtotal']
                    ]);
                    $stockStmt->execute(['quantity'=>$line['quantity'],'id'=>$line['product_id']]);
                }
                $pdo->prepare("DELETE FROM cart_items WHERE user_id = :user_id")->execute(['user_id'=>$userId]);
                $pdo->commit();
                respond(['success'=>true,'message'=>'Order created successfully.','order'=>['id'=>$orderId,'total_amount'=>round($total,2),'status'=>'Pending','payment_method'=>$paymentMethod]],201);
            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                respond(['success'=>false,'message'=>$e->getMessage()],409);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

        default:
            respond([
                'success' => false,
                'message' => 'Unknown API action.'
            ], 404);
    }

} catch (PDOException $e) {
    error_log('Baiku API database error: ' . $e->getMessage());

    respond([
        'success' => false,
        'message' => 'A database error occurred.'
    ], 500);

} catch (Throwable $e) {
    error_log('Baiku API error: ' . $e->getMessage());

    respond([
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ], 500);
}
?>
