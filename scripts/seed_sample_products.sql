START TRANSACTION;

INSERT INTO categories (categoryName)
SELECT 'Baking Ingredients'
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE categoryName = 'Baking Ingredients'
);

INSERT INTO categories (categoryName)
SELECT 'Packaging'
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE categoryName = 'Packaging'
);

INSERT INTO categories (categoryName)
SELECT 'Bakeware'
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE categoryName = 'Bakeware'
);

INSERT INTO categories (categoryName)
SELECT 'Decor Supplies'
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE categoryName = 'Decor Supplies'
);

INSERT INTO categories (categoryName)
SELECT 'Tools & Utensils'
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE categoryName = 'Tools & Utensils'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Baking Paper Roll',
    (SELECT categoryId FROM categories WHERE categoryName = 'Packaging' LIMIT 1),
    'BPR-30',
    'Premium',
    35,
    'New',
    '30cm x 50m',
    '18000',
    24000,
    'Non-stick parchment roll for cake and pastry lining.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Baking Paper Roll' AND itemModel = 'BPR-30'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Icing Sugar',
    (SELECT categoryId FROM categories WHERE categoryName = 'Baking Ingredients' LIMIT 1),
    'IS-1KG',
    'Fine',
    48,
    'New',
    '1kg',
    '5500',
    7500,
    'Fine powdered sugar for frosting and dusting.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Icing Sugar' AND itemModel = 'IS-1KG'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Vanilla Essence',
    (SELECT categoryId FROM categories WHERE categoryName = 'Baking Ingredients' LIMIT 1),
    'VE-500',
    'Original',
    22,
    'New',
    '500ml',
    '9000',
    12000,
    'Concentrated vanilla flavor for cakes and desserts.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Vanilla Essence' AND itemModel = 'VE-500'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Baking Powder',
    (SELECT categoryId FROM categories WHERE categoryName = 'Baking Ingredients' LIMIT 1),
    'BP-250',
    'Original',
    40,
    'New',
    '250g',
    '3000',
    4500,
    'Double-acting baking powder for sponge and muffin mixes.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Baking Powder' AND itemModel = 'BP-250'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Fondant White',
    (SELECT categoryId FROM categories WHERE categoryName = 'Decor Supplies' LIMIT 1),
    'FW-1KG',
    'Premium',
    18,
    'New',
    '1kg',
    '14000',
    18500,
    'Smooth white fondant for cake covering and accents.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Fondant White' AND itemModel = 'FW-1KG'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Fondant Pink',
    (SELECT categoryId FROM categories WHERE categoryName = 'Decor Supplies' LIMIT 1),
    'FP-1KG',
    'Premium',
    14,
    'New',
    '1kg',
    '14500',
    19000,
    'Ready-colored fondant for themed cakes and cupcakes.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Fondant Pink' AND itemModel = 'FP-1KG'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Cake Board Round',
    (SELECT categoryId FROM categories WHERE categoryName = 'Packaging' LIMIT 1),
    'CBR-10',
    'Original',
    60,
    'New',
    '10 inch',
    '1800',
    3000,
    'Silver cake board for standard celebration cakes.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Cake Board Round' AND itemModel = 'CBR-10'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Cake Box Medium',
    (SELECT categoryId FROM categories WHERE categoryName = 'Packaging' LIMIT 1),
    'CBM-12',
    'Strong',
    28,
    'New',
    '12 x 12',
    '3500',
    5000,
    'Medium carry box with window top.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Cake Box Medium' AND itemModel = 'CBM-12'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Cake Box Large',
    (SELECT categoryId FROM categories WHERE categoryName = 'Packaging' LIMIT 1),
    'CBL-14',
    'Strong',
    24,
    'New',
    '14 x 14',
    '4200',
    6000,
    'Large cake box for tiered and custom orders.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Cake Box Large' AND itemModel = 'CBL-14'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Cupcake Liners',
    (SELECT categoryId FROM categories WHERE categoryName = 'Packaging' LIMIT 1),
    'CL-100',
    'Premium',
    70,
    'New',
    'Pack of 100',
    '2500',
    4000,
    'Grease-resistant cupcake liners in assorted patterns.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Cupcake Liners' AND itemModel = 'CL-100'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Piping Bags',
    (SELECT categoryId FROM categories WHERE categoryName = 'Decor Supplies' LIMIT 1),
    'PB-50',
    'Premium',
    45,
    'New',
    'Pack of 50',
    '7000',
    9500,
    'Disposable piping bags for buttercream and ganache.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Piping Bags' AND itemModel = 'PB-50'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Nozzle Set',
    (SELECT categoryId FROM categories WHERE categoryName = 'Decor Supplies' LIMIT 1),
    'NZ-12',
    'Stainless',
    16,
    'Excellent',
    '12 pieces',
    '11000',
    15000,
    'Mixed piping nozzles for borders, flowers, and lettering.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Nozzle Set' AND itemModel = 'NZ-12'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Measuring Cups Set',
    (SELECT categoryId FROM categories WHERE categoryName = 'Tools & Utensils' LIMIT 1),
    'MC-4',
    'Original',
    20,
    'New',
    '4 pieces',
    '8500',
    12000,
    'Nested cup set for dry and liquid ingredients.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Measuring Cups Set' AND itemModel = 'MC-4'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Food Coloring Gel Red',
    (SELECT categoryId FROM categories WHERE categoryName = 'Decor Supplies' LIMIT 1),
    'FCR-25',
    'Concentrated',
    12,
    'New',
    '25g',
    '4000',
    6500,
    'Bright red gel for frosting, fondant, and batter.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Food Coloring Gel Red' AND itemModel = 'FCR-25'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Food Coloring Gel Blue',
    (SELECT categoryId FROM categories WHERE categoryName = 'Decor Supplies' LIMIT 1),
    'FCB-25',
    'Concentrated',
    13,
    'New',
    '25g',
    '4000',
    6500,
    'Deep blue gel for themed cakes and dessert decoration.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Food Coloring Gel Blue' AND itemModel = 'FCB-25'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Whipping Cream',
    (SELECT categoryId FROM categories WHERE categoryName = 'Baking Ingredients' LIMIT 1),
    'WC-1L',
    'Dairy',
    15,
    'Chilled',
    '1 litre',
    '15500',
    21000,
    'Liquid whipping cream for toppings and fillings.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Whipping Cream' AND itemModel = 'WC-1L'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Chocolate Compound',
    (SELECT categoryId FROM categories WHERE categoryName = 'Baking Ingredients' LIMIT 1),
    'CC-1KG',
    'Premium',
    19,
    'New',
    '1kg',
    '17000',
    23000,
    'Dark coating chocolate for drips, molds, and ganache.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Chocolate Compound' AND itemModel = 'CC-1KG'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Muffin Tray',
    (SELECT categoryId FROM categories WHERE categoryName = 'Bakeware' LIMIT 1),
    'MT-12',
    'Non-stick',
    10,
    'Excellent',
    '12 cups',
    '22000',
    30000,
    'Heavy-duty muffin tray for cupcakes and mini cakes.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Muffin Tray' AND itemModel = 'MT-12'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Silicone Spatula',
    (SELECT categoryId FROM categories WHERE categoryName = 'Tools & Utensils' LIMIT 1),
    'SS-28',
    'Heat Resistant',
    32,
    'New',
    '28cm',
    '6500',
    9000,
    'Flexible spatula for mixing batter and folding cream.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Silicone Spatula' AND itemModel = 'SS-28'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Cake Turntable',
    (SELECT categoryId FROM categories WHERE categoryName = 'Tools & Utensils' LIMIT 1),
    'CT-31',
    'Premium',
    8,
    'Excellent',
    '31cm',
    '35000',
    47000,
    'Rotating stand for smooth frosting and cake finishing.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Cake Turntable' AND itemModel = 'CT-31'
);

INSERT INTO inventory
    (itemName, itemCategoryId, itemModel, itemQuality, itemQuantity, itemCondition, itemSize, itemStockPrice, itemLeastPrice, itemNotes, itemOwner)
SELECT
    'Aluminium Cake Pan',
    (SELECT categoryId FROM categories WHERE categoryName = 'Bakeware' LIMIT 1),
    'ACP-08',
    'Original',
    17,
    'New',
    '8 inch',
    '14000',
    19500,
    'Round aluminium pan for sponge and layered cakes.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM inventory WHERE itemName = 'Aluminium Cake Pan' AND itemModel = 'ACP-08'
);

COMMIT;
