<?php

namespace App\Controllers;

class ProductCatalogController extends BaseController
{
    /**
     * Generate Product Catalog PDF
     */
    public function generateCatalog()
    {
        // Sample products data
        $products = [
            [
                'name' => 'A4 Bond Paper Ream (500 sheets)',
                'category' => 'Stationery',
                'model' => 'Standard White',
                'quantity' => 50,
                'stock_price' => 12500,
                'least_price' => 11500,
                'notes' => 'High-quality office paper, bright white'
            ],
            [
                'name' => 'Office Chair (Executive Mesh)',
                'category' => 'Furniture',
                'model' => 'Ergonomic Pro 3000',
                'quantity' => 12,
                'stock_price' => 350000,
                'least_price' => 320000,
                'notes' => 'Black mesh, adjustable lumbar support'
            ],
            [
                'name' => 'Ballpoint Pen Set (Box of 50)',
                'category' => 'Stationery',
                'model' => 'Blue Classic',
                'quantity' => 120,
                'stock_price' => 8000,
                'least_price' => 7200,
                'notes' => 'Smooth writing, 1.0mm tip'
            ],
            [
                'name' => 'Desktop Computer Monitor (24-inch)',
                'category' => 'Equipment',
                'model' => 'LED Full HD 1080p',
                'quantity' => 8,
                'stock_price' => 450000,
                'least_price' => 420000,
                'notes' => '1920x1080 resolution, HDMI/VGA/DP inputs'
            ],
            [
                'name' => 'File Cabinet (4-Drawer Metal)',
                'category' => 'Furniture',
                'model' => 'Steel Office Storage',
                'quantity' => 15,
                'stock_price' => 180000,
                'least_price' => 165000,
                'notes' => 'Gray finish, locking mechanism, A4 size'
            ],
            [
                'name' => 'Laser Jet Printer (Monochrome)',
                'category' => 'Equipment',
                'model' => 'HP LaserJet Pro M404',
                'quantity' => 5,
                'stock_price' => 2800000,
                'least_price' => 2650000,
                'notes' => '38ppm print speed, network ready'
            ],
            [
                'name' => 'USB Flash Drive (32GB)',
                'category' => 'Equipment',
                'model' => 'Kingston DataTraveler',
                'quantity' => 200,
                'stock_price' => 35000,
                'least_price' => 32000,
                'notes' => 'USB 3.1, up to 110MB/s transfer speed'
            ],
            [
                'name' => 'Envelopes (Box of 500) - White C4',
                'category' => 'Stationery',
                'model' => 'Standard Kraft',
                'quantity' => 80,
                'stock_price' => 22000,
                'least_price' => 20000,
                'notes' => 'Self-seal, 90gsm paper'
            ],
            [
                'name' => 'Office Desk Lamp (LED)',
                'category' => 'Furniture',
                'model' => 'Adjustable Study Lamp',
                'quantity' => 35,
                'stock_price' => 65000,
                'least_price' => 58000,
                'notes' => 'Dimmable, USB charging port, warm/cool light'
            ],
            [
                'name' => 'Mouse Pad (Extended Gaming)',
                'category' => 'Equipment',
                'model' => 'XL Non-slip Base',
                'quantity' => 150,
                'stock_price' => 18000,
                'least_price' => 16000,
                'notes' => 'Black cloth surface, waterproof base'
            ]
        ];

        // Calculate summary
        $totalStockValue = 0;
        foreach ($products as $product) {
            $totalStockValue += $product['stock_price'] * $product['quantity'];
        }

        // Pass data to view
        $data = [
            'products' => $products,
            'totalStockValue' => $totalStockValue,
            'totalProducts' => count($products),
            'generatedDate' => date('F d, Y')
        ];

        return view('product_catalog_view', $data);
    }

    /**
     * Return HTML for printing/PDF
     */
    public function getCatalogHTML()
    {
        $response = $this->generateCatalog();
        header('Content-Type: text/html; charset=utf-8');
        return $response;
    }

    /**
     * Get catalog as JSON (for frontend integration)
     */
    public function getCatalogJSON()
    {
        $products = [
            [
                'name' => 'A4 Bond Paper Ream (500 sheets)',
                'category' => 'Stationery',
                'model' => 'Standard White',
                'quantity' => 50,
                'stock_price' => 12500,
                'least_price' => 11500,
                'notes' => 'High-quality office paper, bright white'
            ],
            [
                'name' => 'Office Chair (Executive Mesh)',
                'category' => 'Furniture',
                'model' => 'Ergonomic Pro 3000',
                'quantity' => 12,
                'stock_price' => 350000,
                'least_price' => 320000,
                'notes' => 'Black mesh, adjustable lumbar support'
            ],
            [
                'name' => 'Ballpoint Pen Set (Box of 50)',
                'category' => 'Stationery',
                'model' => 'Blue Classic',
                'quantity' => 120,
                'stock_price' => 8000,
                'least_price' => 7200,
                'notes' => 'Smooth writing, 1.0mm tip'
            ],
            [
                'name' => 'Desktop Computer Monitor (24-inch)',
                'category' => 'Equipment',
                'model' => 'LED Full HD 1080p',
                'quantity' => 8,
                'stock_price' => 450000,
                'least_price' => 420000,
                'notes' => '1920x1080 resolution, HDMI/VGA/DP inputs'
            ],
            [
                'name' => 'File Cabinet (4-Drawer Metal)',
                'category' => 'Furniture',
                'model' => 'Steel Office Storage',
                'quantity' => 15,
                'stock_price' => 180000,
                'least_price' => 165000,
                'notes' => 'Gray finish, locking mechanism, A4 size'
            ],
            [
                'name' => 'Laser Jet Printer (Monochrome)',
                'category' => 'Equipment',
                'model' => 'HP LaserJet Pro M404',
                'quantity' => 5,
                'stock_price' => 2800000,
                'least_price' => 2650000,
                'notes' => '38ppm print speed, network ready'
            ],
            [
                'name' => 'USB Flash Drive (32GB)',
                'category' => 'Equipment',
                'model' => 'Kingston DataTraveler',
                'quantity' => 200,
                'stock_price' => 35000,
                'least_price' => 32000,
                'notes' => 'USB 3.1, up to 110MB/s transfer speed'
            ],
            [
                'name' => 'Envelopes (Box of 500) - White C4',
                'category' => 'Stationery',
                'model' => 'Standard Kraft',
                'quantity' => 80,
                'stock_price' => 22000,
                'least_price' => 20000,
                'notes' => 'Self-seal, 90gsm paper'
            ],
            [
                'name' => 'Office Desk Lamp (LED)',
                'category' => 'Furniture',
                'model' => 'Adjustable Study Lamp',
                'quantity' => 35,
                'stock_price' => 65000,
                'least_price' => 58000,
                'notes' => 'Dimmable, USB charging port, warm/cool light'
            ],
            [
                'name' => 'Mouse Pad (Extended Gaming)',
                'category' => 'Equipment',
                'model' => 'XL Non-slip Base',
                'quantity' => 150,
                'stock_price' => 18000,
                'least_price' => 16000,
                'notes' => 'Black cloth surface, waterproof base'
            ]
        ];

        return $this->response->setJSON([
            'status' => 'success',
            'data' => $products,
            'count' => count($products)
        ]);
    }
}
