<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Admin\AdminInventoryManager;
use App\Models\Admin\AdminOrderManager;
use App\Models\Admin\AdminProductManager;
use App\Models\Admin\AdminReportManager;
use App\Models\CouponManager;
use App\Models\User;
use App\Services\Notification\OrderNotificationMailer;
use DomainException;
use PHPMailer\PHPMailer\Exception as MailException;
use PDOException;
use RuntimeException;

final class AdminController extends Controller
{
    protected function render(string $view, array $data = []): void
    {
        if (is_array($_SESSION['user'] ?? null) && ($_SESSION['user']['role'] ?? '') === 'admin') {
            $data['pendingOrderCount'] = (new AdminOrderManager(Database::connection()))
                ->pendingConfirmationCount();
        }

        parent::render($view, $data);
    }

    public function dashboard(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $connection = Database::connection();
        $products = new AdminProductManager($connection);
        $orders = new AdminOrderManager($connection);

        $this->render('admin/dashboard/index', [
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

        $this->render('admin/users/index', [
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
            $this->render('admin/users/index', [
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

        $this->render('admin/products/index', [
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
        $this->render('admin/categories/index', [
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
            $this->render('admin/categories/index', [
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
            $manager->create($data, (string) $_SESSION['user']['id']);
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
            $manager->update($productId, $data, (string) $_SESSION['user']['id']);
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

        $this->render('admin/orders/index', [
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

        $this->render('admin/orders/show', [
            'title' => 'Đơn ' . $order['order_number'] . ' | Quản trị TPet',
            'order' => $order,
            'nextStatuses' => $this->orderTransitions($manager, $order),
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

    public function recordCodPayment(string $orderId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $manager = new AdminOrderManager(Database::connection());
        try {
            $manager->recordCodPayment($orderId);
        } catch (DomainException $exception) {
            $this->flashError($exception->getMessage());
            $this->redirect('/admin/orders/' . rawurlencode($orderId));
        }

        $order = $manager->findById($orderId);
        if ($order === null) {
            throw new RuntimeException('Không thể tải đơn hàng vừa ghi nhận thanh toán.');
        }
        try {
            (new OrderNotificationMailer())->sendPaymentUpdate($order);
            $this->flashSuccess('Đã ghi nhận thu tiền COD và gửi email thông báo.');
        } catch (MailException | RuntimeException $exception) {
            error_log(sprintf(
                'COD payment notification failed for order %s (%s).',
                $orderId,
                get_class($exception)
            ));
            $this->flashError('Đã ghi nhận thanh toán COD nhưng email thông báo chưa gửi được.');
        }
        $this->redirect('/admin/orders/' . rawurlencode($orderId));
    }

    public function recordRefund(string $orderId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->hasValidCsrfToken()) {
            $this->showForbidden();
            return;
        }

        $manager = new AdminOrderManager(Database::connection());
        try {
            $manager->recordRefundComplete(
                $orderId,
                (string) $_SESSION['user']['id'],
                $this->postText('note')
            );
        } catch (DomainException $exception) {
            $this->flashError($exception->getMessage());
            $this->redirect('/admin/orders/' . rawurlencode($orderId));
        }

        $order = $manager->findById($orderId);
        if ($order === null) {
            throw new RuntimeException('Không thể tải đơn hàng vừa hoàn tiền.');
        }
        try {
            (new OrderNotificationMailer())->sendRefundUpdate($order);
            $this->flashSuccess('Đã ghi nhận hoàn tiền và gửi email thông báo.');
        } catch (MailException | RuntimeException $exception) {
            error_log(sprintf(
                'Refund notification failed for order %s (%s).',
                $orderId,
                get_class($exception)
            ));
            $this->flashError('Đã ghi nhận hoàn tiền nhưng email thông báo chưa gửi được.');
        }
        $this->redirect('/admin/orders/' . rawurlencode($orderId));
    }

    public function inventory(): void
    {
            if (!$this->requireAdmin()) {
                return;
            }

            $manager = new AdminInventoryManager(Database::connection());
            $this->render('admin/inventory/index', [
                'title' => 'Quản lý tồn kho | Quản trị TPet',
                'products' => $manager->snapshot(),
                'movements' => $manager->history(),
                'errors' => [],
            ]);
        }

    public function adjustInventory(): void
    {
            if (!$this->requireAdmin()) {
                return;
            }
            if (!$this->hasValidCsrfToken()) {
                $this->showForbidden();
                return;
            }

            $productId = $this->postInteger('product_id');
            $variantId = $this->postInteger('variant_id');
            $deltaValue = $this->postText('quantity_delta');
            $delta = preg_match('/\A-?[0-9]+\z/', $deltaValue) === 1
                ? filter_var($deltaValue, FILTER_VALIDATE_INT)
                : false;
            $type = $this->postText('movement_type');
            $reason = $this->postText('reason');
            if ($productId === null || $delta === false) {
                $this->flashError('Sản phẩm hoặc số lượng điều chỉnh không hợp lệ.');
                $this->redirect('/admin/inventory');
            }

            try {
                (new AdminInventoryManager(Database::connection()))->adjust(
                    (string) $productId,
                    $variantId === null ? null : (string) $variantId,
                    $delta,
                    $type,
                    $reason,
                    (string) $_SESSION['user']['id']
                );
            } catch (DomainException $exception) {
                $this->flashError($exception->getMessage());
                $this->redirect('/admin/inventory');
            }

            $this->flashSuccess('Đã cập nhật tồn kho và ghi lịch sử thay đổi.');
            $this->redirect('/admin/inventory');
        }

    public function coupons(): void
    {
            if (!$this->requireAdmin()) {
                return;
            }

            $form = $this->emptyCoupon();
            $editId = $_GET['edit'] ?? null;
            if (is_string($editId) && preg_match('/\A[1-9][0-9]*\z/', $editId) === 1) {
                $coupon = (new CouponManager(Database::connection()))->findById($editId);
                if ($coupon !== null) {
                    $form = [
                        'id' => (string) $coupon['id'],
                        'code' => (string) $coupon['code'],
                        'discount_type' => (string) $coupon['discount_type'],
                        'discount_value' => (string) $coupon['discount_value'],
                        'starts_at' => str_replace(' ', 'T', substr((string) $coupon['starts_at'], 0, 16)),
                        'ends_at' => str_replace(' ', 'T', substr((string) $coupon['ends_at'], 0, 16)),
                        'min_order_vnd' => (string) $coupon['min_order_vnd'],
                        'max_uses' => $coupon['max_uses'] === null ? '' : (string) $coupon['max_uses'],
                        'allow_stackable' => (int) $coupon['allow_stackable'] === 1,
                        'is_active' => (int) $coupon['is_active'] === 1,
                    ];
                }
            }
            $this->renderCouponPage([], $form);
        }

    public function saveCoupon(): void
    {
            if (!$this->requireAdmin()) {
                return;
            }
            if (!$this->hasValidCsrfToken()) {
                $this->showForbidden();
                return;
            }

            $id = $this->postText('id');
            $id = $id === '' ? null : $id;
            $code = strtoupper($this->postText('code'));
            $type = $this->postText('discount_type');
            $value = $this->postInteger('discount_value');
            $minimum = $this->postInteger('min_order_vnd');
            $maxUsesText = $this->postText('max_uses');
            $maxUses = $maxUsesText === '' ? null : $this->integerValue($maxUsesText);
            $startsInput = $this->postText('starts_at');
            $endsInput = $this->postText('ends_at');
            $starts = $this->couponDateTime($startsInput);
            $ends = $this->couponDateTime($endsInput);
            $errors = [];

            if ($id !== null && preg_match('/\A[1-9][0-9]*\z/', $id) !== 1) {
                $errors[] = 'Mã định danh giảm giá không hợp lệ.';
            }
            if (preg_match('/\A[A-Z0-9_-]{3,40}\z/', $code) !== 1) {
                $errors[] = 'Mã giảm giá cần có 3 đến 40 ký tự chữ, số, gạch ngang hoặc gạch dưới.';
            }
            if (!in_array($type, ['fixed', 'percent'], true)) {
                $errors[] = 'Chọn kiểu giảm giá hợp lệ.';
            }
            if ($value === null || $value < 1 || ($type === 'percent' && $value > 100)) {
                $errors[] = 'Giá trị giảm giá không hợp lệ.';
            }
            if ($minimum === null) {
                $errors[] = 'Giá trị đơn tối thiểu phải là số nguyên không âm.';
            }
            if ($maxUsesText !== '' && ($maxUses === null || $maxUses < 1 || $maxUses > 4294967295)) {
                $errors[] = 'Giới hạn lượt dùng phải để trống hoặc là số nguyên dương.';
            }
            if ($starts === null || $ends === null || $ends <= $starts) {
                $errors[] = 'Thời gian kết thúc phải sau thời gian bắt đầu.';
            }

            $form = [
                'id' => $id ?? '',
                'code' => $code,
                'discount_type' => $type,
                'discount_value' => $value ?? '',
                'starts_at' => $startsInput,
                'ends_at' => $endsInput,
                'min_order_vnd' => $minimum ?? '',
                'max_uses' => $maxUsesText,
                'allow_stackable' => $this->postText('allow_stackable') === '1',
                'is_active' => $this->postText('is_active') === '1',
            ];

            if ($errors === []) {
                try {
                    if ($id !== null && (new CouponManager(Database::connection()))->findById($id) === null) {
                        $errors[] = 'Không tìm thấy mã giảm giá cần sửa.';
                    }
                    if ($errors !== []) {
                        $this->renderCouponPage($errors, $form);
                        return;
                    }
                    (new CouponManager(Database::connection()))->save($id, [
                        'code' => $code,
                        'type' => $type,
                        'value' => $value,
                        'starts' => $starts,
                        'ends' => $ends,
                        'minimum' => $minimum,
                        'max_uses' => $maxUses,
                        'stackable' => $form['allow_stackable'],
                        'active' => $form['is_active'],
                    ]);
                } catch (PDOException $exception) {
                    if (($exception->errorInfo[0] ?? (string) $exception->getCode()) !== '23000') {
                        throw $exception;
                    }
                    $errors[] = 'Mã giảm giá đã tồn tại.';
                }
            }

            if ($errors !== []) {
                $this->renderCouponPage($errors, $form);
                return;
            }

            $this->flashSuccess($id === null ? 'Đã tạo mã giảm giá.' : 'Đã cập nhật mã giảm giá.');
            $this->redirect('/admin/coupons');
        }

    public function toggleCoupon(string $couponId): void
    {
            if (!$this->requireAdmin()) {
                return;
            }
            if (!$this->hasValidCsrfToken()) {
                $this->showForbidden();
                return;
            }

            $active = $this->postText('is_active') === '1';
            if (!(new CouponManager(Database::connection()))->setActive($couponId, $active)) {
                $this->flashError('Không tìm thấy mã giảm giá.');
            } else {
                $this->flashSuccess($active ? 'Đã kích hoạt mã giảm giá.' : 'Đã tạm ngưng mã giảm giá.');
            }
            $this->redirect('/admin/coupons');
        }

    public function reports(): void
    {
            if (!$this->requireAdmin()) {
                return;
            }

            $reports = new AdminReportManager(Database::connection());
            $this->render('admin/reports/index', [
                'title' => 'Báo cáo cửa hàng | Quản trị TPet',
                'orderCounts' => $reports->orderCounts(),
                'dailyRevenue' => $reports->dailyRevenue(),
                'monthlyRevenue' => $reports->monthlyRevenue(),
                'bestSellers' => $reports->bestSellers(),
                'slowSellers' => $reports->slowSellers(),
                'inventoryAlerts' => $reports->inventoryAlerts(),
                'couponUsage' => $reports->couponUsage(),
            ]);
        }

    private function renderCouponPage(array $errors, array $form): void
    {
            $this->render('admin/coupons/index', [
                'title' => 'Mã giảm giá | Quản trị TPet',
                'coupons' => (new CouponManager(Database::connection()))->findAll(),
                'errors' => $errors,
                'form' => $form,
            ]);
        }

    private function emptyCoupon(): array
    {
            return [
                'id' => '',
                'code' => '',
                'discount_type' => 'percent',
                'discount_value' => '',
                'starts_at' => '',
                'ends_at' => '',
                'min_order_vnd' => '0',
                'max_uses' => '',
                'allow_stackable' => false,
                'is_active' => true,
            ];
        }

    private function couponDateTime(string $value): ?string
    {
            $date = \DateTimeImmutable::createFromFormat(
                '!Y-m-d\TH:i',
                $value,
                new \DateTimeZone('Asia/Ho_Chi_Minh')
            );
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date === false || (is_array($errors)
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                return null;
            }

            return $date->format('Y-m-d H:i:s');
        }

        private function orderTransitions(AdminOrderManager $manager, array $order): array
        {
            $nextStatuses = $manager->allowedTransitions((string) $order['status']);
            if ($order['payment_status'] === 'paid') {
                return array_values(array_filter(
                    $nextStatuses,
                    static fn (string $status): bool => $status !== 'cancelled'
                ));
            }
            if (in_array($order['payment_method'], ['sepay', 'legacy_online'], true)) {
                return array_values(array_filter(
                    $nextStatuses,
                    static fn (string $status): bool => $status === 'cancelled'
                ));
            }

            return $nextStatuses;
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
            $this->render('admin/errors/forbidden', [
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
        $this->render('admin/products/form', [
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
        return dirname(__DIR__, 3)
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
        $this->render('admin/errors/forbidden', [
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
