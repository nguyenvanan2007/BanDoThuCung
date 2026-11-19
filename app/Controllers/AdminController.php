<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\AdminOrderManager;
use App\Models\AdminProductManager;
use App\Models\User;
use App\Services\OrderNotificationMailer;
use DomainException;
use PHPMailer\PHPMailer\Exception as MailException;
use PDOException;
use RuntimeException;

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
            'userCount' => count((new User($connection))->findAll()),
        ]);
    }

    public function users(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $this->render('admin/users', [
            'title' => 'Quản lý tài khoản | TPet',
            'users' => (new User(Database::connection()))->findAll(),
            'errors' => [],
        ]);
    }

    public function createUser(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $name = $this->postText('name');
        $email = mb_strtolower($this->postText('email'), 'UTF-8');
        $password = $_POST['password'] ?? '';
        $role = $this->postText('role');
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Họ tên cần có từ 2 đến 100 ký tự.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email không hợp lệ.';
        }
        if (!is_string($password) || mb_strlen($password) < 6) {
            $errors[] = 'Mật khẩu cần có ít nhất 6 ký tự.';
        }
        if (!in_array($role, ['customer', 'admin'], true)) {
            $errors[] = 'Vai trò tài khoản không hợp lệ.';
        }

        $manager = new User(Database::connection());
        if ($errors === [] && !$manager->createManagedAccount($name, $email, $password, $role)) {
            $errors[] = 'Email đã được sử dụng hoặc không thể tạo tài khoản.';
        }

        if ($errors !== []) {
            $this->render('admin/users', [
                'title' => 'Quản lý tài khoản | TPet',
                'users' => $manager->findAll(),
                'errors' => $errors,
            ]);
            return;
        }

        $this->flashSuccess('Đã tạo tài khoản.');
        $this->redirect('/admin/users');
    }

    public function updateUserStatus(string $userId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $isActive = $this->postText('is_active') === '1';
        if (!(new User(Database::connection()))->setActive($userId, $isActive)) {
            $this->flashError('Tài khoản admin không được khóa hoặc mở khóa.');
        } else {
            $this->flashSuccess($isActive ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.');
        }
        $this->redirect('/admin/users');
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

    public function categories(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $manager = new AdminProductManager(Database::connection());
        $this->render('admin/categories', [
            'title' => 'Quản lý danh mục | TPet',
            'categories' => $manager->findCategories(true),
            'errors' => [],
            'categoryName' => '',
            'categorySpecies' => 'dog',
            'categoryFoodType' => 'dry',
        ]);
    }

    public function createCategory(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $name = $this->postText('name');
        $species = $this->postText('species');
        $foodType = $this->postText('food_type');
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Tên danh mục cần có từ 2 đến 120 ký tự.';
        }
        if (!in_array($species, ['dog', 'cat'], true) || !in_array($foodType, ['dry', 'wet'], true)) {
            $errors[] = 'Hãy chọn đúng đối tượng và loại thức ăn.';
        }

        if ($errors === []) {
            $slug = $this->categorySlug($species . '-' . $foodType . '-' . $name);
            try {
                (new AdminProductManager(Database::connection()))->createCategory($name, $slug, $species, $foodType);
            } catch (PDOException $exception) {
                if (($exception->errorInfo[0] ?? (string) $exception->getCode()) !== '23000') {
                    throw $exception;
                }
                $errors[] = 'Danh mục có tên tương tự đã tồn tại cho loại thức ăn này.';
            }
        }

        if ($errors !== []) {
            $this->render('admin/categories', [
                'title' => 'Quản lý danh mục | TPet',
                'categories' => (new AdminProductManager(Database::connection()))->findCategories(true),
                'errors' => $errors,
                'categoryName' => $name,
                'categorySpecies' => $species,
                'categoryFoodType' => $foodType,
            ]);
            return;
        }

        $this->flashSuccess('Đã thêm danh mục.');
        $this->redirect('/admin/categories');
    }

    public function toggleCategory(string $categoryId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $isActive = $this->postText('is_active') === '1';
        (new AdminProductManager(Database::connection()))->setCategoryActive($categoryId, $isActive);
        $this->flashSuccess($isActive ? 'Đã hiển thị danh mục.' : 'Đã ẩn danh mục.');
        $this->redirect('/admin/categories');
    }

    public function newProduct(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $manager = new AdminProductManager(Database::connection());
        $categories = $manager->findCategories();
        $this->renderProductForm($this->emptyProduct($categories), [], false, $categories);
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
        $manager = new AdminProductManager(Database::connection());
        [$data, $errors, $uploadedImagePath] = $this->validatedProductData($manager);
        if ($errors !== []) {
            $this->renderProductForm($this->productFormData($data), $errors, false, $manager->findCategories(true));
            return;
        }

        try {
            $manager->create($data);
        } catch (PDOException $exception) {
            $this->removeUploadedProductImage($uploadedImagePath);
            if (($exception->errorInfo[0] ?? (string) $exception->getCode()) !== '23000') {
                throw $exception;
            }

            $this->renderProductForm(
                $this->productFormData($data),
                ['Mã sản phẩm hoặc SKU biến thể đã được sử dụng.'],
                false,
                $manager->findCategories(true)
            );
            return;
        } catch (\Throwable $exception) {
            $this->removeUploadedProductImage($uploadedImagePath);
            throw $exception;
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

        $this->renderProductForm($product, [], true, (new AdminProductManager(Database::connection()))->findCategories(true));
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

        [$data, $errors, $uploadedImagePath] = $this->validatedProductData($manager);
        if ($errors !== []) {
            $this->renderProductForm($this->productFormData($data, $productId), $errors, true, $manager->findCategories(true));
            return;
        }

        try {
            $manager->update($productId, $data);
        } catch (PDOException $exception) {
            $this->removeUploadedProductImage($uploadedImagePath);
            if (($exception->errorInfo[0] ?? (string) $exception->getCode()) !== '23000') {
                throw $exception;
            }

            $this->renderProductForm(
                $this->productFormData($data, $productId),
                ['Mã sản phẩm hoặc SKU biến thể đã được sử dụng.'],
                true,
                $manager->findCategories(true)
            );
            return;
        } catch (\Throwable $exception) {
            $this->removeUploadedProductImage($uploadedImagePath);
            throw $exception;
        }

        $this->flashSuccess('Đã lưu thay đổi sản phẩm.');
        $this->redirect('/admin/products');
    }

    public function deleteProduct(string $productId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }
        if ($this->postText('confirm_delete') !== '1') {
            $this->flashError('Vui lòng xác nhận trước khi xóa sản phẩm.');
            $this->redirect('/admin/products/' . rawurlencode($productId));
        }

        $manager = new AdminProductManager(Database::connection());
        if ($manager->findById($productId) === null) {
            (new NotFoundController())->show();
            return;
        }
        if (!$manager->delete($productId)) {
            $this->flashError('Sản phẩm đã có trong đơn hàng nên không thể xóa. Hãy ẩn sản phẩm để giữ lịch sử đơn.');
            $this->redirect('/admin/products/' . rawurlencode($productId));
        }

        $this->flashSuccess('Đã xóa sản phẩm.');
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
        $userId = (string) $_SESSION['user']['id'];
        $manager = new AdminOrderManager(Database::connection());
        try {
            $changed = $manager->updateStatus($orderId, $nextStatus, $userId);
        } catch (DomainException $exception) {
            $this->flashError($exception->getMessage());
            $this->redirect('/admin/orders/' . rawurlencode($orderId));
        }

        if (!$changed) {
            $this->flashSuccess('Đơn hàng đã ở trạng thái này.');
            $this->redirect('/admin/orders/' . rawurlencode($orderId));
        }

        try {
            $order = $manager->findById($orderId);
            if ($order === null) {
                throw new RuntimeException('Không thể tải đơn hàng vừa cập nhật để gửi email.');
            }
            (new OrderNotificationMailer())->sendStatusUpdate($order);
            $this->flashSuccess('Đã cập nhật trạng thái đơn hàng và gửi email thông báo cho khách.');
        } catch (MailException | RuntimeException | PDOException $exception) {
            error_log(sprintf(
                'Order status email failed for order %s (%s).',
                $orderId,
                get_class($exception)
            ));
            $this->flashError('Trạng thái đơn đã được cập nhật nhưng email thông báo chưa gửi được. Vui lòng kiểm tra cấu hình email.');
        }
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

    private function validatedProductData(AdminProductManager $manager): array
    {
        $sku = $this->postText('sku');
        $name = $this->postText('name');
        $categoryId = $this->postText('category_id');
        $description = $this->postText('description');
        $price = $this->postInteger('price_vnd');
        $stock = $this->postInteger('stock_quantity');
        $isActive = $this->postText('is_active') === '1' ? 1 : 0;
        $availableImages = $this->availableProductImages();
        $submittedImages = $_POST['images'] ?? [];
        $images = is_array($submittedImages)
            ? array_values(array_unique(array_filter($submittedImages, 'is_string')))
            : [];
        $errors = [];
        $categories = $manager->findCategories(true);
        $category = null;
        foreach ($categories as $candidate) {
            if ((string) $candidate['id'] === $categoryId) {
                $category = $candidate;
                break;
            }
        }

        if (preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $sku) !== 1) {
            $errors[] = 'Mã sản phẩm chỉ được gồm chữ, số, dấu gạch ngang hoặc gạch dưới (tối đa 64 ký tự).';
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 180) {
            $errors[] = 'Tên sản phẩm cần có từ 2 đến 180 ký tự.';
        }
        if ($category === null || !(bool) $category['isActive']) {
            $errors[] = 'Hãy chọn một danh mục đang hoạt động.';
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
        if (count($images) > 10
            || array_diff($images, $availableImages) !== []
            || array_filter($images, static fn (string $image): bool => safe_product_image_path($image) === null) !== []) {
            $errors[] = 'Chỉ được chọn tối đa 10 ảnh có sẵn trong thư mục ảnh sản phẩm.';
        }

        $submittedVariants = $_POST['variants'] ?? [];
        $variants = [];
        $variantSkus = [];
        if (!is_array($submittedVariants) || count($submittedVariants) > 20) {
            $errors[] = 'Sản phẩm được có tối đa 20 biến thể.';
            $submittedVariants = [];
        }
        foreach ($submittedVariants as $variantRow) {
            if (!is_array($variantRow)) {
                $errors[] = 'Thông tin biến thể không hợp lệ.';
                continue;
            }

            $variant = [
                'id' => is_string($variantRow['id'] ?? null) ? trim($variantRow['id']) : '',
                'sku' => is_string($variantRow['sku'] ?? null) ? trim($variantRow['sku']) : '',
                'name' => is_string($variantRow['name'] ?? null) ? trim($variantRow['name']) : '',
                'price_vnd' => $this->integerValue($variantRow['price_vnd'] ?? null),
                'stock_quantity' => $this->integerValue($variantRow['stock_quantity'] ?? null),
            ];
            if ($variant['sku'] === '' && $variant['name'] === ''
                && $variant['price_vnd'] === null && $variant['stock_quantity'] === null) {
                continue;
            }
            if (preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $variant['sku']) !== 1) {
                $errors[] = 'Mã SKU của mỗi biến thể cần từ 1 đến 64 ký tự hợp lệ.';
            }
            if (mb_strlen($variant['name']) < 1 || mb_strlen($variant['name']) > 120) {
                $errors[] = 'Tên biến thể cần có từ 1 đến 120 ký tự.';
            }
            if ($variant['price_vnd'] === null || $variant['price_vnd'] < 1) {
                $errors[] = 'Giá của biến thể phải là số nguyên lớn hơn 0.';
            }
            if ($variant['stock_quantity'] === null
                || $variant['stock_quantity'] < 0 || $variant['stock_quantity'] > 1000000000) {
                $errors[] = 'Tồn kho biến thể phải là số nguyên từ 0 đến 1.000.000.000.';
            }
            if (isset($variantSkus[strtolower($variant['sku'])])) {
                $errors[] = 'SKU của các biến thể phải khác nhau.';
            }
            $variantSkus[strtolower($variant['sku'])] = true;
            $variants[] = $variant;
        }

        $data = [
            'sku' => $sku,
            'name' => $name,
            'species' => (string) ($category['species'] ?? ''),
            'food_type' => (string) ($category['foodType'] ?? ''),
            'category_id' => $categoryId,
            'description' => $description,
            'image_path' => $images[0] ?? null,
            'images' => $images,
            'price_vnd' => $price ?? 0,
            'stock_quantity' => $stock ?? 0,
            'is_active' => $isActive,
            'variants' => $variants,
        ];

        $uploadedImagePath = null;
        if ($errors === []) {
            [$uploadedImagePath, $uploadError] = $this->storeUploadedProductImage();
            if ($uploadError !== null) {
                $errors[] = $uploadError;
            } elseif ($uploadedImagePath !== null) {
                if (count($images) >= 10) {
                    $this->removeUploadedProductImage($uploadedImagePath);
                    $uploadedImagePath = null;
                    $errors[] = 'Chỉ được chọn tối đa 10 ảnh cho mỗi sản phẩm.';
                } else {
                    array_unshift($images, $uploadedImagePath);
                    $data['images'] = $images;
                    $data['image_path'] = $uploadedImagePath;
                }
            }
        }

        return [$data, $errors, $uploadedImagePath];
    }

    private function renderProductForm(array $product, array $errors, bool $isEditing, array $categories): void
    {
        $this->render('admin/product-form', [
            'title' => ($isEditing ? 'Sửa sản phẩm' : 'Thêm sản phẩm') . ' | Quản trị TPet',
            'product' => $product,
            'errors' => $errors,
            'isEditing' => $isEditing,
            'images' => $this->availableProductImages(),
            'categories' => $categories,
        ]);
    }

    private function emptyProduct(array $categories): array
    {
        return [
            'id' => '',
            'sku' => '',
            'name' => '',
            'categoryId' => (string) ($categories[0]['id'] ?? ''),
            'description' => '',
            'imagePath' => null,
            'images' => [],
            'variants' => [],
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
            'categoryId' => $data['category_id'],
            'description' => $data['description'],
            'imagePath' => $data['image_path'],
            'images' => $data['images'],
            'variants' => array_map(
                static fn (array $variant): array => [
                    'id' => $variant['id'],
                    'sku' => $variant['sku'],
                    'name' => $variant['name'],
                    'priceVnd' => $variant['price_vnd'],
                    'stockQuantity' => $variant['stock_quantity'],
                    'isActive' => true,
                ],
                $data['variants']
            ),
            'priceVnd' => $data['price_vnd'],
            'stockQuantity' => $data['stock_quantity'],
            'isActive' => (bool) $data['is_active'],
        ];
    }

    private function categorySlug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $slug = strtolower(is_string($ascii) ? $ascii : $value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return substr($slug, 0, 140);
    }

    private function integerValue(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        if (preg_match('/\A[0-9]+\z/', (string) $value) !== 1) {
            return null;
        }

        $integer = filter_var((string) $value, FILTER_VALIDATE_INT);

        return $integer === false ? null : $integer;
    }

    private function availableProductImages(): array
    {
        $directory = $this->productImageDirectory();
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

    private function storeUploadedProductImage(): array
    {
        $file = $_FILES['image_file'] ?? null;
        if (!is_array($file)) {
            return [null, 'Không nhận được tệp ảnh tải lên.'];
        }

        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return [null, match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ảnh vượt quá dung lượng máy chủ cho phép.',
                UPLOAD_ERR_PARTIAL => 'Ảnh tải lên chưa hoàn tất. Vui lòng thử lại.',
                default => 'Không thể tải ảnh lên. Vui lòng thử lại.',
            }];
        }

        $temporaryPath = $file['tmp_name'] ?? null;
        $fileSize = $file['size'] ?? null;
        if (!is_string($temporaryPath)
            || !is_int($fileSize)
            || $fileSize < 1
            || $fileSize > 5 * 1024 * 1024
            || !is_uploaded_file($temporaryPath)) {
            return [null, 'Ảnh phải hợp lệ và không lớn hơn 5 MB.'];
        }

        $image = @getimagesize($temporaryPath);
        $extension = is_array($image) ? match ($image['mime'] ?? '') {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        } : null;
        if ($extension === null) {
            return [null, 'Định dạng ảnh không hợp lệ. Chỉ chấp nhận JPG, PNG hoặc WebP.'];
        }

        $directory = $this->productImageDirectory();
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            return [null, 'Không thể lưu ảnh vào thư mục sản phẩm.'];
        }

        return ['/assets/images/products/' . $filename, null];
    }

    private function productImageDirectory(): string
    {
        return dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'images'
            . DIRECTORY_SEPARATOR . 'products';
    }

    private function removeUploadedProductImage(?string $imagePath): void
    {
        if ($imagePath === null || safe_product_image_path($imagePath) === null) {
            return;
        }

        $imageFile = $this->productImageDirectory() . DIRECTORY_SEPARATOR . basename($imagePath);
        if (is_file($imageFile)) {
            unlink($imageFile);
        }
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
