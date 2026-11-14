<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Order;
use App\Models\ProductCatalogInterface;
use DomainException;
use RuntimeException;

final class CheckoutController extends Controller
{
    public function __construct(private readonly ProductCatalogInterface $catalog)
    {
    }

    public function show(): void
    {
        if (!$this->requireCustomer()) {
            return;
        }

        $checkout = $this->checkoutItems();
        if ($checkout['items'] === []) {
            $_SESSION['_flash'] = [
                'type' => 'error',
                'message' => 'Giỏ hàng đang trống.',
            ];
            $this->redirect('/cart');
        }

        $this->render('checkout/index', [
            'title' => 'Thanh toán COD | Pet House',
            'items' => $checkout['items'],
            'subtotal' => $checkout['subtotal'],
            'shippingFee' => $this->shippingFee(),
            'errors' => $checkout['errors'],
            'recipientName' => (string) ($_SESSION['user']['name'] ?? ''),
            'phone' => '',
            'address' => '',
            'isDemoMode' => $this->catalog->isDemoMode(),
        ]);
    }

    public function submit(): void
    {
        if (!$this->requireCustomer()) {
            return;
        }

        if (!valid_csrf_token($_POST['_csrf_token'] ?? null)) {
            http_response_code(403);
            $this->render('checkout/error', [
                'title' => 'Yêu cầu không hợp lệ | Pet House',
                'message' => 'Phiên gửi biểu mẫu không hợp lệ. Hãy tải lại trang và thử lại.',
            ]);
            return;
        }

        $recipientName = trim($this->postText('recipient_name'));
        $phone = trim($this->postText('phone'));
        $address = trim($this->postText('address'));
        $errors = $this->validateShippingDetails($recipientName, $phone, $address);
        $checkout = $this->checkoutItems();
        $errors = array_merge($errors, $checkout['errors']);

        if ($this->catalog->isDemoMode()) {
            $errors[] = 'Chế độ dữ liệu minh họa không thể tạo đơn hàng thật.';
        }

        if ($errors !== []) {
            $this->render('checkout/index', [
                'title' => 'Thanh toán COD | Pet House',
                'items' => $checkout['items'],
                'subtotal' => $checkout['subtotal'],
                'shippingFee' => $this->shippingFee(),
                'errors' => $errors,
                'recipientName' => $recipientName,
                'phone' => $phone,
                'address' => $address,
                'isDemoMode' => $this->catalog->isDemoMode(),
            ]);
            return;
        }

        try {
            $orderId = (new Order(Database::connection()))->createForCustomer(
                (string) $_SESSION['user']['id'],
                $this->cartQuantities(),
                $recipientName,
                $phone,
                $address,
                $this->shippingFee()
            );
        } catch (DomainException $exception) {
            $checkout = $this->checkoutItems();
            $checkout['errors'][] = $exception->getMessage();
            $this->render('checkout/index', [
                'title' => 'Thanh toán COD | Pet House',
                'items' => $checkout['items'],
                'subtotal' => $checkout['subtotal'],
                'shippingFee' => $this->shippingFee(),
                'errors' => array_values(array_unique($checkout['errors'])),
                'recipientName' => $recipientName,
                'phone' => $phone,
                'address' => $address,
                'isDemoMode' => $this->catalog->isDemoMode(),
            ]);
            return;
        }

        $_SESSION['cart_items'] = [];
        $_SESSION['_flash'] = [
            'type' => 'success',
            'message' => 'Đặt hàng thành công. Đơn hàng sẽ được thanh toán khi nhận hàng (COD).',
        ];
        $this->redirect('/orders/' . $orderId);
    }

    private function requireCustomer(): bool
    {
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])
            && ($_SESSION['user']['role'] ?? '') === 'customer') {
            return true;
        }

        $_SESSION['_flash'] = [
            'type' => 'error',
            'message' => 'Vui lòng đăng nhập tài khoản khách hàng để thanh toán.',
        ];
        $this->redirect('/login');
    }

    private function checkoutItems(): array
    {
        $items = [];
        $errors = [];
        $subtotal = 0;
        $cart = $this->cartQuantities();

        if ($cart === []) {
            $errors[] = 'Giỏ hàng đang trống.';
        }

        foreach ($cart as $productId => $quantity) {
            $product = $this->catalog->findById((string) $productId);
            if ($product === null) {
                $errors[] = 'Một sản phẩm trong giỏ hàng không còn được bán. Hãy xóa sản phẩm đó khỏi giỏ.';
                continue;
            }

            if ($product['isDemo']) {
                $errors[] = 'Giỏ hàng có sản phẩm minh họa; không thể dùng sản phẩm này để đặt hàng.';
            }

            if ($quantity > $product['stockQuantity']) {
                $errors[] = sprintf('Sản phẩm "%s" không đủ tồn kho.', $product['name']);
            }

            if ($product['price'] > intdiv(PHP_INT_MAX, $quantity)) {
                $errors[] = 'Tổng giá trị giỏ hàng vượt quá giới hạn.';
                continue;
            }

            $lineTotal = $product['price'] * $quantity;
            if ($subtotal > PHP_INT_MAX - $lineTotal) {
                $errors[] = 'Tổng giá trị giỏ hàng vượt quá giới hạn.';
                continue;
            }

            $subtotal += $lineTotal;
            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $lineTotal,
            ];
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private function cartQuantities(): array
    {
        $cart = $_SESSION['cart_items'] ?? [];
        if (!is_array($cart)) {
            return [];
        }

        $quantities = [];
        foreach ($cart as $productId => $quantity) {
            $productId = (string) $productId;
            if (ctype_digit($productId)
                && (int) $productId > 0
                && is_int($quantity)
                && $quantity > 0) {
                $quantities[$productId] = $quantity;
            }
        }

        return $quantities;
    }

    private function validateShippingDetails(string $name, string $phone, string $address): array
    {
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Tên người nhận cần có từ 2 đến 100 ký tự.';
        }

        if (preg_match('/\A[0-9+().\-\s]{7,20}\z/', $phone) !== 1) {
            $errors[] = 'Vui lòng nhập số điện thoại hợp lệ.';
        }

        if (mb_strlen($address) < 10 || mb_strlen($address) > 500) {
            $errors[] = 'Địa chỉ giao hàng cần có từ 10 đến 500 ký tự.';
        }

        return $errors;
    }

    private function shippingFee(): int
    {
        $value = $_ENV['SHIPPING_FEE'] ?? '';
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new RuntimeException('SHIPPING_FEE must be a non-negative integer in VND.');
        }

        $fee = filter_var($value, FILTER_VALIDATE_INT);
        if ($fee === false) {
            throw new RuntimeException('SHIPPING_FEE is outside the supported integer range.');
        }

        return $fee;
    }

    private function postText(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
