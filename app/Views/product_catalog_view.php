<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ampla Uganda Supplies - Product Catalog</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #1f3a93;
            padding-bottom: 20px;
        }

        .header h1 {
            color: #1f3a93;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header h2 {
            color: #666;
            font-size: 18px;
            font-weight: normal;
            margin-bottom: 5px;
        }

        .header p {
            color: #999;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th {
            background-color: #1f3a93;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f0f0f0;
        }

        .number-col {
            width: 30px;
            text-align: center;
            font-weight: bold;
        }

        .name-col {
            width: 20%;
            font-weight: 500;
        }

        .category-col {
            width: 12%;
        }

        .price-col {
            text-align: right;
            font-weight: 500;
        }

        .qty-col {
            text-align: center;
        }

        .summary {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .summary h3 {
            color: #1f3a93;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-weight: 600;
            color: #333;
        }

        .summary-value {
            color: #1f3a93;
            font-weight: 500;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            color: #666;
            font-size: 12px;
        }

        .footer i {
            font-style: italic;
        }

        @media print {
            body {
                background-color: white;
            }

            .container {
                max-width: 100%;
                box-shadow: none;
            }

            .print-btn {
                display: none;
            }
        }

        .print-btn {
            background-color: #1f3a93;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .print-btn:hover {
            background-color: #152d6a;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>

        <div class="header">
            <h1>AMPLA UGANDA SUPPLIES</h1>
            <h2>Product Catalog</h2>
            <p>Generated: <?= $generatedDate ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="number-col">#</th>
                    <th class="name-col">Product Name</th>
                    <th class="category-col">Category</th>
                    <th>Model</th>
                    <th class="qty-col">Qty</th>
                    <th class="price-col">Stock Price (UGX)</th>
                    <th class="price-col">Least Price (UGX)</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $index => $product): ?>
                <tr>
                    <td class="number-col"><?= $index + 1 ?></td>
                    <td class="name-col"><strong><?= $product['name'] ?></strong></td>
                    <td class="category-col"><?= $product['category'] ?></td>
                    <td><?= $product['model'] ?></td>
                    <td class="qty-col"><?= $product['quantity'] ?></td>
                    <td class="price-col"><?= number_format($product['stock_price']) ?></td>
                    <td class="price-col"><?= number_format($product['least_price']) ?></td>
                    <td><?= strlen($product['notes']) > 40 ? substr($product['notes'], 0, 40) . '...' : $product['notes'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary">
            <h3>Catalog Summary</h3>
            <div class="summary-row">
                <span class="summary-label">Total Products:</span>
                <span class="summary-value"><?= $totalProducts ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Total Stock Value:</span>
                <span class="summary-value">UGX <?= number_format($totalStockValue) ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Average Product Value:</span>
                <span class="summary-value">UGX <?= number_format($totalStockValue / $totalProducts) ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Currency:</span>
                <span class="summary-value">Ugandan Shilling (UGX)</span>
            </div>
        </div>

        <div class="footer">
            <p><strong>Ampla Uganda Supplies</strong> - Office Solutions & Equipment</p>
            <p><i>This is an official product catalog. For more information, please contact us.</i></p>
        </div>
    </div>
</body>
</html>
