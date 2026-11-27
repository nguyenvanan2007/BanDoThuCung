<section class="page-heading">
    <p class="eyebrow">TPet / Thanh toán</p>
    <h1>Thanh toán qua SePay</h1>
    <p class="page-description">Đơn hàng <?= e($payment['order_number']) ?></p>
</section>

<section class="sepay-payment-panel" aria-live="polite">
    <?php if ($configurationError): ?>
        <h2>Chưa thể tạo mã QR thanh toán</h2>
        <p>Vui lòng liên hệ cửa hàng để được hỗ trợ thanh toán đơn hàng này.</p>
    <?php elseif ($payment['payment_status'] === 'paid' && $payment['order_status'] === 'cancelled'): ?>
        <h2>Đã nhận thanh toán sau khi đơn hàng bị hủy</h2>
        <p>Vui lòng liên hệ cửa hàng để được hướng dẫn hoàn tiền.</p>
    <?php elseif ($payment['payment_status'] === 'paid'): ?>
        <h2>Đã nhận thanh toán</h2>
        <p>SePay đã xác nhận khoản chuyển khoản cho đơn hàng này.</p>
    <?php elseif ($payment['order_status'] === 'cancelled' || $payment['payment_status'] === 'cancelled'): ?>
        <h2>Đơn hàng đã bị hủy</h2>
        <p>Không tiếp tục chuyển khoản cho đơn hàng này.</p>
    <?php elseif ($payment['payment_status'] === 'failed'): ?>
        <h2>Thanh toán không thành công</h2>
        <p>Vui lòng liên hệ cửa hàng để được hỗ trợ.</p>
    <?php elseif ($qrImageUrl !== null): ?>
        <div class="sepay-payment-content">
            <div class="sepay-qr-frame">
                <img
                    class="sepay-qr-code"
                    src="<?= e($qrImageUrl) ?>"
                    alt="Mã QR SePay cho đơn hàng <?= e($payment['order_number']) ?>"
                    referrerpolicy="no-referrer"
                >
            </div>
            <div class="sepay-payment-details">
                <h2>Quét mã QR để chuyển khoản</h2>
                <p>Vui lòng chuyển đúng số tiền và giữ nguyên nội dung thanh toán để hệ thống tự xác nhận.</p>
                <dl>
                    <div>
                        <dt>Số tiền</dt>
                        <dd><?= e(format_vnd((int) $payment['amount_vnd'])) ?></dd>
                    </div>
                    <div>
                        <dt>Nội dung chuyển khoản</dt>
                        <dd><code><?= e($transferDescription ?? '') ?></code></dd>
                    </div>
                    <div>
                        <dt>Ngân hàng</dt>
                        <dd><?= e($accountDetails['bank'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt>Số tài khoản</dt>
                        <dd><?= e($accountDetails['accountNumber'] ?? '') ?></dd>
                    </div>
                    <?php if (($accountDetails['accountName'] ?? '') !== ''): ?>
                        <div>
                            <dt>Chủ tài khoản</dt>
                            <dd><?= e($accountDetails['accountName']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
                <p class="sepay-payment-status" id="sepay-payment-status" role="status">
                    Đang chờ SePay xác nhận chuyển khoản…
                </p>
            </div>
        </div>
    <?php endif; ?>

    <a class="button" href="<?= e($orderUrl) ?>">Xem đơn hàng</a>
</section>

<?php if ($qrImageUrl !== null && $payment['payment_status'] === 'pending'): ?>
    <script>
        (() => {
            const statusMessage = document.getElementById('sepay-payment-status');
            const qrFrame = document.querySelector('.sepay-qr-frame');
            const checkStatus = async () => {
                try {
                    const response = await fetch(<?= json_encode($statusUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    });
                    if (!response.ok) {
                        throw new Error('Không thể kiểm tra trạng thái thanh toán.');
                    }

                    const payment = await response.json();
                    if (payment.paymentStatus === 'paid') {
                        statusMessage.textContent = 'Đã nhận thanh toán. Đang chuyển tới chi tiết đơn hàng…';
                        window.setTimeout(() => window.location.assign(<?= json_encode($orderUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>), 1200);
                        return false;
                    }
                    if (payment.orderStatus === 'cancelled' || payment.paymentStatus === 'cancelled') {
                        statusMessage.textContent = 'Đơn hàng đã bị hủy. Không tiếp tục chuyển khoản.';
                        qrFrame.hidden = true;
                        return false;
                    }

                    statusMessage.textContent = 'Đang chờ SePay xác nhận chuyển khoản…';
                } catch (error) {
                    statusMessage.textContent = 'Chưa kiểm tra được trạng thái. Hệ thống sẽ thử lại sau.';
                }

                return true;
            };

            let timer = window.setInterval(async () => {
                if (!(await checkStatus())) {
                    window.clearInterval(timer);
                }
            }, 5000);
            checkStatus().then((shouldContinue) => {
                if (!shouldContinue) {
                    window.clearInterval(timer);
                }
            });
        })();
    </script>
<?php endif; ?>
