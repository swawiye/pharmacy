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
        // Total medicines (Using 'drugs' table)
        $r = $conn->query("SELECT COUNT(*) as total FROM drugs");
        $stats['total_medicines'] = $r->fetch_assoc()['total'];

        // Low stock count
        $r = $conn->query("SELECT COUNT(*) as cnt FROM drugs WHERE quantity <= low_stock_threshold");
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

        // Low stock items list (Mapping drug_name and drug_id)
        $r = $conn->query("SELECT drug_id as id, drug_name as name, quantity, low_stock_threshold, unit FROM drugs WHERE quantity <= low_stock_threshold ORDER BY quantity ASC LIMIT 5");
        $stats['low_stock_items'] = $r->fetch_all(MYSQLI_ASSOC);

        // Recent sales
        $r = $conn->query("SELECT s.*, d.drug_name as medicine_name FROM sales s JOIN drugs d ON s.medicine_id=d.drug_id ORDER BY s.sale_date DESC LIMIT 5");
        $stats['recent_sales'] = $r->fetch_all(MYSQLI_ASSOC);

        echo json_encode($stats);
        break;

    // Fixed Action Name to match pharmacy.js
    case 'medicines':
    case 'get_medicines':
        $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
        $category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
        $stock_filter = $_GET['stock'] ?? '';

        $where = [];
        if ($search) $where[] = "(drug_name LIKE '%$search%' OR generic_name LIKE '%$search%' OR batch_number LIKE '%$search%')";
        if ($category) $where[] = "category = '$category'"; // Schema uses varchar for category
        if ($stock_filter === 'low') $where[] = "quantity <= low_stock_threshold";
        if ($stock_filter === 'expired') $where[] = "expiry_date < CURDATE()";
        if ($stock_filter === 'out') $where[] = "quantity = 0";

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT *, drug_id as id, drug_name as name FROM drugs $whereStr ORDER BY drug_name ASC";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'save_medicine':
        $data = json_decode(file_get_contents('php://input'), true);
        $id             = (int)($data['id'] ?? 0);
        $name           = $conn->real_escape_string($data['name'] ?? '');
        $generic_name   = $conn->real_escape_string($data['generic_name'] ?? '');
        $category       = $conn->real_escape_string($data['category_id'] ?? '');
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
            // Updated table name to 'drugs' and column to 'drug_name'
            $sql = "UPDATE drugs SET 
                drug_name='$name', generic_name='$generic_name', category='$category',
                quantity=$quantity, unit='$unit', purchase_price=$purchase_price,
                selling_price=$selling_price, expiry_date=" . ($expiry_date ? "'$expiry_date'" : 'NULL') . ",
                low_stock_threshold=$low_stock_threshold, description='$description', batch_number='$batch_number'
                WHERE drug_id=$id";
            $conn->query($sql);
            echo json_encode(['success' => true]);
        } else {
            $sql = "INSERT INTO drugs (drug_name, generic_name, category, batch_number, quantity, unit, purchase_price, selling_price, expiry_date, low_stock_threshold, description, date_received)
                    VALUES ('$name', '$generic_name', '$category', '$batch_number', $quantity, '$unit', $purchase_price, $selling_price, " . ($expiry_date ? "'$expiry_date'" : 'NULL') . ", $low_stock_threshold, '$description', CURDATE())";
            $conn->query($sql);
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        }
        break;

    case 'record_sale':
        $data = json_decode(file_get_contents('php://input'), true);
        $medicine_id = (int)($data['medicine_id'] ?? 0);
        $qty_sold    = (int)($data['quantity_sold'] ?? 0);
        $unit_price  = (float)($data['unit_price'] ?? 0);

        // Check stock in 'drugs' table
        $r = $conn->query("SELECT quantity, selling_price FROM drugs WHERE drug_id=$medicine_id");
        $med = $r->fetch_assoc();
        
        if ($med && $med['quantity'] >= $qty_sold) {
            $price = $unit_price ?: $med['selling_price'];
            $total = $price * $qty_sold;
            
            $conn->begin_transaction();
            $conn->query("INSERT INTO sales (medicine_id, quantity_sold, unit_price, total_amount, sale_date) VALUES ($medicine_id, $qty_sold, $price, $total, NOW())");
            $conn->query("UPDATE drugs SET quantity = quantity - $qty_sold WHERE drug_id=$medicine_id");
            $conn->commit();
            echo json_encode(['success' => true, 'total' => $total]);
        } else {
            echo json_encode(['error' => 'Insufficient stock']);
        }
        break;
    
        default:
        echo json_encode(['error' => 'Unknown action']); 
}

$conn->close();

?>
