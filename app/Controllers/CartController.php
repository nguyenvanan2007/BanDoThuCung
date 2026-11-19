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
        $cartErrors = [];
        $hasDemoItems = false;

        foreach ($cart as $cartKey => $quantity) {
            $parts = $this->parseCartKey((string) $cartKey);
            if ($parts === null) {
                continue;
            }
            [$productId, $variantId] = $parts;
            $product = $this->catalog->findById((string) $productId);
            if ($product === null) {
                $cartErrors[] = 'Một sản phẩm trong giỏ không còn bán; hãy xóa sản phẩm đó trước khi thanh toán.';
                $items[] = [
                    'id' => (string) $cartKey,
                    'productId' => (string) $productId,
                    'variant' => null,
                    'quantity' => $quantity,
                    'product' => null,
                    'subtotal' => 0,
                ];
                continue;
            }

            $variant = $variantId === null ? null : $this->catalog->findVariant((string) $productId, $variantId);
            if (($product['variants'] !== [] && $variant === null) || ($variantId !== null && $variant === null)) {
                $cartErrors[] = 'Một biến thể trong giỏ không còn bán; hãy xóa sản phẩm đó trước khi thanh toán.';
                $items[] = [
                    'id' => (string) $cartKey,
                    'productId' => (string) $productId,
                    'variant' => null,
                    'quantity' => $quantity,
                    'product' => null,
                    'subtotal' => 0,
                ];
                continue;
            }
            $price = $variant['price'] ?? $product['price'];
            $stockQuantity = $variant['stockQuantity'] ?? $product['stockQuantity'];
            if ($quantity > $stockQuantity) {
                $cartErrors[] = sprintf('Sản phẩm "%s" không đủ tồn kho; hãy giảm số lượng.', $product['name']);
            }

            $subtotal = null;
            if ($price <= intdiv(PHP_INT_MAX, $quantity)) {
                $subtotal = $price * $quantity;
                if ($total !== null && $total <= PHP_INT_MAX - $subtotal) {
                    $total += $subtotal;
                } else {
                    $total = null;
                }
            } else {
                $total = null;
            }
            $hasDemoItems = $hasDemoItems || $product['isDemo'];
            $items[] = [
                'id' => (string) $cartKey,
                'productId' => (string) $productId,
                'variant' => $variant,
                'quantity' => $quantity,
                'product' => $product,
                'price' => $price,
                'stockQuantity' => $stockQuantity,
                'subtotal' => $subtotal,
            ];
        }

        $this->render('cart/index', [
            'title' => 'Giỏ hàng | TPet',
            'items' => $items,
            'total' => $total,
            'cartErrors' => array_values(array_unique($cartErrors)),
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
        $variantId = $this->postInteger('variant_id');
        $quantity = $this->postInteger('quantity');
        $product = $productId === null ? null : $this->catalog->findById((string) $productId);

        if ($product === null) {
            $this->flashError('Sản phẩm không tồn tại hoặc đã ngừng bán.');
            $this->redirect('/');
        }

        $variant = $variantId === null
            ? null
            : $this->catalog->findVariant((string) $productId, (string) $variantId);
        if (($product['variants'] !== [] && $variant === null) || ($variantId !== null && $variant === null)) {
            $this->flashError('Vui lòng chọn một biến thể đang được bán.');
            $this->redirect('/products/' . $productId);
        }

        $cartKey = $this->makeCartKey((string) $productId, $variantId === null ? null : (string) $variantId);
        $stockQuantity = $variant['stockQuantity'] ?? $product['stockQuantity'];
        $currentCart = $this->cartItems();
        $currentQuantity = $currentCart[$cartKey] ?? 0;
        $requestedQuantity = $quantity ?? 0;

        if ($requestedQuantity < 1
            || $stockQuantity < 1
            || $requestedQuantity > $stockQuantity - $currentQuantity) {
            $this->flashError('Số lượng yêu cầu vượt quá tồn kho hiện có.');
            $this->redirect('/products/' . $productId);
        }

        $currentCart[$cartKey] = $currentQuantity + $requestedQuantity;
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

        $cartKey = $this->postCartKey();
        $quantity = $this->postInteger('quantity');
        $cart = $this->cartItems();

        if ($cartKey === null || !array_key_exists($cartKey, $cart)) {
            $this->flashError('Sản phẩm không có trong giỏ hàng.');
            $this->redirect('/cart');
        }

        $parts = $this->parseCartKey($cartKey);
        $product = $parts === null ? null : $this->catalog->findById($parts[0]);
        $variant = $parts === null || $parts[1] === null
            ? null
            : $this->catalog->findVariant($parts[0], $parts[1]);
        $stockQuantity = $variant['stockQuantity'] ?? $product['stockQuantity'] ?? 0;
        if ($product === null || ($parts[1] !== null && $variant === null)
            || ($product['variants'] !== [] && $variant === null)
            || $quantity === null || $quantity < 1 || $quantity > $stockQuantity) {
            $this->flashError('Số lượng không hợp lệ hoặc vượt quá tồn kho hiện có.');
            $this->redirect('/cart');
        }

        $cart[$cartKey] = $quantity;
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

        $key = $this->postCartKey();
        $cart = $this->cartItems();

        if ($key === null || !array_key_exists($key, $cart)) {
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
            $cartKey = (string) $productId;
            if ($this->parseCartKey($cartKey) !== null
                && is_int($quantity)
                && $quantity > 0) {
                $validItems[$cartKey] = $quantity;
            }
        }

        return $validItems;
    }

    private function makeCartKey(string $productId, ?string $variantId): string
    {
        return $variantId === null ? $productId : $productId . ':v' . $variantId;
    }

    private function parseCartKey(string $cartKey): ?array
    {
        if (preg_match('/\A([1-9][0-9]*)(?::v([1-9][0-9]*))?\z/', $cartKey, $matches) !== 1) {
            return null;
        }

        return [$matches[1], isset($matches[2]) ? $matches[2] : null];
    }

    private function postCartKey(): ?string
    {
        $value = $_POST['cart_key'] ?? null;

        return is_string($value) && $this->parseCartKey($value) !== null ? $value : null;
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
            'title' => 'Yêu cầu không hợp lệ | TPet',
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
