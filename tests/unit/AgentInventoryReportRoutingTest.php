<?php

use App\Controllers\AgentController;
use App\Services\CopilotContextService;
use App\Services\AgentToolService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AgentInventoryReportRoutingTest extends CIUnitTestCase
{
    /**
     * @dataProvider exportFormatProvider
     */
    public function testInventoryReportRequestsCanAskForExportFiles(string $message, string $format, string $period): void
    {
        $choice = $this->fallbackToolChoice($message);

        $this->assertTrue($choice['allowed']);
        $this->assertSame('get_inventory_report', $choice['tool']);
        $this->assertSame($format, $choice['arguments']['export_format'] ?? null);
        $this->assertSame($period, $choice['arguments']['period'] ?? null);
    }

    public function testInventoryReportToolAdvertisesPdfAndCsvExportFormat(): void
    {
        $tool = (new AgentToolService())->getToolDefinition('get_inventory_report');

        $this->assertIsArray($tool);
        $this->assertArrayHasKey('export_format', $tool['arguments']);
        $this->assertStringContainsString('pdf', strtolower($tool['arguments']['export_format']));
        $this->assertStringContainsString('csv', strtolower($tool['arguments']['export_format']));
    }

    public function testOtherReportToolsAlsoAdvertisePdfAndCsvExportFormat(): void
    {
        $toolService = new AgentToolService();

        foreach (['get_sales_report', 'get_customer_debt_report', 'get_expense_report'] as $toolName) {
            $tool = $toolService->getToolDefinition($toolName);

            $this->assertIsArray($tool, "{$toolName} should be registered.");
            $this->assertArrayHasKey('export_format', $tool['arguments']);
            $this->assertStringContainsString('pdf', strtolower($tool['arguments']['export_format']));
            $this->assertStringContainsString('csv', strtolower($tool['arguments']['export_format']));
        }
    }

    public function testPdfFollowUpAfterSalesSummaryRoutesToExportableSalesReport(): void
    {
        $choice = (new CopilotContextService())->fallbackFollowUpChoice(
            'create a pdf for that summary',
            [
                'last_tool' => 'get_sales_summary',
                'last_period' => 'all',
            ]
        );

        $this->assertIsArray($choice);
        $this->assertTrue($choice['allowed']);
        $this->assertSame('get_sales_report', $choice['tool']);
        $this->assertSame('pdf', $choice['arguments']['export_format'] ?? null);
        $this->assertSame('all', $choice['arguments']['period'] ?? null);
    }

    public function testCsvFollowUpAfterInventoryValueRoutesToExportableInventoryReport(): void
    {
        $choice = (new CopilotContextService())->fallbackFollowUpChoice(
            'export that as csv',
            [
                'last_tool' => 'get_inventory_value',
                'last_period' => 'this_month',
            ]
        );

        $this->assertIsArray($choice);
        $this->assertTrue($choice['allowed']);
        $this->assertSame('get_inventory_report', $choice['tool']);
        $this->assertSame('csv', $choice['arguments']['export_format'] ?? null);
    }

    public static function exportFormatProvider(): array
    {
        return [
            'pdf inventory report' => [
                'create an inventory report for this month as a pdf file',
                'pdf',
                'this_month',
            ],
            'csv inventory report' => [
                'create an inventory report for today as a csv file',
                'csv',
                'today',
            ],
        ];
    }

    private function fallbackToolChoice(string $message): array
    {
        $controller = new AgentController();
        $method = new ReflectionMethod(AgentController::class, 'fallbackToolChoice');
        $method->setAccessible(true);

        return $method->invoke($controller, $message, null, []);
    }
}
