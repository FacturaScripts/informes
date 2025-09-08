<?php

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Plugins\Informes\Model\Report;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    use LogErrorsTrait;

    public function testAddFilter(): void
    {
        $report = new Report();
        $this->assertTrue($report->addFilter('table_column', '=', 'value'));
    }

    public function testAddFilterInvalidOperator(): void
    {
        $report = new Report();
        $this->assertFalse($report->addFilter('table_column', 'invalid_operator', 'value'));
    }

    public function testGetChartClassExists(): void
    {
        $report = new Report();
        $this->assertNotEquals('', $report->getChart());
    }

    public function testGetChartClassNotExists(): void
    {
        $report = new Report();
        $report->type = 'nonexistent_type';
        $this->assertEquals('', $report->getChart());
    }

    public function testGetFilters(): void
    {
        $report = new Report();
        $this->assertIsArray($report->getFilters());
    }

    public function testGetSqlFilters(): void
    {
        $report = new Report();
        $this->assertStringStartsWith(' WHERE', $report->getSqlFilters());
    }

    public function testPrimaryDescriptionColumn(): void
    {
        $report = new Report();
        $this->assertEquals('name', $report->primaryDescriptionColumn());
    }

    public function testTableName(): void
    {
        $report = new Report();
        $this->assertEquals('reports', $report->tableName());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
