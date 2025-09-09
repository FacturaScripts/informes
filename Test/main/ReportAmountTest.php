<?php

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Plugins\Informes\Model\ReportAmount;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class ReportAmountTest extends TestCase
{
    use LogErrorsTrait;

    public function testCreateAndDelete(): void
    {
        $reportAmount = new ReportAmount();
        $reportAmount->name = 'report amount';

        $this->assertTrue($reportAmount->save());
        $this->assertTrue($reportAmount->exists());
        $this->assertTrue($reportAmount->delete());
    }

    public function testPrimaryDescriptionColumn(): void
    {
        $reportAmount = new ReportAmount();
        $this->assertEquals('name', $reportAmount->primaryDescriptionColumn());
    }

    public function testTableName(): void
    {
        $reportAmount = new ReportAmount();
        $this->assertEquals('reports_amounts', $reportAmount->tableName());
    }

    public function testUrl(): void
    {
        $reportAmount = new ReportAmount();
        $this->assertStringContainsString('ListReportAccounting?activetab=', $reportAmount->url());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
