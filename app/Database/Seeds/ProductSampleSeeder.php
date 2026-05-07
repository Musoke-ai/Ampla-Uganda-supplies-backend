<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductSampleSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;

        $categoryNames = [
            'Baking Ingredients',
            'Packaging',
            'Bakeware',
            'Decor Supplies',
            'Tools & Utensils',
        ];

        $categoryIds = [];

        foreach ($categoryNames as $categoryName) {
            $existing = $db->table('categories')
                ->where('categoryName', $categoryName)
                ->get()
                ->getRowArray();

            if ($existing) {
                $categoryIds[$categoryName] = (int) $existing['categoryId'];
                continue;
            }

            $db->table('categories')->insert([
                'categoryName' => $categoryName,
            ]);

            $categoryIds[$categoryName] = (int) $db->insertID();
        }

        $products = [
            [
                'itemName' => 'Baking Paper Roll',
                'itemCategoryId' => $categoryIds['Packaging'],
                'itemModel' => 'BPR-30',
                'itemQuality' => 'Premium',
                'itemQuantity' => 35,
                'itemCondition' => 'New',
                'itemSize' => '30cm x 50m',
                'itemStockPrice' => '18000',
                'itemLeastPrice' => 24000,
                'itemNotes' => 'Non-stick parchment roll for cake and pastry lining.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Icing Sugar',
                'itemCategoryId' => $categoryIds['Baking Ingredients'],
                'itemModel' => 'IS-1KG',
                'itemQuality' => 'Fine',
                'itemQuantity' => 48,
                'itemCondition' => 'New',
                'itemSize' => '1kg',
                'itemStockPrice' => '5500',
                'itemLeastPrice' => 7500,
                'itemNotes' => 'Fine powdered sugar for frosting and dusting.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Vanilla Essence',
                'itemCategoryId' => $categoryIds['Baking Ingredients'],
                'itemModel' => 'VE-500',
                'itemQuality' => 'Original',
                'itemQuantity' => 22,
                'itemCondition' => 'New',
                'itemSize' => '500ml',
                'itemStockPrice' => '9000',
                'itemLeastPrice' => 12000,
                'itemNotes' => 'Concentrated vanilla flavor for cakes and desserts.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Baking Powder',
                'itemCategoryId' => $categoryIds['Baking Ingredients'],
                'itemModel' => 'BP-250',
                'itemQuality' => 'Original',
                'itemQuantity' => 40,
                'itemCondition' => 'New',
                'itemSize' => '250g',
                'itemStockPrice' => '3000',
                'itemLeastPrice' => 4500,
                'itemNotes' => 'Double-acting baking powder for sponge and muffin mixes.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Fondant White',
                'itemCategoryId' => $categoryIds['Decor Supplies'],
                'itemModel' => 'FW-1KG',
                'itemQuality' => 'Premium',
                'itemQuantity' => 18,
                'itemCondition' => 'New',
                'itemSize' => '1kg',
                'itemStockPrice' => '14000',
                'itemLeastPrice' => 18500,
                'itemNotes' => 'Smooth white fondant for cake covering and accents.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Fondant Pink',
                'itemCategoryId' => $categoryIds['Decor Supplies'],
                'itemModel' => 'FP-1KG',
                'itemQuality' => 'Premium',
                'itemQuantity' => 14,
                'itemCondition' => 'New',
                'itemSize' => '1kg',
                'itemStockPrice' => '14500',
                'itemLeastPrice' => 19000,
                'itemNotes' => 'Ready-colored fondant for themed cakes and cupcakes.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Cake Board Round',
                'itemCategoryId' => $categoryIds['Packaging'],
                'itemModel' => 'CBR-10',
                'itemQuality' => 'Original',
                'itemQuantity' => 60,
                'itemCondition' => 'New',
                'itemSize' => '10 inch',
                'itemStockPrice' => '1800',
                'itemLeastPrice' => 3000,
                'itemNotes' => 'Silver cake board for standard celebration cakes.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Cake Box Medium',
                'itemCategoryId' => $categoryIds['Packaging'],
                'itemModel' => 'CBM-12',
                'itemQuality' => 'Strong',
                'itemQuantity' => 28,
                'itemCondition' => 'New',
                'itemSize' => '12 x 12',
                'itemStockPrice' => '3500',
                'itemLeastPrice' => 5000,
                'itemNotes' => 'Medium carry box with window top.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Cake Box Large',
                'itemCategoryId' => $categoryIds['Packaging'],
                'itemModel' => 'CBL-14',
                'itemQuality' => 'Strong',
                'itemQuantity' => 24,
                'itemCondition' => 'New',
                'itemSize' => '14 x 14',
                'itemStockPrice' => '4200',
                'itemLeastPrice' => 6000,
                'itemNotes' => 'Large cake box for tiered and custom orders.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Cupcake Liners',
                'itemCategoryId' => $categoryIds['Packaging'],
                'itemModel' => 'CL-100',
                'itemQuality' => 'Premium',
                'itemQuantity' => 70,
                'itemCondition' => 'New',
                'itemSize' => 'Pack of 100',
                'itemStockPrice' => '2500',
                'itemLeastPrice' => 4000,
                'itemNotes' => 'Grease-resistant cupcake liners in assorted patterns.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Piping Bags',
                'itemCategoryId' => $categoryIds['Decor Supplies'],
                'itemModel' => 'PB-50',
                'itemQuality' => 'Premium',
                'itemQuantity' => 45,
                'itemCondition' => 'New',
                'itemSize' => 'Pack of 50',
                'itemStockPrice' => '7000',
                'itemLeastPrice' => 9500,
                'itemNotes' => 'Disposable piping bags for buttercream and ganache.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Nozzle Set',
                'itemCategoryId' => $categoryIds['Decor Supplies'],
                'itemModel' => 'NZ-12',
                'itemQuality' => 'Stainless',
                'itemQuantity' => 16,
                'itemCondition' => 'Excellent',
                'itemSize' => '12 pieces',
                'itemStockPrice' => '11000',
                'itemLeastPrice' => 15000,
                'itemNotes' => 'Mixed piping nozzles for borders, flowers, and lettering.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Measuring Cups Set',
                'itemCategoryId' => $categoryIds['Tools & Utensils'],
                'itemModel' => 'MC-4',
                'itemQuality' => 'Original',
                'itemQuantity' => 20,
                'itemCondition' => 'New',
                'itemSize' => '4 pieces',
                'itemStockPrice' => '8500',
                'itemLeastPrice' => 12000,
                'itemNotes' => 'Nested cup set for dry and liquid ingredients.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Food Coloring Gel Red',
                'itemCategoryId' => $categoryIds['Decor Supplies'],
                'itemModel' => 'FCR-25',
                'itemQuality' => 'Concentrated',
                'itemQuantity' => 12,
                'itemCondition' => 'New',
                'itemSize' => '25g',
                'itemStockPrice' => '4000',
                'itemLeastPrice' => 6500,
                'itemNotes' => 'Bright red gel for frosting, fondant, and batter.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Food Coloring Gel Blue',
                'itemCategoryId' => $categoryIds['Decor Supplies'],
                'itemModel' => 'FCB-25',
                'itemQuality' => 'Concentrated',
                'itemQuantity' => 13,
                'itemCondition' => 'New',
                'itemSize' => '25g',
                'itemStockPrice' => '4000',
                'itemLeastPrice' => 6500,
                'itemNotes' => 'Deep blue gel for themed cakes and dessert decoration.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Whipping Cream',
                'itemCategoryId' => $categoryIds['Baking Ingredients'],
                'itemModel' => 'WC-1L',
                'itemQuality' => 'Dairy',
                'itemQuantity' => 15,
                'itemCondition' => 'Chilled',
                'itemSize' => '1 litre',
                'itemStockPrice' => '15500',
                'itemLeastPrice' => 21000,
                'itemNotes' => 'Liquid whipping cream for toppings and fillings.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Chocolate Compound',
                'itemCategoryId' => $categoryIds['Baking Ingredients'],
                'itemModel' => 'CC-1KG',
                'itemQuality' => 'Premium',
                'itemQuantity' => 19,
                'itemCondition' => 'New',
                'itemSize' => '1kg',
                'itemStockPrice' => '17000',
                'itemLeastPrice' => 23000,
                'itemNotes' => 'Dark coating chocolate for drips, molds, and ganache.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Muffin Tray',
                'itemCategoryId' => $categoryIds['Bakeware'],
                'itemModel' => 'MT-12',
                'itemQuality' => 'Non-stick',
                'itemQuantity' => 10,
                'itemCondition' => 'Excellent',
                'itemSize' => '12 cups',
                'itemStockPrice' => '22000',
                'itemLeastPrice' => 30000,
                'itemNotes' => 'Heavy-duty muffin tray for cupcakes and mini cakes.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Silicone Spatula',
                'itemCategoryId' => $categoryIds['Tools & Utensils'],
                'itemModel' => 'SS-28',
                'itemQuality' => 'Heat Resistant',
                'itemQuantity' => 32,
                'itemCondition' => 'New',
                'itemSize' => '28cm',
                'itemStockPrice' => '6500',
                'itemLeastPrice' => 9000,
                'itemNotes' => 'Flexible spatula for mixing batter and folding cream.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Cake Turntable',
                'itemCategoryId' => $categoryIds['Tools & Utensils'],
                'itemModel' => 'CT-31',
                'itemQuality' => 'Premium',
                'itemQuantity' => 8,
                'itemCondition' => 'Excellent',
                'itemSize' => '31cm',
                'itemStockPrice' => '35000',
                'itemLeastPrice' => 47000,
                'itemNotes' => 'Rotating stand for smooth frosting and cake finishing.',
                'itemOwner' => 1,
            ],
            [
                'itemName' => 'Aluminium Cake Pan',
                'itemCategoryId' => $categoryIds['Bakeware'],
                'itemModel' => 'ACP-08',
                'itemQuality' => 'Original',
                'itemQuantity' => 17,
                'itemCondition' => 'New',
                'itemSize' => '8 inch',
                'itemStockPrice' => '14000',
                'itemLeastPrice' => 19500,
                'itemNotes' => 'Round aluminium pan for sponge and layered cakes.',
                'itemOwner' => 1,
            ],
        ];

        foreach ($products as $product) {
            $existing = $db->table('inventory')
                ->where('itemName', $product['itemName'])
                ->where('itemModel', $product['itemModel'])
                ->get()
                ->getRowArray();

            if ($existing) {
                continue;
            }

            $db->table('inventory')->insert($product);
        }
    }
}
