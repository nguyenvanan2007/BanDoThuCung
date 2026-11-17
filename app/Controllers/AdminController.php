<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\AdminOrderManager;
use App\Models\AdminProductManager;
use App\Models\User;
use DomainException;
use PDOException;

final class AdminController extends Controller
{
    public function dashboard(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $connection = Database::connection();
        $products = new AdminProductManager($connection);
        $orders = new AdminOrderManager($connection);

        $this->render('admin/dashboard', [
            'title' => 'Quản trị | TPet',
            'activeProductCount' => $products->countActive(),
            'orderStats' => $orders->dashboardStats(),
        ]);
    }

    public function products(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $this->render('admin/products', [
            'title' => 'Quản lý sản phẩm | TPet',
            'products' => (new AdminProductManager(Database::connection()))->findAll(),
        ]);
    }

    public function newProduct(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $this->renderProductForm($this->emptyProduct(), [], false);
    }

    public function createProduct(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        [$data, $errors] = $this->validatedProductData();
        if ($errors !== []) {
            $this->renderProductForm($this->productFormData($data), $errors, false);
            return;
        }

        try {
            (new AdminProductManager(Database::connection()))->create($data);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? (string) $exception->getCode()) !== '23000') {
                throw $exception;
            }

            $this->renderProductForm(
                $this->productFormData($data),
                ['Mã sản phẩm đã được sử dụng cho sản phẩm khác.'],
                false
            );
            return;
        }

        $this->flashSuccess('Đã thêm sản phẩm.');
        $this->redirect('/admin/products');
    }

    public function editProduct(string $productId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $product = (new AdminProductManager(Database::connection()))->findById($productId);
        if ($product === null) {
            (new NotFoundController())->show();
            return;
        }

        $this->renderProductForm($product, [], true);
    }

    public function updateProduct(string $productId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $manager = new AdminProductManager(Database::connection());
        $existingProduct = $manager->findById($productId);
        if ($existingProduct === null) {
            (new NotFoundController())->show();
            return;
        }

        [$data, $errors] = $this->validatedProductData();
        if ($errors !== []) {
            $this->renderProductForm($this->productFormData($data, $productId), $errors, true);
            return;
        }

        try {
            $manager->update($productId, $data);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? (string) $exception->getCode()) !== '23000') {
                throw $exception;
            }

            $this->renderProductForm(
                $this->productFormData($data, $productId),
                ['Mã sản phẩm đã được sử dụng cho sản phẩm khác.'],
                true
            );
            return;
        }

        $this->flashSuccess('Đã lưu thay đổi sản phẩm.');
        $this->redirect('/admin/products');
    }

    public function orders(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $this->render('admin/orders', [
            'title' => 'Quản lý đơn hàng | TPet',
            'orders' => (new AdminOrderManager(Database::connection()))->findAll(),
        ]);
    }

    public function showOrder(string $orderId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $manager = new AdminOrderManager(Database::connection());
        $order = $manager->findById($orderId);
        if ($order === null) {
            (new NotFoundController())->show();
            return;
        }

        $this->render('admin/order-show', [
            'title' => 'Đơn ' . $order['order_number'] . ' | Quản trị TPet',
            'order' => $order,
            'nextStatuses' => $manager->allowedTransitions((string) $order['status']),
        ]);
    }

    public function updateOrderStatus(string $orderId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $nextStatus = $this->postText('status');
        $userId = $_SESSION['user']['id'];
        try {
            (new AdminOrderManager(Database::connection()))->updateStatus($orderId, $nextStatus, $userId);
        } catch (DomainException $exception) {
            $this->flashError($exception->getMessage());
            $this->redirect('/admin/orders/' . rawurlencode($orderId));
        }

        $this->flashSuccess('Đã cập nhật trạng thái đơn hàng.');
        $this->redirect('/admin/orders/' . rawurlencode($orderId));
    }

    private function requireAdmin(): bool
    {
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user) || !is_string($user['id'] ?? null)) {
            $_SESSION['_flash'] = [
                'type' => 'error',
                'message' => 'Vui lòng đăng nhập tài khoản quản trị.',
            ];
            $this->redirect('/login');
        }

        if (($user['role'] ?? '') !== 'admin'
            || !(new User(Database::connection()))->isActiveAdministrator($user['id'])) {
            http_response_code(403);
            $this->render('admin/forbidden', [
                'title' => 'Không có quyền truy cập | TPet',
            ]);
            return false;
        }

        return true;
    }

    private function validatedProductData(): array
    {
        $sku = $this->postText('sku');
        $name = $this->postText('name');
        $species = $this->postText('species');
        $foodType = $this->postText('food_type');
        $description = $this->postText('description');
        $imagePath = $this->postText('image_path');
        $price = $this->postInteger('price_vnd');
        $stock = $this->postInteger('stock_quantity');
        $isActive = $this->postText('is_active') === '1' ? 1 : 0;
        $availableImages = $this->availableProductImages();
        $errors = [];

        if (preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $sku) !== 1) {
            $errors[] = 'Mã sản phẩm chỉ được gồm chữ, số, dấu gạch ngang hoặc gạch dưới (tối đa 64 ký tự).';
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 180) {
            $errors[] = 'Tên sản phẩm cần có từ 2 đến 180 ký tự.';
        }
        if (!in_array($species, ['dog', 'cat'], true)) {
            $errors[] = 'Hãy chọn sản phẩm dành cho chó hoặc mèo.';
        }
        if (!in_array($foodType, ['dry', 'wet'], true)) {
            $errors[] = 'Hãy chọn loại thức ăn khô hoặc ướt.';
        }
        if (mb_strlen($description) > 5000) {
            $errors[] = 'Mô tả không được dài quá 5.000 ký tự.';
        }
        if ($price === null || $price < 1) {
            $errors[] = 'Giá bán phải là số nguyên lớn hơn 0.';
        }
        if ($stock === null || $stock < 0 || $stock > 1000000000) {
            $errors[] = 'Tồn kho phải là số nguyên từ 0 đến 1.000.000.000.';
        }
        if ($imagePath !== ''
            && (!in_array($imagePath, $availableImages, true) || safe_product_image_path($imagePath) === null)) {
            $errors[] = 'Ảnh phải được chọn từ các file có sẵn trong thư mục ảnh sản phẩm.';
        }

        return [[
            'sku' => $sku,
            'name' => $name,
            'species' => $species,
            'food_type' => $foodType,
            'description' => $description,
            'image_path' => $imagePath === '' ? null : $imagePath,
            'price_vnd' => $price ?? 0,
            'stock_quantity' => $stock ?? 0,
            'is_active' => $isActive,
        ], $errors];
    }

    private function renderProductForm(array $product, array $errors, bool $isEditing): void
    {
        $this->render('admin/product-form', [
            'title' => ($isEditing ? 'Sửa sản phẩm' : 'Thêm sản phẩm') . ' | Quản trị TPet',
            'product' => $product,
            'errors' => $errors,
            'isEditing' => $isEditing,
            'images' => $this->availableProductImages(),
        ]);
    }

    private function emptyProduct(): array
    {
        return [
            'id' => '',
            'sku' => '',
            'name' => '',
            'species' => 'dog',
            'foodType' => 'dry',
            'description' => '',
            'imagePath' => null,
            'priceVnd' => '',
            'stockQuantity' => 0,
            'isActive' => true,
        ];
    }

    private function productFormData(array $data, string $id = ''): array
    {
        return [
            'id' => $id,
            'sku' => $data['sku'],
            'name' => $data['name'],
            'species' => $data['species'],
            'foodType' => $data['food_type'],
            'description' => $data['description'],
            'imagePath' => $data['image_path'],
            'priceVnd' => $data['price_vnd'],
            'stockQuantity' => $data['stock_quantity'],
            'isActive' => (bool) $data['is_active'],
        ];
    }

    private function availableProductImages(): array
    {
        $directory = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'images'
            . DIRECTORY_SEPARATOR . 'products';
        $files = glob($directory . DIRECTORY_SEPARATOR . '*') ?: [];
        $images = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $path = '/assets/images/products/' . basename($file);
            if (safe_product_image_path($path) !== null) {
                $images[] = $path;
            }
        }

        sort($images);

        return $images;
    }

    private function postInteger(string $key): ?int
    {
        $value = $_POST[$key] ?? null;
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false ? null : $integer;
    }

    private function postText(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private function hasValidCsrfToken(): bool
    {
        return valid_csrf_token($_POST['_csrf_token'] ?? null);
    }

    private function showForbidden(): void
    {
        http_response_code(403);
        $this->render('admin/forbidden', [
            'title' => 'Yêu cầu không hợp lệ | TPet',
        ]);
    }

    private function flashSuccess(string $message): void
    {
        $_SESSION['_flash'] = [
            'type' => 'success',
            'message' => $message,
        ];
    }

    private function flashError(string $message): void
    {
        $_SESSION['_flash'] = [
            'type' => 'error',
            'message' => $message,
        ];
    }
}
