<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Order;
use App\Models\ProductCatalogInterface;
use App\Services\OrderNotificationMailer;
use DomainException;
use PHPMailer\PHPMailer\Exception as MailException;
use PDOException;
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
        if ($this->cartQuantities() === []) {
            $_SESSION['_flash'] = [
                'type' => 'error',
                'message' => 'Giỏ hàng đang trống.',
            ];
            $this->redirect('/cart');
        }

        $this->render('checkout/index', [
            'title' => 'Thanh toán COD | TPet',
            'items' => $checkout['items'],
            'subtotal' => $checkout['subtotal'],
            'shippingFee' => $checkout['shippingFee'],
            'total' => $checkout['total'],
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
                'title' => 'Yêu cầu không hợp lệ | TPet',
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
                'title' => 'Thanh toán COD | TPet',
                'items' => $checkout['items'],
                'subtotal' => $checkout['subtotal'],
                'shippingFee' => $checkout['shippingFee'],
                'total' => $checkout['total'],
                'errors' => $errors,
                'recipientName' => $recipientName,
                'phone' => $phone,
                'address' => $address,
                'isDemoMode' => $this->catalog->isDemoMode(),
            ]);
            return;
        }

        try {
            $orderModel = new Order(Database::connection());
            $userId = (string) $_SESSION['user']['id'];
            $orderId = $orderModel->createForCustomer(
                $userId,
                $this->cartQuantities(),
                $recipientName,
                $phone,
                $address,
                $checkout['shippingFee']
            );
        } catch (DomainException $exception) {
            $checkout = $this->checkoutItems();
            $checkout['errors'][] = $exception->getMessage();
            $this->render('checkout/index', [
                'title' => 'Thanh toán COD | TPet',
                'items' => $checkout['items'],
                'subtotal' => $checkout['subtotal'],
                'shippingFee' => $checkout['shippingFee'],
                'total' => null,
                'errors' => array_values(array_unique($checkout['errors'])),
                'recipientName' => $recipientName,
                'phone' => $phone,
                'address' => $address,
                'isDemoMode' => $this->catalog->isDemoMode(),
            ]);
            return;
        }

        $_SESSION['cart_items'] = [];
        try {
            $order = $orderModel->findForCustomer($userId, $orderId);
            if ($order === null) {
                throw new RuntimeException('Không thể tải đơn hàng vừa tạo để gửi email.');
            }
            (new OrderNotificationMailer())->sendOrderConfirmation($order);
            $_SESSION['_flash'] = [
                'type' => 'success',
                'message' => 'Đặt hàng thành công. Email xác nhận đã được gửi; đơn hàng sẽ thanh toán khi nhận hàng (COD).',
            ];
        } catch (MailException | RuntimeException | PDOException $exception) {
            error_log(sprintf(
                'Order confirmation email failed for order %s (%s).',
                $orderId,
                get_class($exception)
            ));
            $_SESSION['_flash'] = [
                'type' => 'error',
                'message' => 'Đơn hàng đã được tạo nhưng email xác nhận chưa gửi được. Vui lòng kiểm tra cấu hình email hoặc liên hệ CSKH.',
            ];
        }
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
            $parts = $this->parseCartKey((string) $productId);
            $product = $parts === null ? null : $this->catalog->findById($parts[0]);
            if ($product === null) {
                $errors[] = 'Một sản phẩm trong giỏ hàng không còn được bán. Hãy xóa sản phẩm đó khỏi giỏ.';
                continue;
            }
            $variant = $parts[1] === null ? null : $this->catalog->findVariant($parts[0], $parts[1]);
            if (($parts[1] !== null && $variant === null)
                || ($product['variants'] !== [] && $variant === null)) {
                $errors[] = sprintf('Biến thể của sản phẩm "%s" không còn được bán.', $product['name']);
                continue;
            }

            if ($product['isDemo']) {
                $errors[] = 'Giỏ hàng có sản phẩm minh họa; không thể dùng sản phẩm này để đặt hàng.';
            }

            $price = $variant['price'] ?? $product['price'];
            $stockQuantity = $variant['stockQuantity'] ?? $product['stockQuantity'];
            if ($quantity > $stockQuantity) {
                $errors[] = sprintf('Sản phẩm "%s" không đủ tồn kho.', $product['name']);
            }

            if ($price > intdiv(PHP_INT_MAX, $quantity)) {
                $errors[] = 'Tổng giá trị giỏ hàng vượt quá giới hạn.';
                continue;
            }

            $lineTotal = $price * $quantity;
            if ($subtotal > PHP_INT_MAX - $lineTotal) {
                $errors[] = 'Tổng giá trị giỏ hàng vượt quá giới hạn.';
                continue;
            }

            $subtotal += $lineTotal;
            $items[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $lineTotal,
            ];
        }

        $shippingFee = $this->shippingFee();
        $total = null;
        if ($subtotal > PHP_INT_MAX - $shippingFee) {
            $errors[] = 'Tổng tiền sau phí giao hàng vượt quá giới hạn.';
        } elseif ($errors === []) {
            $total = $subtotal + $shippingFee;
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'shippingFee' => $shippingFee,
            'total' => $total,
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
            $cartKey = (string) $productId;
            if ($this->parseCartKey($cartKey) !== null
                && is_int($quantity)
                && $quantity > 0) {
                $quantities[$cartKey] = $quantity;
            }
        }

        return $quantities;
    }

    private function parseCartKey(string $cartKey): ?array
    {
        if (preg_match('/\A([1-9][0-9]*)(?::v([1-9][0-9]*))?\z/', $cartKey, $matches) !== 1) {
            return null;
        }

        return [$matches[1], isset($matches[2]) ? $matches[2] : null];
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
