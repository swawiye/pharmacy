<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$conn = getConnection();

switch ($action) {

    // Dashboard stats
    case 'dashboard':
        $stats = [];

        // Total medicines
        $r = $conn->query("SELECT COUNT(*) as total FROM medicines");
        $stats['total_medicines'] = $r->fetch_assoc()['total'];

        // Low stock count
        $r = $conn->query("SELECT COUNT(*) as cnt FROM medicines WHERE quantity <= low_stock_threshold");
        $stats['low_stock'] = $r->fetch_assoc()['cnt'];

        // Expired medicines
        $r = $conn->query("SELECT COUNT(*) as cnt FROM medicines WHERE expiry_date < CURDATE()");
        $stats['expired'] = $r->fetch_assoc()['cnt'];

        // Today's sales
        $r = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE DATE(sale_date)=CURDATE()");
        $stats['today_sales'] = $r->fetch_assoc()['total'];

        // Monthly sales
        $r = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())");
        $stats['monthly_sales'] = $r->fetch_assoc()['total'];

        // Low stock items list
        $r = $conn->query("SELECT id, name, quantity, low_stock_threshold, unit FROM medicines WHERE quantity <= low_stock_threshold ORDER BY quantity ASC LIMIT 5");
        $stats['low_stock_items'] = $r->fetch_all(MYSQLI_ASSOC);

        // Recent sales
        $r = $conn->query("SELECT s.*, m.name as medicine_name FROM sales s JOIN medicines m ON s.medicine_id=m.id ORDER BY s.sale_date DESC LIMIT 5");
        $stats['recent_sales'] = $r->fetch_all(MYSQLI_ASSOC);

        echo json_encode($stats);
        break;

        // Log In
        case 'login':
            $data = json_decode(file_get_contents('php://input'), true);
            $user = $data['username'] ?? '';
            $pass = $data['password'] ?? '';

            // Default credentials logic
            if ($user === 'admin' && $pass === 'Admin@123') {
                session_start();
                $_SESSION['user_id'] = 1;
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Invalid username or password']);
            }
            break;
        
        case 'me':
            session_start();
            if (isset($_SESSION['user_id'])) {
                echo json_encode(['id' => $_SESSION['user_id'], 'name' => 'Admin']);
            } else {
                echo 
                json_encode(['id' => null]);
            }
            break;

    // Medicines
    case 'get_medicines':
        $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
        $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
        $stock_filter = $_GET['stock'] ?? '';

        $where = [];
        if ($search) $where[] = "(m.name LIKE '%$search%' OR m.generic_name LIKE '%$search%' OR m.batch_number LIKE '%$search%')";
        if ($category) $where[] = "m.category_id = $category";
        if ($stock_filter === 'low') $where[] = "m.quantity <= m.low_stock_threshold";
        if ($stock_filter === 'expired') $where[] = "m.expiry_date < CURDATE()";
        if ($stock_filter === 'out') $where[] = "m.quantity = 0";

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT m.*, c.name as category_name
                FROM medicines m
                LEFT JOIN categories c ON m.category_id = c.id
                $whereStr
                ORDER BY m.name ASC";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'get_medicine':
        $id = (int)($_GET['id'] ?? 0);
        $r = $conn->query("SELECT * FROM medicines WHERE id=$id");
        echo json_encode($r->fetch_assoc());
        break;

    case 'save_medicine':
        $data = json_decode(file_get_contents('php://input'), true);
        $id            = (int)($data['id'] ?? 0);
        $name          = $conn->real_escape_string($data['name'] ?? '');
        $generic_name  = $conn->real_escape_string($data['generic_name'] ?? '');
        $category_id   = (int)($data['category_id'] ?? 0);
        $batch_number  = $conn->real_escape_string($data['batch_number'] ?? '');
        $quantity      = (int)($data['quantity'] ?? 0);
        $unit          = $conn->real_escape_string($data['unit'] ?? 'pcs');
        $purchase_price = (float)($data['purchase_price'] ?? 0);
        $selling_price  = (float)($data['selling_price'] ?? 0);
        $expiry_date    = $conn->real_escape_string($data['expiry_date'] ?? '');
        $low_stock_threshold = (int)($data['low_stock_threshold'] ?? 10);
        $description   = $conn->real_escape_string($data['description'] ?? '');

        if (!$name) { echo json_encode(['error' => 'Medicine name is required']); break; }

        if ($id) {
            $sql = "UPDATE medicines SET
                name='$name', generic_name='$generic_name', category_id=" . ($category_id ?: 'NULL') . ",
                quantity=$quantity, unit='$unit', purchase_price=$purchase_price,
                selling_price=$selling_price, expiry_date=" . ($expiry_date ? "'$expiry_date'" : 'NULL') . ",
                low_stock_threshold=$low_stock_threshold, description='$description'
                WHERE id=$id";
            $conn->query($sql);
            echo json_encode(['success' => true, 'message' => 'Medicine updated successfully']);
        } else {
            $sql = "INSERT INTO drugs (drug_name, generic_name, category, batch_number, 
                        quantity, unit, purchase_price, selling_price, expiry_date, 
                        low_stock_threshold, description)
                        VALUES ('$name', '$generic_name', '$category_id', '$batch_number', 
                        $quantity, '$unit', $purchase_price, $selling_price, 
                        " . ($expiry_date ? "'$expiry_date'" : 'NULL') . ", 
                        $low_stock_threshold, '$description')";
            $conn->query($sql);
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        }
        break;

    case 'delete_medicine':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM medicines WHERE id=$id");
        echo json_encode(['success' => true, 'message' => 'Medicine deleted']);
        break;

    // Categories
    case 'get_categories':
        $r = $conn->query("SELECT * FROM categories ORDER BY name");
        echo json_encode($r->fetch_all(MYSQLI_ASSOC));
        break;

    case 'save_category':
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($data['id'] ?? 0);
        $name = $conn->real_escape_string($data['name'] ?? '');
        $desc = $conn->real_escape_string($data['description'] ?? '');
        if (!$name) { echo json_encode(['error' => 'Category name required']); break; }
        if ($id) {
            $conn->query("UPDATE categories SET name='$name', description='$desc' WHERE id=$id");
        } else {
            $conn->query("INSERT INTO categories (name, description) VALUES ('$name','$desc')");
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete_category':
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM categories WHERE id=$id");
        echo json_encode(['success' => true]);
        break;

    // Sales
    case 'get_sales':
        $date_from = $conn->real_escape_string($_GET['date_from'] ?? '');
        $date_to   = $conn->real_escape_string($_GET['date_to'] ?? '');
        $where = [];
        if ($date_from) $where[] = "DATE(s.sale_date) >= '$date_from'";
        if ($date_to)   $where[] = "DATE(s.sale_date) <= '$date_to'";
        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT s.*, m.name as medicine_name FROM sales s
                JOIN medicines m ON s.medicine_id = m.id
                $whereStr ORDER BY s.sale_date DESC";
        $r = $conn->query($sql);
        echo json_encode($r->fetch_all(MYSQLI_ASSOC));
        break;

    case 'record_sale':
        $data         = json_decode(file_get_contents('php://input'), true);
        $medicine_id  = (int)($data['medicine_id'] ?? 0);
        $qty_sold     = (int)($data['quantity_sold'] ?? 0);
        $unit_price   = (float)($data['unit_price'] ?? 0);
        $customer     = $conn->real_escape_string($data['customer_name'] ?? '');
        $sold_by      = $conn->real_escape_string($data['sold_by'] ?? '');

        if (!$medicine_id || $qty_sold <= 0) { echo json_encode(['error' => 'Invalid sale data']); break; }

        // Check stock
        $r = $conn->query("SELECT quantity, selling_price FROM medicines WHERE id=$medicine_id");
        $med = $r->fetch_assoc();
        if (!$med) { echo json_encode(['error' => 'Medicine not found']); break; }
        if ($med['quantity'] < $qty_sold) { echo json_encode(['error' => 'Insufficient stock. Available: ' . $med['quantity']]); break; }

        $price = $unit_price ?: $med['selling_price'];
        $total = $price * $qty_sold;

        $conn->begin_transaction();
        try {
            $conn->query("INSERT INTO sales (medicine_id, quantity_sold, unit_price, total_amount, customer_name, sold_by)
                          VALUES ($medicine_id, $qty_sold, $price, $total, '$customer', '$sold_by')");
            $conn->query("UPDATE medicines SET quantity = quantity - $qty_sold WHERE id=$medicine_id");
            $conn->commit();
            echo json_encode(['success' => true, 'total' => $total, 'message' => 'Sale recorded successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => 'Sale failed: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}

$conn->close();
?>
