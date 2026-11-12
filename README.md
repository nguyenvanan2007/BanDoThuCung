# Pet House — website thương mại điện tử cho chó và mèo

Ứng dụng PHP 8.1+, MVC/OOP, Composer PSR-4, PDO và MySQL 8. Tài liệu yêu cầu gốc nằm trong `PetShop_Vibecoding_Documentation/`.

## Chạy trên Laragon

1. Cài/khởi động PHP 8.1+, Apache và MySQL 8 trong Laragon.
2. Tạo Virtual Host `petshop.test` trỏ **Document Root** đến `F:\TPET\public`.
3. Tạo database bằng cách chạy `database/schema.sql` trong HeidiSQL hoặc MySQL client. Script không xóa dữ liệu hiện có.
4. Mở terminal tại `F:\TPET`, chạy `composer install`.
5. Sao chép `.env.example` thành `.env`; cập nhật `APP_URL`, thông tin MySQL và phí giao hàng thực tế. Không đưa `.env` lên Git.
   Tạo `APP_KEY` bằng `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` rồi thay giá trị mẫu; không chia sẻ hoặc commit khóa này.
6. Tạo tài khoản quản trị từ terminal:

   ```powershell
   php bin/create-admin.php
   ```

   Nhập email và mật khẩu quản trị riêng (tối thiểu 12 ký tự). Không có tài khoản/mật khẩu mặc định được seed.
7. Mở `http://petshop.test`. Thêm danh mục sản phẩm từ các danh mục mẫu trong database, sau đó tạo sản phẩm ở trang Quản trị → Quản lý sản phẩm.

Có thể chạy thử không cần Virtual Host bằng `php -S 127.0.0.1:8000 -t public server.php`; khi đó đặt `APP_URL=http://127.0.0.1:8000`. Không dùng PHP built-in server cho môi trường triển khai.

## Luồng hiện có

- Trang cửa hàng; tìm theo tên/SKU; lọc loài, danh mục và giá; sắp xếp; trang chi tiết sản phẩm.
- Đăng ký/đăng nhập/đăng xuất và đặt lại mật khẩu token hết hạn một lần với `password_hash`, `password_verify`, giới hạn thử đăng nhập theo email/IP, phân quyền Customer/Admin và CSRF trên mọi POST.
- Giỏ hàng session; kiểm tra số lượng; tạo đơn COD trong transaction; kiểm tra và trừ tồn kho có khóa; voucher phần trăm/số tiền, thời gian, mức tối thiểu, giới hạn tổng lượt và theo người dùng.
- Lịch sử/chi tiết đơn có kiểm tra chủ sở hữu; Admin dashboard, tạo/ẩn sản phẩm, thêm danh mục/thương hiệu/voucher và chuyển trạng thái đơn theo luồng hợp lệ, hủy đơn khôi phục tồn kho.
- Email xác nhận đơn qua PHPMailer khi đã cấu hình SMTP.

## Cấu hình và lưu ý

- Cấu hình bắt buộc: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. `SHIPPING_FEE` phải được đặt theo chính sách giao hàng của cửa hàng; mặc định mẫu là `0`.
- Email không gửi cho đến khi cung cấp cấu hình `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`.
- `APP_DEBUG=true` chỉ dành cho local; đặt `false` khi triển khai. Log ứng dụng ở `storage/logs/app.log`, ngoài web root.
- Database có bảng biến thể, ảnh, thương hiệu, voucher và lịch sử đơn để mở rộng; giao diện hiện tại chưa triển khai đầy đủ quản trị biến thể/ảnh/danh mục/thương hiệu, hồ sơ/địa chỉ, quên mật khẩu, yêu thích, đánh giá, thanh toán điện tử hoặc tải ảnh lên. Không dùng các chức năng này như thể đã sẵn sàng.
- Chưa có dữ liệu sản phẩm/ảnh thực tế. Chỉ nhập thông tin và hình ảnh mà cửa hàng có quyền sử dụng, không dùng dữ liệu demo làm thông tin sản phẩm thật.
- Nếu nâng cấp một database đang có, sao lưu trước và tạo migration phù hợp; `schema.sql` là schema khởi tạo idempotent, không thay thế migration cho các bảng đã tồn tại.

## Kiểm tra

Kiểm tra PHP syntax bằng:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Sau khi nạp schema và cấu hình database, kiểm tra đăng ký/đăng nhập, thêm vào giỏ, đặt COD, tồn kho sau đặt/hủy đơn và truy cập trang đơn bằng một tài khoản khác. Cần có PHP/MySQL và cấu hình local để chạy kiểm thử tích hợp.
