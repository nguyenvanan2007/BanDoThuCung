<section class="page-heading">
    <p class="eyebrow">TPet / Thanh toán</p>
    <h1>Kết quả thanh toán</h1>
    <p class="page-description">Đơn hàng <?= e($order['order_number']) ?></p>
</section>

<section class="empty-state">
    <?php if ($order['payment_status'] === 'paid' && $order['status'] === 'cancelled'): ?>
        <h2>Thanh toán được xác nhận sau khi đơn đã hủy</h2>
        <p>Vui lòng liên hệ CSKH để được hướng dẫn hoàn tiền. Đơn đã hủy và không được tiếp tục xử lý.</p>
    <?php elseif ($order['payment_status'] === 'paid'): ?>
        <h2>Thanh toán đã được xác nhận</h2>
        <p>VNPAY đã xác nhận giao dịch thành công.</p>
    <?php elseif ($order['payment_status'] === 'failed'): ?>
        <h2>Thanh toán không thành công</h2>
        <p>Đơn hàng đã được hủy và tồn kho đã được hoàn lại. Bạn có thể đặt hàng lại.</p>
    <?php else: ?>
        <h2>Đang chờ xác nhận thanh toán</h2>
        <p>Trạng thái chỉ được cập nhật sau khi máy chủ nhận được xác nhận giao dịch từ VNPAY.</p>
    <?php endif; ?>
    <a class="button" href="/orders/<?= e($order['id']) ?>">Xem đơn hàng</a>
</section>
