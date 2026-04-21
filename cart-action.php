<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$productId = (int)($_POST['product_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

switch ($action) {

    case 'add':
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if ($product) {
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$productId] = [
                    'id'       => $product['id'],
                    'name'     => $product['name_cs'],
                    'name_en'  => $product['name_en'],
                    'price'    => $product['price'],
                    'image'    => $product['image'],
                    'quantity' => $quantity,
                ];
            }

            $cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));

            echo json_encode([
                'success'   => true,
                'message'   => 'Přidáno do košíku!',
                'cartCount' => $cartCount,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Produkt nenalezen.']);
        }
        break;

    case 'increase':
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['quantity']++;
        }
        echo json_encode(['success' => true]);
        break;

    case 'decrease':
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['quantity']--;
            if ($_SESSION['cart'][$productId]['quantity'] <= 0) {
                unset($_SESSION['cart'][$productId]);
            }
        }
        echo json_encode(['success' => true]);
        break;

    case 'remove':
        unset($_SESSION['cart'][$productId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Neplatná akce.']);
}
