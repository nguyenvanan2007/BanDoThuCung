# TPet — cửa hàng thức ăn cho chó và mèo

Khung ứng dụng PHP 8.1+, OOP/MVC, Composer PSR-4, PDO và MySQL. Website tập trung bán thức ăn cho chó và mèo.

## Trạng thái hiện tại

 HEAD
Khung MVC, trang chủ hiển thị danh sách và bộ lọc sản phẩm (demo/MySQL), đăng ký/đăng nhập khách hàng, đăng xuất có CSRF, trang tài khoản, giỏ hàng session, đặt đơn COD và lịch sử đơn hàng đã có. Đường dẫn `/products` chuyển về trang chủ; chi tiết sản phẩm vẫn dùng `/products/{id}`. Quản trị chưa được triển khai.

Khung MVC, trang chủ, catalog sản phẩm (demo/MySQL), đăng ký/đăng nhập, giỏ hàng session, đặt đơn COD và lịch sử đơn hàng đã có. Khu quản trị yêu cầu tài khoản `admin`, cho phép quản lý sản phẩm thật, ảnh trong thư mục cục bộ và tiến trình đơn hàng.
>>>>>>> 7f6584a (thêm role admin, thêm các ảnh vào sản phẩm)

## Chạy cục bộ

1. Cài và khởi động PHP trong Laragon.
2. Tại thư mục dự án, chạy `composer install`.
3. Nếu chưa có `.env`, sao chép `.env.example` thành `.env`.
4. Chạy ứng dụng:

   ```powershell
   php -S 127.0.0.1:8000 -t public server.php
   ```

5. Mở `http://127.0.0.1:8000`.

Mặc định `CATALOG_SOURCE=demo` để xem catalog mà không cần database. Tài khoản, thanh toán và lịch sử đơn cần MySQL: import `database/schema.sql`, cấu hình MySQL trong `.env`; tùy chọn import `database/seed_demo.sql` một lần để có sản phẩm minh họa. Đặt `CATALOG_SOURCE=database` để catalog đọc sản phẩm từ MySQL. Schema tạo bảng `users`, `products`, `orders` và `order_items`; khách đăng nhập mới được đặt hàng, phương thức hiện tại là COD, tồn kho và giá được xác thực lại trong transaction. Sản phẩm seed được đánh dấu DEMO và không thể đặt hàng. Ảnh sản phẩm phải là ảnh thật mà cửa hàng có quyền sử dụng; đặt file tại `public/assets/images/products/` và lưu đường dẫn tương đối như `/assets/images/products/ten-anh.jpg` vào `products.image_path`. Nếu database cũ đã có bảng `products` và `order_items`, chạy migration `database/migrations/2026_11_14_add_product_images.sql` đúng một lần sau khi sao lưu. Nếu database hoặc bảng đã tồn tại, hãy sao lưu và kiểm tra trước khi import; script khởi tạo không thay thế migration. Lỗi kết nối/schema sẽ được báo rõ, không tự chuyển về dữ liệu demo.

## Quản trị

Đăng ký tài khoản thường trước, sau đó dùng phpMyAdmin/MySQL cục bộ để cấp quyền quản trị cho đúng email của bạn:

```sql
UPDATE users
SET role = 'admin'
WHERE email = 'an123@gmail.com' AND is_active = 1;
```

Không có tài khoản hoặc mật khẩu admin mặc định. Đăng nhập bằng tài khoản vừa được cấp quyền sẽ chuyển tới `/admin`. Quản trị viên có thể thêm/sửa sản phẩm thật, chọn ảnh đã chép vào `public/assets/images/products/`, xem đơn và chuyển trạng thái theo luồng. Hủy đơn khi còn chờ xác nhận/đã xác nhận sẽ hoàn tồn kho trong cùng transaction; sản phẩm DEMO không xuất hiện trong quản trị.

Ảnh Pedigree trong bản xem thử do chủ dự án cung cấp. Các ảnh minh họa bổ sung trong catalog được lấy từ Wikimedia Commons: [Catfood.jpg](https://commons.wikimedia.org/wiki/File:Catfood.jpg) và [Cat_Food.jpg](https://commons.wikimedia.org/wiki/File:Cat_Food.jpg) thuộc phạm vi công cộng; [Cat eating wet plant-based cat food](https://commons.wikimedia.org/wiki/File:Cat_eating_wet_plant-based_cat_food.jpg) của Jay KapLon theo [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/); [Dog Food](https://commons.wikimedia.org/wiki/File:Dog_Food_(208810939).jpg) của chrismetcalfTV theo [CC BY 2.0](https://creativecommons.org/licenses/by/2.0/). Ảnh Commons chỉ dùng minh họa trong catalog DEMO, không xác nhận bao bì hay SKU đang bán.

Không đưa `.env` hoặc thông tin kết nối lên Git.

## Khắc phục lỗi kết nối database

Nếu gửi form tài khoản hiện trang “Dịch vụ tạm thời không khả dụng”, kiểm tra MySQL đang chạy trong Laragon, thông tin `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` trong `.env`, và đã import `database/schema.sql`. Mã MySQL `1045` nghĩa là MySQL từ chối xác thực user; dùng đúng tài khoản MySQL cục bộ và cấp quyền cần thiết trên database `pet_shop`. Không gửi mật khẩu qua chat. Chi tiết lỗi phía ứng dụng chỉ ghi SQLSTATE và mã driver, không ghi thông tin đăng nhập.

## Kiểm tra cú pháp PHP

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```
