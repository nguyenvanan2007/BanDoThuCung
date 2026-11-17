<section class="order-history" aria-labelledby="order-history-title">
    <h2 id="order-history-title">Lịch sử trạng thái</h2>
    <?php if ($order['statusHistory'] === []): ?>
        <p class="page-description">Chưa có cập nhật trạng thái.</p>
    <?php else: ?>
        <ol class="order-timeline">
            <?php foreach ($order['statusHistory'] as $entry): ?>
                <li>
                    <time datetime="<?= e(date(DATE_ATOM, strtotime((string) $entry['created_at']))) ?>">
                        <?= e(date('d/m/Y H:i', strtotime((string) $entry['created_at']))) ?>
                    </time>
                    <div class="order-timeline-event">
                        <span class="order-status order-status-<?= e($entry['status']) ?>">
                            <?= e(order_status_label((string) $entry['status'])) ?>
                        </span>
                        <p>
                            <?php if ($entry['changed_by_name'] === null): ?>
                                Trạng thái hiện tại được ghi nhận
                            <?php elseif ($entry['status'] === 'pending'): ?>
                                Đơn hàng được tạo
                            <?php else: ?>
                                Cập nhật bởi <?= e($entry['changed_by_name']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
