<?php

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Plugins\Informes\Model\ReportBoardLine;
use PHPUnit\Framework\TestCase;

final class ReportBoardLineTest extends TestCase
{
    use LogErrorsTrait;

    public function testGetBoard(): void
    {
        $reportBoardLine = new ReportBoardLine();

        $this->assertIsObject($reportBoardLine->getBoard());
    }

    public function testGetReport(): void
    {
        $reportBoardLine = new ReportBoardLine();

        $this->assertIsObject($reportBoardLine->getReport());
    }

    public function testTableName(): void
    {
        $this->assertEquals('reports_boards_lines', ReportBoardLine::tableName());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
