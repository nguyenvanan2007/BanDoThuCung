<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ProductCatalogInterface;

final class CartController extends Controller
{
    public function __construct(private readonly ProductCatalogInterface $catalog)
    {
    }

    public function index(): void
    {
        $cart = $this->cartItems();
        $items = [];
        $total = 0;
        $hasDemoItems = false;

        foreach ($cart as $productId => $quantity) {
            $product = $this->catalog->findById((string) $productId);
            if ($product === null) {
                $items[] = [
                    'id' => (string) $productId,
                    'quantity' => $quantity,
                    'product' => null,
                    'subtotal' => 0,
                ];
                continue;
            }

            $subtotal = $product['price'] * $quantity;
            $total += $subtotal;
            $hasDemoItems = $hasDemoItems || $product['isDemo'];
            $items[] = [
                'id' => (string) $productId,
                'quantity' => $quantity,
                'product' => $product,
                'subtotal' => $subtotal,
            ];
        }

        $this->render('cart/index', [
            'title' => 'Giỏ hàng | Pet House',
            'items' => $items,
            'total' => $total,
            'hasDemoItems' => $hasDemoItems,
            'catalogDemoMode' => $this->catalog->isDemoMode(),
        ]);
    }

    public function add(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $productId = $this->postInteger('product_id');
        $quantity = $this->postInteger('quantity');
        $product = $productId === null ? null : $this->catalog->findById((string) $productId);

        if ($product === null) {
            $this->flashError('Sản phẩm không tồn tại hoặc đã ngừng bán.');
            $this->redirect('/products');
        }

        $currentCart = $this->cartItems();
        $currentQuantity = $currentCart[(string) $productId] ?? 0;
        $requestedQuantity = $quantity ?? 0;

        if ($requestedQuantity < 1
            || $product['stockQuantity'] < 1
            || $requestedQuantity > $product['stockQuantity'] - $currentQuantity) {
            $this->flashError('Số lượng yêu cầu vượt quá tồn kho hiện có.');
            $this->redirect('/products/' . $productId);
        }

        $currentCart[(string) $productId] = $currentQuantity + $requestedQuantity;
        $_SESSION['cart_items'] = $currentCart;
        $_SESSION['_flash'] = [
            'type' => 'success',
            'message' => 'Đã thêm sản phẩm vào giỏ hàng.',
        ];
        $this->redirect('/cart');
    }

    public function update(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $productId = $this->postInteger('product_id');
        $quantity = $this->postInteger('quantity');
        $cart = $this->cartItems();
        $key = $productId === null ? '' : (string) $productId;

        if ($key === '' || !array_key_exists($key, $cart)) {
            $this->flashError('Sản phẩm không có trong giỏ hàng.');
            $this->redirect('/cart');
        }

        $product = $this->catalog->findById($key);
        if ($product === null || $quantity === null
            || $quantity < 1 || $quantity > $product['stockQuantity']) {
            $this->flashError('Số lượng không hợp lệ hoặc vượt quá tồn kho hiện có.');
            $this->redirect('/cart');
        }

        $cart[$key] = $quantity;
        $_SESSION['cart_items'] = $cart;
        $_SESSION['_flash'] = [
            'type' => 'success',
            'message' => 'Đã cập nhật giỏ hàng.',
        ];
        $this->redirect('/cart');
    }

    public function remove(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $productId = $this->postInteger('product_id');
        $key = $productId === null ? '' : (string) $productId;
        $cart = $this->cartItems();

        if ($key === '' || !array_key_exists($key, $cart)) {
            $this->flashError('Sản phẩm không có trong giỏ hàng.');
            $this->redirect('/cart');
        }

        unset($cart[$key]);
        $_SESSION['cart_items'] = $cart;
        $_SESSION['_flash'] = [
            'type' => 'success',
            'message' => 'Đã xóa sản phẩm khỏi giỏ hàng.',
        ];
        $this->redirect('/cart');
    }

    private function cartItems(): array
    {
        $items = $_SESSION['cart_items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $validItems = [];
        foreach ($items as $productId => $quantity) {
            $productId = (string) $productId;
            if (ctype_digit($productId)
                && (int) $productId > 0
                && is_int($quantity)
                && $quantity > 0) {
                $validItems[$productId] = $quantity;
            }
        }

        return $validItems;
    }

    private function postInteger(string $key): ?int
    {
        $value = $_POST[$key] ?? null;
        if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false ? null : $integer;
    }

    private function hasValidCsrfToken(): bool
    {
        return valid_csrf_token($_POST['_csrf_token'] ?? null);
    }

    private function showForbidden(): void
    {
        http_response_code(403);
        $this->render('cart/error', [
            'title' => 'Yêu cầu không hợp lệ | Pet House',
            'message' => 'Phiên gửi biểu mẫu không hợp lệ. Hãy tải lại trang và thử lại.',
        ]);
    }

    private function flashError(string $message): void
    {
        $_SESSION['_flash'] = [
            'type' => 'error',
            'message' => $message,
        ];
    }
}
