<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$conn = getConnection();

switch ($action) {
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $conn->real_escape_string($data['username'] ?? '');
        $password = $data['password'] ?? '';
        
        $sql = "SELECT id, username, password, role FROM users WHERE username='$username'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                echo json_encode(['success' => true, 'id' => $user['id'], 'username' => $user['username']]);
                break;
            }
        }
        echo json_encode(['error' => 'Invalid username or password']);
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    case 'me':
        if (isset($_SESSION['user_id'])) {
            echo json_encode(['id' => $_SESSION['user_id'], 'username' => $_SESSION['username'], 'role' => $_SESSION['role']]);
        } else {
            echo json_encode(['error' => 'Not authenticated']);
        }
        break;

    // Categories
    case 'get_categories':
        $sql = "SELECT id, name, description FROM categories ORDER BY name ASC";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'save_category':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $name = $conn->real_escape_string($data['name'] ?? '');
        $description = $conn->real_escape_string($data['description'] ?? '');

        if (!$name) { echo json_encode(['error' => 'Category name is required']); break; }

        if ($id) {
            $conn->query("UPDATE categories SET name='$name', description='$description' WHERE id=$id");
            echo json_encode(['success' => true]);
        } else {
            $conn->query("INSERT INTO categories (name, description) VALUES ('$name', '$description')");
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        }
        break;

    case 'delete_category':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM categories WHERE id=$id");
        echo json_encode(['success' => true]);
        break;

    // Medicines
    case 'medicines':
    case 'get_medicines':
        $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
        $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
        $stock_filter = $_GET['stock'] ?? '';

        $where = [];
        if ($search) $where[] = "(d.drug_name LIKE '%$search%' OR d.generic_name LIKE '%$search%' OR d.batch_number LIKE '%$search%')";
        if ($category) $where[] = "d.category_id = $category";
        if ($stock_filter === 'low') $where[] = "d.quantity <= d.low_stock_threshold AND d.quantity > 0";
        if ($stock_filter === 'expired') $where[] = "d.expiry_date < CURDATE()";
        if ($stock_filter === 'out') $where[] = "d.quantity = 0";

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT d.*, d.drug_id as id, d.drug_name as name, c.name as category_name,
                CASE 
                    WHEN d.expiry_date < CURDATE() THEN 'Expired'
                    WHEN d.quantity = 0 THEN 'Out of Stock'
                    WHEN d.quantity <= d.low_stock_threshold THEN 'Low Stock'
                    ELSE 'In Stock'
                END as status
                FROM drugs d 
                LEFT JOIN categories c ON d.category_id = c.id
                $whereStr ORDER BY d.drug_name ASC";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'get_medicine':
        $id = (int)($_GET['id'] ?? 0);
        $sql = "SELECT *, drug_id as id, drug_name as name FROM drugs WHERE drug_id=$id";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_assoc());
        break;

    case 'save_medicine':
        $data = json_decode(file_get_contents('php://input'), true);
        $id             = (int)($data['id'] ?? 0);
        $name           = $conn->real_escape_string($data['name'] ?? '');
        $generic_name   = $conn->real_escape_string($data['generic_name'] ?? '');
        $category       = (int)($data['category_id'] ?? 0);
        $batch_number   = $conn->real_escape_string($data['batch_number'] ?? '');
        $quantity       = (int)($data['quantity'] ?? 0);
        $unit           = $conn->real_escape_string($data['unit'] ?? 'pcs');
        $purchase_price = (float)($data['purchase_price'] ?? 0);
        $selling_price  = (float)($data['selling_price'] ?? 0);
        $expiry_date    = $conn->real_escape_string($data['expiry_date'] ?? '');
        $low_stock_threshold = (int)($data['low_stock_threshold'] ?? 10);
        $description    = $conn->real_escape_string($data['description'] ?? '');

        if (!$name) { echo json_encode(['error' => 'Medicine name is required']); break; }

        if ($id) {
            $sql = "UPDATE drugs SET 
                drug_name='$name', generic_name='$generic_name', category_id=" . ($category ? $category : 'NULL') . ",
                quantity=$quantity, unit='$unit', purchase_price=$purchase_price,
                selling_price=$selling_price, expiry_date=" . ($expiry_date ? "'$expiry_date'" : 'NULL') . ",
                low_stock_threshold=$low_stock_threshold, description='$description', batch_number='$batch_number'
                WHERE drug_id=$id";
            $conn->query($sql);
            echo json_encode(['success' => true]);
        } else {
            $sql = "INSERT INTO drugs (drug_name, generic_name, category_id, batch_number, quantity, unit, purchase_price, selling_price, expiry_date, low_stock_threshold, description, date_received)
                    VALUES ('$name', '$generic_name', " . ($category ? $category : 'NULL') . ", '$batch_number', $quantity, '$unit', $purchase_price, $selling_price, " . ($expiry_date ? "'$expiry_date'" : 'NULL') . ", $low_stock_threshold, '$description', CURDATE())";
            $conn->query($sql);
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        }
        break;

    case 'delete_medicine':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM drugs WHERE drug_id=$id");
        echo json_encode(['success' => true]);
        break;

    // Sales
    case 'get_sales':
        $date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
        $date_to   = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';

        $where = [];
        if ($date_from) $where[] = "DATE(s.sale_date) >= '$date_from'";
        if ($date_to) $where[] = "DATE(s.sale_date) <= '$date_to'";

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT s.*, d.drug_name as medicine_name 
                FROM sales s 
                LEFT JOIN drugs d ON s.medicine_id = d.drug_id 
                $whereStr 
                ORDER BY s.sale_date DESC";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'record_sale':
        $data = json_decode(file_get_contents('php://input'), true);
        $medicine_id = (int)($data['medicine_id'] ?? 0);
        $qty_sold    = (int)($data['quantity_sold'] ?? 0);
        $unit_price  = (float)($data['unit_price'] ?? 0);
        $customer_name  = $conn->real_escape_string($data['customer_name'] ?? '');
        $sold_by        = $conn->real_escape_string($data['sold_by'] ?? '');

        // Check stock
        $r = $conn->query("SELECT quantity, selling_price FROM drugs WHERE drug_id=$medicine_id");
        $med = $r->fetch_assoc();
        
        if ($med && $med['quantity'] >= $qty_sold) {
            $price = $unit_price ?: $med['selling_price'];
            $total = $price * $qty_sold;
            
            $conn->begin_transaction();
            $sql = "INSERT INTO sales (medicine_id, quantity_sold, unit_price, total_amount, customer_name, sold_by, sale_date) 
                    VALUES ($medicine_id, $qty_sold, $price, $total, '$customer_name', '$sold_by', NOW())";
            $conn->query($sql);
            $conn->query("UPDATE drugs SET quantity = quantity - $qty_sold WHERE drug_id=$medicine_id");
            $conn->commit();
            echo json_encode(['success' => true, 'total' => $total]);
        } else {
            echo json_encode(['error' => 'Insufficient stock']);
        }
        break;

    // Dashboard
    case 'dashboard':
        $stats = [];
        // Total medicines
        $r = $conn->query("SELECT COUNT(*) as total FROM drugs");
        $stats['total_medicines'] = $r->fetch_assoc()['total'];

        // Low stock count
        $r = $conn->query("SELECT COUNT(*) as cnt FROM drugs WHERE quantity <= low_stock_threshold AND quantity > 0");
        $stats['low_stock'] = $r->fetch_assoc()['cnt'];

        // Expired medicines
        $r = $conn->query("SELECT COUNT(*) as cnt FROM drugs WHERE expiry_date < CURDATE()");
        $stats['expired'] = $r->fetch_assoc()['cnt'];

        // Today's sales
        $r = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE DATE(sale_date)=CURDATE()");
        $stats['today_sales'] = $r->fetch_assoc()['total'];

        // Monthly sales
        $r = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())");
        $stats['monthly_sales'] = $r->fetch_assoc()['total'];

        // Low stock items list
        $r = $conn->query("SELECT drug_id as id, drug_name as name, quantity, low_stock_threshold, unit FROM drugs WHERE quantity <= low_stock_threshold ORDER BY quantity ASC LIMIT 5");
        $stats['low_stock_items'] = $r->fetch_all(MYSQLI_ASSOC);

        // Recent sales
        $r = $conn->query("SELECT s.*, d.drug_name as medicine_name FROM sales s JOIN drugs d ON s.medicine_id=d.drug_id ORDER BY s.sale_date DESC LIMIT 5");
        $stats['recent_sales'] = $r->fetch_all(MYSQLI_ASSOC);

        echo json_encode($stats);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']); 
}

$conn->close();
?>
