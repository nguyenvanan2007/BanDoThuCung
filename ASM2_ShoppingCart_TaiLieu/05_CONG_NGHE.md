# CÔNG NGHỆ VÀ THƯ VIỆN

## Công nghệ chính
| Công nghệ | Vai trò |
|---|---|
| PHP | Xử lý backend |
| PHP OOP | Tổ chức mã nguồn theo hướng đối tượng |
| MVC | Tách Model, View và Controller |
| MySQL | Lưu trữ dữ liệu |
| HTML | Xây dựng cấu trúc giao diện |
| CSS | Định dạng giao diện |
| JavaScript | Tương tác phía trình duyệt |

## Thư viện/kỹ thuật
| Công nghệ | Vị trí sử dụng | Mục đích |
|---|---|---|
| Bootstrap 5 | Layout frontend/backend | Responsive, navbar, form, table, card, alert |
| jQuery | `assets/js/app.js` và layout | Xác nhận xóa, kiểm tra form đăng ký và thanh toán |
| PDO | `core/Database.php` và các Model | Kết nối MySQL, prepared statements |
| PHP Session | AuthController, CartController | Lưu trạng thái đăng nhập và giỏ hàng |

## Môi trường chạy
- Laragon
- Apache
- MySQL
- phpMyAdmin để tạo database và import SQL
- Trình duyệt web

## Cài đặt cơ bản
1. Đặt thư mục dự án tại `C:\laragon\www\asm2`.
2. Khởi động Apache và MySQL trong Laragon.
3. Tạo database tên `asm2` trong phpMyAdmin.
4. Import `database/asm2.sql`.
5. Truy cập `http://localhost/asm2/`.
6. Đăng nhập admin bằng tài khoản mẫu được ghi trong tài liệu dự án.

## Tài khoản mẫu trong tài liệu nguồn
- Admin: `admin@gmail.com` / `admin123`
- User: `user@gmail.com` / `user123`

Lưu ý: Đây là thông tin mẫu từ tài liệu, nên đổi mật khẩu khi triển khai thực tế. Không đưa mật khẩu thật vào mã nguồn công khai.
