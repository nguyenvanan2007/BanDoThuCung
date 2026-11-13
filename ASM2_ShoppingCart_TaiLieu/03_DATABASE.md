# THIẾT KẾ DATABASE

## Thông tin chung
- Tên database: `asm2`
- Hệ quản trị: MySQL
- File SQL import: `database/asm2.sql`

## Các bảng chính

### 1. `users`
Lưu tài khoản người dùng và admin.
- Khóa chính: `id`
- Ràng buộc: `email` là duy nhất.

### 2. `categories`
Lưu danh mục sản phẩm.
- Khóa chính: `id`

### 3. `products`
Lưu thông tin sản phẩm.
- Khóa chính: `id`
- Khóa ngoại: `category_id` tham chiếu `categories.id`.

### 4. `orders`
Lưu đơn hàng của người dùng.
- Khóa chính: `id`
- Khóa ngoại: `user_id` tham chiếu `users.id`.

### 5. `order_items`
Lưu các sản phẩm thuộc từng đơn hàng.
- Khóa chính: `id`
- Khóa ngoại: `order_id` tham chiếu `orders.id`.
- Khóa ngoại: `product_id` tham chiếu `products.id`.

## Quan hệ dữ liệu
- Một danh mục có thể có nhiều sản phẩm.
- Một người dùng có thể có nhiều đơn hàng.
- Một đơn hàng có thể có nhiều dòng chi tiết trong `order_items`.
- Một sản phẩm có thể xuất hiện trong nhiều dòng chi tiết đơn hàng.

## Lưu ý
Tài liệu nguồn chỉ cung cấp tên bảng và các khóa/liên kết chính, chưa cung cấp đầy đủ kiểu dữ liệu, toàn bộ cột, chỉ mục và quy tắc xóa/cập nhật khóa ngoại. Cần đối chiếu file `database/asm2.sql` trước khi viết truy vấn hoặc thay đổi schema. Không tự tạo cột dựa trên suy đoán.
