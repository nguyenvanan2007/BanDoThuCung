# TPet — cửa hàng thức ăn cho chó và mèo

Khung ứng dụng PHP 8.1+, OOP/MVC, Composer PSR-4, PDO và MySQL. Website tập trung bán thức ăn cho chó và mèo.

## Trạng thái hiện tại

Ứng dụng có catalog sản phẩm (demo/MySQL), tìm kiếm và bộ lọc, quản lý danh mục và tài khoản, biến thể với giá/tồn riêng, nhiều ảnh cho sản phẩm, giỏ hàng, đơn COD, lịch sử trạng thái và email thông báo đơn hàng.

## Chạy cục bộ

1. Cài và khởi động PHP trong Laragon.
2. Tại thư mục dự án, chạy `composer install`.
3. Nếu chưa có `.env`, sao chép `.env.example` thành `.env`.
4. Chạy ứng dụng:

   ```powershell
   php -S 127.0.0.1:8000 -t public server.php
   ```

5. Mở `http://127.0.0.1:8000`.

Mặc định `CATALOG_SOURCE=demo` để xem catalog mà không cần database. Để dùng tài khoản, quản trị và đặt hàng, cấu hình MySQL trong `.env` rồi import `database/schema.sql`; tùy chọn import `database/seed_demo.sql` một lần để thêm sản phẩm minh họa. Đặt `CATALOG_SOURCE=database` để catalog đọc dữ liệu MySQL. Sản phẩm demo không thể đặt hàng. Ảnh sản phẩm phải là ảnh cửa hàng có quyền sử dụng; có thể chép ảnh vào `public/assets/images/products/` để chọn tối đa 10 ảnh có sẵn, hoặc tải thêm một ảnh JPG, PNG hay WebP (tối đa 5 MB) trong form quản trị.

`SHIPPING_FEE` phải là số nguyên không âm tính theo VND; `SHIPPING_FEE=0` nghĩa là miễn phí giao hàng. Phí được tính lại trên server và lưu cùng đơn; tổng tiền gửi từ trình duyệt không được dùng làm căn cứ. Thanh toán hiện hỗ trợ COD, còn số lượng tồn được khóa và xác thực trong transaction trước khi tạo đơn. Nếu giỏ có sản phẩm/biến thể không còn hàng hoặc tổng tiền vượt giới hạn, khách cần cập nhật giỏ trước khi đặt hàng.

Để gửi email xác nhận đơn và thông báo khi trạng thái thay đổi, cấu hình `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` và `MAIL_FROM_ADDRESS` trong `.env` theo nhà cung cấp SMTP. `MAIL_USERNAME` và `MAIL_PASSWORD` phải được cấu hình cùng nhau. Nếu gửi email thất bại, đơn hàng/trạng thái vẫn được lưu; website sẽ báo rõ email chưa gửi được để tránh khách đặt lại đơn.

Với database cũ, sao lưu trước và chạy các migration theo thứ tự, chỉ chạy mỗi migration một lần: `2026_11_14_add_product_images.sql` nếu chưa có cột ảnh; `2026_11_17_add_store_products.sql` nếu cần thêm dữ liệu sản phẩm mẫu của cửa hàng; `2026_11_17_add_order_status_history.sql` nếu chưa có bảng lịch sử trạng thái; cuối cùng chạy `2026_11_19_add_product_catalog_features.sql` để thêm danh mục, thư viện ảnh, biến thể và liên kết biến thể với chi tiết đơn. Migration mới chuyển ảnh đại diện hiện có sang thư viện ảnh và gán sản phẩm vào danh mục ban đầu.

Sau khi đã có cột `image_path` (chạy migration `2026_11_14_add_product_images.sql` trước nếu cần), có thể áp dụng `database/migrations/2026_11_17_add_store_products.sql` để cập nhật giá khởi tạo cho hai SKU hiện có và thêm bốn sản phẩm (hai chó, hai mèo). Migration dùng SKU để tránh thêm trùng khi chạy lại. Giá là mức tham khảo; hãy đối chiếu với giá nhập và giá bán thực tế trước khi kinh doanh.

## Quản trị

Đăng ký tài khoản thường trước, sau đó dùng phpMyAdmin/MySQL cục bộ để cấp quyền quản trị cho đúng email của bạn:

```sql
UPDATE users
SET role = 'admin'
WHERE email = 'an123@gmail.com' AND is_active = 1;
```

Không có tài khoản hoặc mật khẩu admin mặc định. Đăng nhập bằng tài khoản vừa được cấp quyền sẽ chuyển tới `/admin`. Quản trị viên có thể quản lý tài khoản, danh mục theo thú cưng/loại thức ăn, sản phẩm, nhiều ảnh và biến thể (SKU, quy cách, giá, tồn kho), xem đơn và chuyển trạng thái. Khách hàng có thể tự hủy đơn khi còn chờ xác nhận; admin có thể hủy đơn đang chờ hoặc đã xác nhận. Mỗi lần hủy, tồn kho của sản phẩm và biến thể được hoàn lại trong cùng transaction. Sản phẩm đã có trong đơn không thể xóa để bảo toàn lịch sử; hãy ẩn sản phẩm thay thế. Sản phẩm DEMO không xuất hiện trong quản trị.

Ảnh Pedigree trong bản xem thử do chủ dự án cung cấp. Các ảnh minh họa bổ sung trong catalog được lấy từ Wikimedia Commons: [Catfood.jpg](https://commons.wikimedia.org/wiki/File:Catfood.jpg) và [Cat_Food.jpg](https://commons.wikimedia.org/wiki/File:Cat_Food.jpg) thuộc phạm vi công cộng; [Cat eating wet plant-based cat food](https://commons.wikimedia.org/wiki/File:Cat_eating_wet_plant-based_cat_food.jpg) của Jay KapLon theo [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/); [Dog Food](https://commons.wikimedia.org/wiki/File:Dog_Food_(208810939).jpg) của chrismetcalfTV theo [CC BY 2.0](https://creativecommons.org/licenses/by/2.0/). Ảnh Commons chỉ dùng minh họa trong catalog DEMO, không xác nhận bao bì hay SKU đang bán.

Không đưa `.env` hoặc thông tin kết nối lên Git.

## Khắc phục lỗi kết nối database

Nếu gửi form tài khoản hiện trang “Dịch vụ tạm thời không khả dụng”, kiểm tra MySQL đang chạy trong Laragon, thông tin `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` trong `.env`, và đã import `database/schema.sql`. Mã MySQL `1045` nghĩa là MySQL từ chối xác thực user; dùng đúng tài khoản MySQL cục bộ và cấp quyền cần thiết trên database `pet_shop`. Không gửi mật khẩu qua chat. Chi tiết lỗi phía ứng dụng chỉ ghi SQLSTATE và mã driver, không ghi thông tin đăng nhập.

## Kiểm tra cú pháp PHP

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```
