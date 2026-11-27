# NGHIỆP VỤ HỆ THỐNG

## 1. Tổng quan
ASM2 là website Shopping Cart. Người dùng có thể xem và mua sản phẩm; quản trị viên quản lý danh mục, sản phẩm và đơn hàng.

## 2. Vai trò
- Khách: xem danh sách sản phẩm, tìm kiếm/lọc và xem chi tiết.
- Người dùng đã đăng ký/đăng nhập: sử dụng giỏ hàng, đặt hàng và xem đơn hàng của mình.
- Admin: truy cập khu vực quản trị và quản lý dữ liệu hệ thống.

## 3. Nghiệp vụ phía người dùng (Frontend)
1. Đăng ký tài khoản mới.
2. Đăng nhập và đăng xuất.
3. Xem danh sách sản phẩm đang hiển thị.
4. Tìm kiếm sản phẩm theo tên.
5. Lọc sản phẩm theo danh mục.
6. Xem thông tin chi tiết sản phẩm.
7. Thêm sản phẩm vào giỏ hàng.
8. Thay đổi số lượng hoặc xóa sản phẩm khỏi giỏ hàng.
9. Thanh toán/đặt hàng sau khi đăng nhập.
10. Xem danh sách đơn hàng đã đặt.

## 4. Nghiệp vụ quản trị (Backend)
1. Admin đăng nhập.
2. Xem dashboard: tổng số sản phẩm, tổng số đơn hàng và doanh thu.
3. Quản lý danh mục: xem, thêm, sửa, xóa.
4. Quản lý sản phẩm: xem, tìm kiếm, thêm, sửa, xóa.
5. Tải lên hình ảnh sản phẩm.
6. Quản lý đơn hàng và cập nhật trạng thái đơn hàng.

## 5. Luồng tổng quát đặt hàng
Người dùng xem sản phẩm → chọn sản phẩm → thêm vào giỏ → cập nhật giỏ hàng → đăng nhập (nếu chưa đăng nhập) → thanh toán/đặt hàng → hệ thống lưu đơn hàng và chi tiết đơn hàng → người dùng xem lại đơn hàng.

## 6. Quy tắc nghiệp vụ cần tuân thủ
- Chỉ người dùng đã đăng nhập mới được thanh toán.
- Người dùng chỉ được xem các đơn hàng thuộc tài khoản của mình.
- Chỉ admin được truy cập các chức năng quản trị.
- Khi cập nhật trạng thái đơn hàng, lưu trạng thái mới vào cơ sở dữ liệu.
- Khi xóa hoặc sửa dữ liệu, cần kiểm tra tính hợp lệ và các liên kết liên quan.
- Chi tiết về phương thức thanh toán, trạng thái đơn hàng cụ thể, tồn kho và chính sách hủy đơn chưa được đặc tả trong tài liệu gốc; không tự ý giả định khi triển khai.
