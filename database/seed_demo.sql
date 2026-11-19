USE pet_shop;

INSERT INTO products (sku, name, species, food_type, category_id, description, price_vnd, stock_quantity, is_demo)
VALUES
    ('DEMO-DOG-DRY-A', 'Thức ăn khô cho chó — mẫu minh họa A', 'dog', 'dry', (SELECT id FROM product_categories WHERE slug = 'dog-dry'), 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.', 219000, 10, 1),
    ('DEMO-DOG-WET-B', 'Thức ăn ướt cho chó — mẫu minh họa B', 'dog', 'wet', (SELECT id FROM product_categories WHERE slug = 'dog-wet'), 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.', 45000, 10, 1),
    ('DEMO-CAT-DRY-A', 'Thức ăn khô cho mèo — mẫu minh họa A', 'cat', 'dry', (SELECT id FROM product_categories WHERE slug = 'cat-dry'), 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.', 179000, 10, 1),
    ('DEMO-CAT-WET-B', 'Thức ăn ướt cho mèo — mẫu minh họa B', 'cat', 'wet', (SELECT id FROM product_categories WHERE slug = 'cat-wet'), 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.', 32000, 10, 1),
    ('DEMO-DOG-WET-C', 'Thức ăn ướt cho chó — mẫu minh họa C', 'dog', 'wet', (SELECT id FROM product_categories WHERE slug = 'dog-wet'), 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.', 56000, 10, 1),
    ('DEMO-CAT-DRY-C', 'Thức ăn khô cho mèo — mẫu minh họa C', 'cat', 'dry', (SELECT id FROM product_categories WHERE slug = 'cat-dry'), 'Dữ liệu minh họa, không phải thông tin hàng hóa thật.', 235000, 10, 1);
