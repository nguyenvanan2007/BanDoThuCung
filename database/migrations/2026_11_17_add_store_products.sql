UPDATE products
SET price_vnd = 329000
WHERE sku = 'DKLFGKW3323' AND is_demo = 0;

UPDATE products
SET price_vnd = 189000
WHERE sku = 'JIODJGIOD32432' AND is_demo = 0;

INSERT INTO products (
    sku,
    name,
    species,
    food_type,
    description,
    image_path,
    price_vnd,
    stock_quantity,
    is_active,
    is_demo
)
VALUES
    (
        'TPET-DOG-DRY-500G-001',
        'Pedigree Adult vị bò và rau củ 500g',
        'dog',
        'dry',
        'Thức ăn khô Pedigree cho chó trưởng thành, vị bò và rau củ, gói 500g.',
        '/assets/images/products/pedigree-dry-dog.jpg',
        55000,
        20,
        1,
        0
    ),
    (
        'TPET-DOG-WET-100G-001',
        'Pedigree thức ăn ướt vị gà sốt gravy 100g',
        'dog',
        'wet',
        'Thức ăn ướt Pedigree vị gà sốt gravy cho chó, gói 100g.',
        '/assets/images/products/pedigree-wet-dog.jpg',
        18000,
        20,
        1,
        0
    ),
    (
        'TPET-CAT-DRY-500G-001',
        'Thức ăn hạt khô cho mèo 500g',
        'cat',
        'dry',
        'Thức ăn hạt khô cho mèo, gói 500g.',
        '/assets/images/products/cat-food-bowl.jpg',
        69000,
        20,
        1,
        0
    ),
    (
        'TPET-CAT-WET-85G-001',
        'Thức ăn ướt cho mèo 85g',
        'cat',
        'wet',
        'Thức ăn ướt cho mèo, khẩu phần gói 85g.',
        '/assets/images/products/cat-wet-food.jpg',
        22000,
        20,
        1,
        0
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    species = VALUES(species),
    food_type = VALUES(food_type),
    description = VALUES(description),
    image_path = VALUES(image_path),
    price_vnd = VALUES(price_vnd),
    is_active = 1,
    is_demo = 0;
