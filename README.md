# Pet House — cửa hàng thức ăn cho chó và mèo

Khung ứng dụng PHP 8.1+, OOP/MVC, Composer PSR-4, PDO và MySQL. Website tập trung bán thức ăn cho chó và mèo.

## Trạng thái hiện tại

Giai đoạn 2 đã tạo khung MVC, trang chủ, helper escape HTML và lớp kết nối PDO. Danh mục sản phẩm, database schema, giỏ hàng, đặt hàng và quản trị chưa được triển khai.

## Chạy cục bộ

1. Cài và khởi động PHP trong Laragon.
2. Tại thư mục dự án, chạy `composer install`.
3. Nếu chưa có `.env`, sao chép `.env.example` thành `.env`.
4. Chạy ứng dụng:

   ```powershell
   php -S 127.0.0.1:8000 -t public server.php
   ```

5. Mở `http://127.0.0.1:8000`.

Trang khung hiện tại chưa truy vấn database. Khi bắt đầu dùng database, cần bổ sung schema đã được xác nhận và cấu hình thông tin MySQL trong `.env`. Không đưa `.env` hoặc thông tin kết nối lên Git.

## Kiểm tra cú pháp PHP

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```
