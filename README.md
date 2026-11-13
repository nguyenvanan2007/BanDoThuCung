# Pet House — cửa hàng thức ăn cho chó và mèo

Khung ứng dụng PHP 8.1+, OOP/MVC, Composer PSR-4, PDO và MySQL. Website tập trung bán thức ăn cho chó và mèo.

## Trạng thái hiện tại

Khung MVC, trang chủ, catalog sản phẩm (demo/MySQL), đăng ký/đăng nhập khách hàng, đăng xuất có CSRF, trang tài khoản và giỏ hàng session cho khách/đã đăng nhập đã có. Đặt hàng và quản trị chưa được triển khai; nút đặt hàng trong giỏ đang bị vô hiệu hóa.

## Chạy cục bộ

1. Cài và khởi động PHP trong Laragon.
2. Tại thư mục dự án, chạy `composer install`.
3. Nếu chưa có `.env`, sao chép `.env.example` thành `.env`.
4. Chạy ứng dụng:

   ```powershell
   php -S 127.0.0.1:8000 -t public server.php
   ```

5. Mở `http://127.0.0.1:8000`.

Mặc định `CATALOG_SOURCE=demo` để xem catalog mà không cần database. Các chức năng tài khoản cần MySQL: import `database/schema.sql`, cấu hình thông tin MySQL trong `.env`; tùy chọn import `database/seed_demo.sql` một lần để có sản phẩm minh họa. Đặt `CATALOG_SOURCE=database` để catalog đọc sản phẩm từ MySQL. Schema tạo bảng `users` và `products`; người đăng ký chỉ được tạo với vai trò khách hàng. Không có tài khoản admin mặc định. Sản phẩm seed được đánh dấu DEMO; không dùng làm hàng hóa thật. Nếu `pet_shop` hoặc các bảng này đã tồn tại, hãy sao lưu và kiểm tra trước khi import; script khởi tạo không thay thế migration. Lỗi kết nối/schema sẽ được báo rõ, không tự chuyển về dữ liệu demo.

Không đưa `.env` hoặc thông tin kết nối lên Git.

## Khắc phục lỗi kết nối database

Nếu gửi form tài khoản hiện trang “Dịch vụ tạm thời không khả dụng”, kiểm tra MySQL đang chạy trong Laragon, thông tin `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` trong `.env`, và đã import `database/schema.sql`. Mã MySQL `1045` nghĩa là MySQL từ chối xác thực user; dùng đúng tài khoản MySQL cục bộ và cấp quyền cần thiết trên database `pet_shop`. Không gửi mật khẩu qua chat. Chi tiết lỗi phía ứng dụng chỉ ghi SQLSTATE và mã driver, không ghi thông tin đăng nhập.

## Kiểm tra cú pháp PHP

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```
