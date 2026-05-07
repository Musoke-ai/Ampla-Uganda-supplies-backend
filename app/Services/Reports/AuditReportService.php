<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class AuditReportService
{
    private BaseConnection $db;
    private BranchContextService $branchContext;

    public function __construct()
    {
        $this->db = db_connect();
        $this->branchContext = service('branchContext');
    }

    public function build(array $filters): array
    {
        if (!$this->db->tableExists('audit_logs')) {
            return [
                'summary' => ['auditEvents' => 0],
                'eventsByAction' => [],
                'recentEvents' => [],
                'accuracyNotes' => ['Run the audit_logs migration before audit reports can show data.'],
            ];
        }

        return [
            'summary' => ['auditEvents' => $this->countEvents($filters)],
            'eventsByAction' => $this->eventsByAction($filters),
            'recentEvents' => $this->recentEvents($filters),
            'accuracyNotes' => [
                'Audit reports show actions that have been wired to AuditLogService.',
                'More sensitive controllers should be connected to audit logging as implementation continues.',
            ],
        ];
    }

    private function countEvents(array $filters): int
    {
        $builder = $this->base($filters);

        return $builder->countAllResults();
    }

    private function eventsByAction(array $filters): array
    {
        $builder = $this->base($filters)
            ->select('action AS label, COUNT(*) AS value')
            ->groupBy('action')
            ->orderBy('value', 'DESC');

        return $builder->get()->getResultArray();
    }

    private function recentEvents(array $filters): array
    {
        $builder = $this->base($filters)
            ->select('id, userId, action, entityType, entityId, ipAddress, auditDateCreated')
            ->orderBy('auditDateCreated', 'DESC')
            ->limit(25);

        return $builder->get()->getResultArray();
    }

    private function base(array $filters)
    {
        $builder = $this->db->table('audit_logs')
            ->where('auditDateCreated >=', $filters['from'])
            ->where('auditDateCreated <=', $filters['to']);

        $branchId = $this->branchContext->getEffectiveBranchId();

        if ($branchId !== null) {
            $builder->where('branchId', $branchId);
        }

        return $builder;
    }
}
