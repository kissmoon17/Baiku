<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Admin access required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function respondAdmin(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function requestAdminJson(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function requirePost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respondAdmin(['success'=>false,'message'=>'POST request required.'],405);
}
function requireDeleteOrPost(): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST','DELETE'], true)) respondAdmin(['success'=>false,'message'=>'POST or DELETE request required.'],405);
}
function cleanProductInput(array $data): array {
    $name = trim((string)($data['name'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $price = $data['price'] ?? null;
    $category = trim((string)($data['category'] ?? ''));
    $image = trim((string)($data['image'] ?? ''));
    $featured = !empty($data['featured']) ? 1 : 0;
    $stock = filter_var($data['stock'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
    $categories = ['Helmets','Jackets','Pants','Boots'];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) respondAdmin(['success'=>false,'message'=>'Product name must be 2–150 characters.'],400);
    if ($description === '') respondAdmin(['success'=>false,'message'=>'Product description is required.'],400);
    if (!is_numeric($price) || (float)$price < 0) respondAdmin(['success'=>false,'message'=>'Price must be a valid non-negative number.'],400);
    if (!in_array($category, $categories, true)) respondAdmin(['success'=>false,'message'=>'Invalid product category.'],400);
    if ($image === '' || mb_strlen($image) > 500) respondAdmin(['success'=>false,'message'=>'A valid image path or URL is required.'],400);
    if ($stock === false) respondAdmin(['success'=>false,'message'=>'Stock must be a whole number 0 or greater.'],400);
    return [$name,$description,round((float)$price,2),$category,$image,$featured,(int)$stock];
}

$action = $_GET['action'] ?? '';
try {
    switch ($action) {
        case 'dashboard':
            $products = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active = 1")->fetchColumn();
            $customers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
            $orders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
            $revenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status <> 'Cancelled' AND (payment_method = 'Cash on delivery' OR payment_status = 'Paid')")->fetchColumn();
            $stmt = $pdo->query("SELECT id, customer_name, customer_email, total_amount, status, created_at FROM orders ORDER BY created_at DESC, id DESC LIMIT 8");
            $recent = $stmt->fetchAll();
            foreach ($recent as &$row) { $row['id']=(int)$row['id']; $row['total_amount']=(float)$row['total_amount']; }
            respondAdmin(['success'=>true,'stats'=>['products'=>$products,'customers'=>$customers,'orders'=>$orders,'revenue'=>$revenue],'recent_orders'=>$recent]);
            break;

        case 'products':
            $stmt = $pdo->query("SELECT id,name,description,price,category,image,featured,stock,active,created_at,updated_at FROM products ORDER BY created_at DESC,id DESC");
            $items = $stmt->fetchAll();
            foreach ($items as &$p) { $p['id']=(int)$p['id']; $p['price']=(float)$p['price']; $p['featured']=(bool)$p['featured']; $p['stock']=(int)$p['stock']; $p['active']=(bool)$p['active']; }
            respondAdmin(['success'=>true,'products'=>$items]);
            break;

        case 'create_product':
            requirePost();
            [$name,$description,$price,$category,$image,$featured,$stock] = cleanProductInput(requestAdminJson());
            $stmt=$pdo->prepare("INSERT INTO products (name,description,price,category,image,featured,stock,active) VALUES (:name,:description,:price,:category,:image,:featured,:stock,1)");
            $stmt->execute(compact('name','description','price','category','image','featured','stock'));
            respondAdmin(['success'=>true,'message'=>'Product created.','id'=>(int)$pdo->lastInsertId()],201);
            break;

        case 'update_product':
            requirePost();
            $data=requestAdminJson();
            $id=filter_var($data['id']??null,FILTER_VALIDATE_INT);
            if (!$id || $id<1) respondAdmin(['success'=>false,'message'=>'Valid product ID required.'],400);
            [$name,$description,$price,$category,$image,$featured,$stock] = cleanProductInput($data);
            $active = array_key_exists('active',$data) ? (!empty($data['active']) ? 1 : 0) : 1;
            $stmt=$pdo->prepare("UPDATE products SET name=:name,description=:description,price=:price,category=:category,image=:image,featured=:featured,stock=:stock,active=:active WHERE id=:id");
            $stmt->execute(compact('name','description','price','category','image','featured','stock','active','id'));
            if ($stmt->rowCount()===0) {
                $check=$pdo->prepare("SELECT id FROM products WHERE id=:id"); $check->execute(['id'=>$id]);
                if (!$check->fetch()) respondAdmin(['success'=>false,'message'=>'Product not found.'],404);
            }
            respondAdmin(['success'=>true,'message'=>'Product updated.']);
            break;

        case 'delete_product':
            requireDeleteOrPost();
            $data=requestAdminJson();
            $id=filter_var($data['id']??($_GET['id']??null),FILTER_VALIDATE_INT);
            if (!$id || $id<1) respondAdmin(['success'=>false,'message'=>'Valid product ID required.'],400);
            // Soft-delete so existing order history and foreign keys remain intact.
            $stmt=$pdo->prepare("UPDATE products SET active=0 WHERE id=:id");
            $stmt->execute(['id'=>$id]);
            if ($stmt->rowCount()===0) respondAdmin(['success'=>false,'message'=>'Product not found or already inactive.'],404);
            respondAdmin(['success'=>true,'message'=>'Product removed from the storefront.']);
            break;

        case 'orders':
            $stmt=$pdo->query("SELECT id,user_id,customer_name,customer_email,phone,address,city,country,total_amount,status,payment_method,created_at,updated_at FROM orders ORDER BY created_at DESC,id DESC");
            $orders=$stmt->fetchAll();
            foreach ($orders as &$o) { $o['id']=(int)$o['id']; $o['user_id']=$o['user_id']!==null?(int)$o['user_id']:null; $o['total_amount']=(float)$o['total_amount']; }
            respondAdmin(['success'=>true,'orders'=>$orders]);
            break;

        case 'order_items':
            $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
            if (!$id || $id<1) respondAdmin(['success'=>false,'message'=>'Valid order ID required.'],400);
            $stmt=$pdo->prepare("SELECT id,product_id,product_name,unit_price,quantity,subtotal FROM order_items WHERE order_id=:id ORDER BY id ASC");
            $stmt->execute(['id'=>$id]); $items=$stmt->fetchAll();
            foreach($items as &$i){$i['id']=(int)$i['id'];$i['product_id']=$i['product_id']!==null?(int)$i['product_id']:null;$i['unit_price']=(float)$i['unit_price'];$i['quantity']=(int)$i['quantity'];$i['subtotal']=(float)$i['subtotal'];}
            respondAdmin(['success'=>true,'items'=>$items]);
            break;

        case 'update_order_status':
            requirePost();
            $data=requestAdminJson();
            $id=filter_var($data['id']??null,FILTER_VALIDATE_INT);
            $status=trim((string)($data['status']??''));
            $allowed=['Pending','Confirmed','Processing','Shipped','Delivered','Cancelled'];
            if(!$id || !in_array($status,$allowed,true)) respondAdmin(['success'=>false,'message'=>'Valid order ID and status are required.'],400);
            $stmt=$pdo->prepare("UPDATE orders SET status=:status WHERE id=:id"); $stmt->execute(['status'=>$status,'id'=>$id]);
            if($stmt->rowCount()===0){$check=$pdo->prepare("SELECT id FROM orders WHERE id=:id");$check->execute(['id'=>$id]);if(!$check->fetch())respondAdmin(['success'=>false,'message'=>'Order not found.'],404);}
            respondAdmin(['success'=>true,'message'=>'Order status updated.']);
            break;

        case 'customers':
            $stmt=$pdo->query("SELECT u.id,u.name,u.email,u.created_at,COUNT(o.id) AS order_count,COALESCE(SUM(CASE WHEN o.status <> 'Cancelled' THEN o.total_amount ELSE 0 END),0) AS spent FROM users u LEFT JOIN orders o ON o.user_id=u.id WHERE u.role='customer' GROUP BY u.id,u.name,u.email,u.created_at ORDER BY u.created_at DESC,u.id DESC");
            $customers=$stmt->fetchAll();
            foreach($customers as &$c){$c['id']=(int)$c['id'];$c['order_count']=(int)$c['order_count'];$c['spent']=(float)$c['spent'];}
            respondAdmin(['success'=>true,'customers'=>$customers]);
            break;

        default:
            respondAdmin(['success'=>false,'message'=>'Unknown admin action.'],404);
    }
} catch (PDOException $e) {
    error_log('Baiku admin API database error: '.$e->getMessage());
    respondAdmin(['success'=>false,'message'=>'A database error occurred.'],500);
} catch (Throwable $e) {
    error_log('Baiku admin API error: '.$e->getMessage());
    respondAdmin(['success'=>false,'message'=>'An unexpected server error occurred.'],500);
}
