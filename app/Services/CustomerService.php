<?php

namespace App\Services;

use App\Models\CustomerModel;
use App\Models\Sales;

class CustomerService
{
    protected CustomerModel $customerModel;
    protected Sales $salesModel;
    protected BranchContextService $branchContext;

    public function __construct()
    {
        $this->customerModel = new CustomerModel();
        $this->salesModel = new Sales();
        $this->branchContext = service('branchContext');
    }

    public function searchCustomers(string $customerName): array
    {
        $builder = $this->customerModel
            ->select('custId, custName, custContact, custEmail, custLocation')
            ->groupStart()
                ->like('custName', $customerName)
                ->orLike('custContact', $customerName)
                ->orLike('custEmail', $customerName)
            ->groupEnd()
            ->orderBy('custName', 'ASC')
            ->limit(20);

        return $this->branchContext->scopeBuilder($builder)->findAll();
    }

    public function getTopCustomersBySales(int $limit = 10): array
    {
        $builder = $this->salesModel
            ->select('
                customers.custId,
                customers.custName,
                customers.custContact,
                COUNT(sales.saleId) AS sale_count,
                SUM(COALESCE(sales.saleQuantity, 0)) AS units_bought,
                SUM(COALESCE(sales.saleQuantity, 0) * COALESCE(sales.salePrice, 0)) AS total_spent
            ')
            ->join('customers', 'customers.custId = sales.custId', 'left')
            ->groupStart()
                ->where('sales.saleStatus <>', 'cancelled')
                ->orWhere('sales.saleStatus IS NULL', null, false)
            ->groupEnd()
            ->groupBy('customers.custId, customers.custName, customers.custContact')
            ->orderBy('total_spent', 'DESC')
            ->limit(max(1, $limit));

        return $this->branchContext->scopeBuilder($builder, 'sales.branchId')->findAll();
    }
}
