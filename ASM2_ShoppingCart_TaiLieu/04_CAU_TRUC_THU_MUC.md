# CẤU TRÚC THƯ MỤC

```text
asm2/
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── app.js
│   └── images/
├── config/
│   ├── config.php
│   ├── autoload.php
│   └── helpers.php
├── core/
│   ├── Controller.php
│   └── Database.php
├── controllers/
│   ├── frontend/
│   └── backend/
├── models/
├── views/
│   ├── frontend/
│   └── backend/
├── database/
│   └── asm2.sql
└── index.php
```

## Vai trò thư mục
- `assets/`: CSS, JavaScript và hình ảnh tĩnh.
- `config/`: cấu hình hệ thống, autoload và các hàm hỗ trợ.
- `core/`: class nền tảng dùng chung như kết nối database và controller cơ sở.
- `controllers/frontend/`: điều khiển nghiệp vụ phía người dùng.
- `controllers/backend/`: điều khiển nghiệp vụ quản trị.
- `models/`: truy vấn và thao tác dữ liệu.
- `views/frontend/`: giao diện người dùng.
- `views/backend/`: giao diện quản trị.
- `database/`: file SQL khởi tạo database.
- `index.php`: điểm vào chính của ứng dụng.

## Các class/controller được xác định trong tài liệu
- Core: `Database`, `Controller`.
- Model: `Product`, `Category`, `User`, `Order`.
- Frontend controller: `HomeController`, `ProductController`, `CartController`, `AuthController`, `OrderController`.
- Backend controller: `AdminDashboardController`, `AdminProductController`, `AdminCategoryController`, `AdminOrderController`.

Tên file cụ thể bên trong một số thư mục chưa được nêu đầy đủ; cần giữ quy ước đặt tên thống nhất khi triển khai.
