<?php

namespace App\Services\Reports;

use DateTimeImmutable;
use InvalidArgumentException;

class ReportFilterService
{
    public function resolve(array $params): array
    {
        $period = (string) ($params['period'] ?? 'this_month');
        $today = new DateTimeImmutable('today');
        $allowedPeriods = [
            'today',
            'yesterday',
            'this_week',
            'last_week',
            'this_month',
            'last_month',
            'this_quarter',
            'this_year',
            'custom',
        ];

        if (!in_array($period, $allowedPeriods, true)) {
            throw new InvalidArgumentException('Invalid report period.');
        }

        [$from, $to] = match ($period) {
            'today' => [$today, $today],
            'yesterday' => [$today->modify('-1 day'), $today->modify('-1 day')],
            'this_week' => [$today->modify('monday this week'), $today],
            'last_week' => [$today->modify('monday last week'), $today->modify('sunday last week')],
            'last_month' => [$today->modify('first day of last month'), $today->modify('last day of last month')],
            'this_quarter' => [$this->quarterStart($today), $today],
            'this_year' => [$today->modify('first day of january this year'), $today],
            'custom' => [
                $this->dateOrDefault($params['date_from'] ?? ($params['from'] ?? null), $today->modify('first day of this month'), 'date_from'),
                $this->dateOrDefault($params['date_to'] ?? ($params['to'] ?? null), $today, 'date_to'),
            ],
            default => [$today->modify('first day of this month'), $today],
        };

        if ($from > $to) {
            throw new InvalidArgumentException('date_from cannot be after date_to.');
        }

        $perPage = (int) ($params['per_page'] ?? ($params['perPage'] ?? 25));
        $perPage = max(1, min($perPage, 100));
        $sortDir = strtolower((string) ($params['sort_dir'] ?? ($params['sortDir'] ?? 'asc')));

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Invalid sort direction.');
        }

        $sortBy = $this->safeText($params['sort_by'] ?? ($params['sortBy'] ?? null), 80);
        if ($sortBy !== null && !preg_match('/^[A-Za-z0-9_.]+$/', $sortBy)) {
            throw new InvalidArgumentException('Invalid sort field.');
        }

        return [
            'period' => $period,
            'fromDate' => $from->format('Y-m-d'),
            'toDate' => $to->format('Y-m-d'),
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'from' => $from->format('Y-m-d 00:00:00'),
            'to' => $to->format('Y-m-d 23:59:59'),
            'page' => max(1, (int) ($params['page'] ?? 1)),
            'per_page' => $perPage,
            'perPage' => $perPage,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'branch_id' => $this->optionalPositiveInt($params['branch_id'] ?? ($params['branchId'] ?? null), 'branch_id'),
            'warehouse_id' => $this->optionalPositiveInt($params['warehouse_id'] ?? ($params['warehouseId'] ?? null), 'warehouse_id'),
            'product_id' => $this->optionalPositiveInt($params['product_id'] ?? ($params['productId'] ?? null), 'product_id'),
            'category_id' => $this->optionalPositiveInt($params['category_id'] ?? ($params['categoryId'] ?? null), 'category_id'),
            'customer_id' => $this->optionalPositiveInt($params['customer_id'] ?? ($params['customerId'] ?? null), 'customer_id'),
            'supplier_id' => $this->optionalPositiveInt($params['supplier_id'] ?? ($params['supplierId'] ?? null), 'supplier_id'),
            'user_id' => $this->optionalPositiveInt($params['user_id'] ?? ($params['userId'] ?? null), 'user_id'),
            'payment_method' => $this->safeText($params['payment_method'] ?? ($params['paymentMethod'] ?? null), 50),
            'status' => $this->safeText($params['status'] ?? null, 50),
            'search' => $this->safeText($params['search'] ?? '', 100) ?? '',
            'lowStockThreshold' => max(0, (int) ($params['lowStockThreshold'] ?? 5)),
        ];
    }

    private function dateOrDefault($value, DateTimeImmutable $default, string $field): DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', substr($value, 0, 10));

        if (!$date || $date->format('Y-m-d') !== substr($value, 0, 10)) {
            throw new InvalidArgumentException("Invalid {$field}. Use YYYY-MM-DD.");
        }

        return $date;
    }

    private function optionalPositiveInt($value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value) || (int) $value <= 0) {
            throw new InvalidArgumentException("Invalid {$field}.");
        }

        return (int) $value;
    }

    private function safeText($value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (strlen($text) > $maxLength) {
            throw new InvalidArgumentException('One or more filters are too long.');
        }

        return $text;
    }

    private function quarterStart(DateTimeImmutable $date): DateTimeImmutable
    {
        $month = (int) $date->format('n');
        $quarterStartMonth = (((int) floor(($month - 1) / 3)) * 3) + 1;

        return $date->setDate((int) $date->format('Y'), $quarterStartMonth, 1);
    }
}
