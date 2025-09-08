<?php

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Plugins\Informes\Model\ReportLedger;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class ReportLedgerTest extends TestCase
{
    use LogErrorsTrait;

    public function testPrimaryDescriptionColumn(): void
    {
        $reportLedger = new ReportLedger();
        $this->assertEquals('name', $reportLedger->primaryDescriptionColumn());
    }

    public function testTableName(): void
    {
        $reportLedger = new ReportLedger();
        $this->assertEquals('reports_ledger', $reportLedger->tableName());
    }

    public function testUrl(): void
    {
        $reportLedger = new ReportLedger();
        $this->assertStringContainsString('ListReportAccounting?activetab=', $reportLedger->url());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
