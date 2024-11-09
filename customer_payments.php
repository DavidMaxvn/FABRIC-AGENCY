<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'connect.php';
include 'nav.php'; // Bao gồm thanh điều hướng

// Xử lý khi form thêm thanh toán mới được gửi
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = $_POST['customer_id'];
    $amount = $_POST['amount'];
    $payment_date = date('Y-m-d');

    // Thêm thanh toán vào Customer_Payments
    $sql = "INSERT INTO Customer_Payments (customer_id, payment_date, amount) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$customer_id, $payment_date, $amount]);

    // Cập nhật công nợ của khách hàng
    $update_sql = "UPDATE Customers SET outstanding_balance = outstanding_balance + ? WHERE customer_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([$amount, $customer_id]);

    // Kiểm tra công nợ hiện tại để cập nhật trạng thái cảnh báo
    $balance_check_sql = "SELECT outstanding_balance FROM Customers WHERE customer_id = ?";
    $balance_stmt = $conn->prepare($balance_check_sql);
    $balance_stmt->execute([$customer_id]);
    $outstanding_balance = $balance_stmt->fetchColumn();

    if ($outstanding_balance > 2000) {
        // Đặt cảnh báo nếu công nợ vượt quá $2000
        $warning_sql = "UPDATE Customers SET warning_status = 1, warning_start_date = COALESCE(warning_start_date, CURDATE()) WHERE customer_id = ?";
        $conn->prepare($warning_sql)->execute([$customer_id]);
    } else {
        // Tắt cảnh báo nếu công nợ không còn quá $2000
        $warning_sql = "UPDATE Customers SET warning_status = 0, warning_start_date = NULL WHERE customer_id = ?";
        $conn->prepare($warning_sql)->execute([$customer_id]);
    }

    header("Location: customer_payments.php");
    exit();
}

// Lấy danh sách thanh toán
$stmt = $conn->query("SELECT Customer_Payments.*, Customers.first_name, Customers.last_name FROM Customer_Payments
                      JOIN Customers ON Customer_Payments.customer_id = Customers.customer_id");
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách khách hàng để hiển thị trong form
$customers = $conn->query("SELECT customer_id, first_name, last_name, outstanding_balance FROM Customers")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Thanh Toán Khách Hàng</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            width: 100%;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
        }
        th {
            background-color: #333;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
        }
        form label {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        form select, form input[type="number"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            outline: none;
        }
        form button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }
        form button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Danh Sách Thanh Toán Khách Hàng</h1>

        <!-- Bảng hiển thị danh sách thanh toán -->
        <table>
            <tr>
                <th>ID</th>
                <th>Khách Hàng</th>
                <th>Ngày Thanh Toán</th>
                <th>Số Tiền (USD)</th>
            </tr>
            <?php if (!empty($payments)): ?>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= htmlspecialchars($payment['payment_id']); ?></td>
                        <td><?= htmlspecialchars($payment['first_name'] . " " . $payment['last_name']); ?></td>
                        <td><?= htmlspecialchars($payment['payment_date']); ?></td>
                        <td>$<?= htmlspecialchars(number_format($payment['amount'], 2)); ?> USD</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center;">Không có thanh toán nào.</td>
                </tr>
            <?php endif; ?>
        </table>

        <h2>Thêm Thanh Toán Mới</h2>
        <form action="customer_payments.php" method="POST">
            <label for="customer_id">Khách Hàng:</label>
            <select name="customer_id" required>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= htmlspecialchars($customer['customer_id']) ?>">
                        <?= htmlspecialchars($customer['first_name'] . " " . $customer['last_name']) ?> - Công nợ: $<?= htmlspecialchars(number_format($customer['outstanding_balance'], 2)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="amount">Số Tiền Thanh Toán (USD):</label>
            <input type="number" name="amount" step="0.01" required>

            <button type="submit">Xác Nhận Thanh Toán</button>
        </form>
    </div>
</body>
</html>