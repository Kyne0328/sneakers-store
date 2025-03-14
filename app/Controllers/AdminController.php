<?php

namespace App\Controllers;

use App\Config\Database;

class AdminController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        session_start();

        // Check if user is admin
        if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            $_SESSION['error'] = 'Access denied. Admin privileges required.';
            header('Location: /php-sneakers-store/public/');
            exit;
        }
    }

    public function index() {
        // Get dashboard statistics
        $stats = [
            'total_orders' => $this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
            'total_revenue' => $this->db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders")->fetchColumn(),
            'total_products' => $this->db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
            'total_users' => $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'low_stock' => $this->db->query("SELECT COUNT(*) FROM products WHERE stock < 10")->fetchColumn()
        ];

        // Get recent orders
        $recent_orders = $this->db->query("
            SELECT o.*, u.name as user_name, a.street_address, a.city, a.state, a.postal_code
            FROM orders o
            JOIN users u ON o.user_id = u.id
            JOIN addresses a ON o.address_id = a.id
            ORDER BY o.created_at DESC
            LIMIT 5
        ")->fetchAll();

        // Get top selling products
        $top_products = $this->db->query("
            SELECT p.*, COUNT(oi.id) as total_sold, SUM(oi.quantity) as total_quantity
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            GROUP BY p.id
            ORDER BY total_quantity DESC
            LIMIT 5
        ")->fetchAll();

        require_once __DIR__ . '/../Views/admin/dashboard.php';
    }

    public function products() {
        // Get all products
        $products = $this->db->query("
            SELECT * FROM products 
            ORDER BY created_at DESC
        ")->fetchAll();

        require_once __DIR__ . '/../Views/admin/products.php';
    }

    public function orders() {
        // Get all orders with user and address details
        $orders = $this->db->query("
            SELECT o.*, u.name as user_name, u.email as user_email,
                   a.street_address, a.city, a.state, a.postal_code, a.phone
            FROM orders o
            JOIN users u ON o.user_id = u.id
            JOIN addresses a ON o.address_id = a.id
            ORDER BY o.created_at DESC
        ")->fetchAll();

        require_once __DIR__ . '/../Views/admin/orders.php';
    }

    public function users() {
        // Get all users
        $users = $this->db->query("
            SELECT * FROM users 
            ORDER BY created_at DESC
        ")->fetchAll();

        require_once __DIR__ . '/../Views/admin/users.php';
    }

    public function addProduct() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /php-sneakers-store/public/admin/products');
            exit;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO products (name, description, price, image, stock)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                $_POST['image'],
                $_POST['stock']
            ]);

            $_SESSION['success'] = 'Product added successfully';
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Failed to add product';
            error_log($e->getMessage());
        }

        header('Location: /php-sneakers-store/public/admin/products');
        exit;
    }

    public function updateProduct() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /php-sneakers-store/public/admin/products');
            exit;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, image = ?, stock = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                $_POST['image'],
                $_POST['stock'],
                $_POST['id']
            ]);

            $_SESSION['success'] = 'Product updated successfully';
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Failed to update product';
            error_log($e->getMessage());
        }

        header('Location: /php-sneakers-store/public/admin/products');
        exit;
    }

    public function deleteProduct() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /php-sneakers-store/public/admin/products');
            exit;
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$_POST['id']]);

            $_SESSION['success'] = 'Product deleted successfully';
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Failed to delete product';
            error_log($e->getMessage());
        }

        header('Location: /php-sneakers-store/public/admin/products');
        exit;
    }

    public function updateOrderStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /php-sneakers-store/public/admin/orders');
            exit;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE orders 
                SET status = ?, tracking_number = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['status'],
                $_POST['tracking_number'] ?? null,
                $_POST['order_id']
            ]);

            $_SESSION['success'] = 'Order status updated successfully';
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Failed to update order status';
            error_log($e->getMessage());
        }

        header('Location: /php-sneakers-store/public/admin/orders');
        exit;
    }

    public function toggleAdmin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /php-sneakers-store/public/admin/users');
            exit;
        }

        try {
            // Get current admin status
            $stmt = $this->db->prepare("SELECT is_admin FROM users WHERE id = ?");
            $stmt->execute([$_POST['user_id']]);
            $current_status = $stmt->fetchColumn();

            // Toggle admin status
            $stmt = $this->db->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
            $stmt->execute([!$current_status, $_POST['user_id']]);

            $_SESSION['success'] = 'User admin status updated successfully';
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Failed to update user admin status';
            error_log($e->getMessage());
        }

        header('Location: /php-sneakers-store/public/admin/users');
        exit;
    }
} 