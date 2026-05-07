<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    private AuditLog $auditLogModel;

    public function __construct()
    {
        $this->auditLogModel = new AuditLog();
    }

    public function record(
        string $action,
        string $entityType,
        $entityId,
        ?array $beforeData,
        ?array $afterData,
        ?int $userId = null,
        ?int $branchId = null,
        array $metadata = []
    ): bool {
        $request = service('request');

        return (bool) $this->auditLogModel->insert([
            'branchId' => $branchId,
            'userId' => $userId ?? (auth()->id() ? (int) auth()->id() : null),
            'action' => $action,
            'entityType' => $entityType,
            'entityId' => $entityId === null ? null : (string) $entityId,
            'beforeData' => $beforeData === null ? null : json_encode($beforeData),
            'afterData' => $afterData === null ? null : json_encode($afterData),
            'metadata' => empty($metadata) ? null : json_encode($metadata),
            'ipAddress' => method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null,
            'userAgent' => method_exists($request, 'getUserAgent') ? (string) $request->getUserAgent() : null,
        ]);
    }
}
