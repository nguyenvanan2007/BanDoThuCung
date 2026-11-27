# Cấu trúc dự án

```text
app/
├── Controllers/
│   ├── Admin/
│   └── AuthController.php, CartController.php, CheckoutController.php,
│       OrderController.php, PaymentController.php, ProductController.php
├── Core/
│   ├── Controller.php
│   └── Database.php
├── Helpers/
├── Models/
│   └── Admin/
├── Services/
│   ├── Notification/
│   └── Payment/
└── Views/
    ├── admin/
    │   ├── categories/
    │   ├── coupons/
    │   ├── dashboard/
    │   ├── errors/
    │   ├── inventory/
    │   ├── orders/
    │   ├── products/
    │   ├── reports/
    │   └── users/
    ├── auth/
    ├── cart/
    ├── checkout/
    ├── errors/
    ├── layouts/
    ├── orders/
    ├── partials/
    ├── payments/
    └── products/
config/
database/
├── backups/
└── migrations/
docs/
public/
└── assets/
    ├── css/
    ├── images/
    │   ├── banners/
    │   ├── logo/
    │   └── products/
    └── js/
```

## Vai trò

- `app/Controllers/`: tiếp nhận request và điều phối giữa model, service và view.
- `app/Controllers/Admin/`: controller xử lý các màn hình quản trị hiện tại.
- `app/Core/`: lớp controller nền và kết nối PDO.
- `app/Models/`: truy vấn và thao tác dữ liệu; các manager phục vụ quản trị nằm trong `Admin/`.
- `app/Services/`: tích hợp dịch vụ bên ngoài, được nhóm theo notification và payment.
- `app/Views/`: giao diện được nhóm theo chức năng; `layouts/`, `partials/` và `errors/` dùng chung.
- `config/`: bootstrap và nạp cấu hình môi trường.
- `database/`: schema, dữ liệu mẫu, migration và bản sao lưu cục bộ.
- `docs/`: tài liệu yêu cầu, use case, database và kiến trúc.
- `public/`: document root, front controller, rewrite rules và tài nguyên công khai.

Các URL được dispatch trong `public/index.php`; dự án hiện chưa có router class riêng. Composer PSR-4 ánh xạ namespace `App\\` tới `app/`.
