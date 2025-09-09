<?php

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Plugins\Informes\Model\ReportBoardLine;
use PHPUnit\Framework\TestCase;

final class ReportBoardLineTest extends TestCase
{
    use LogErrorsTrait;


    public function testCreateAndDelete(): void
    {
        $reportBoardLine = new ReportBoardLine();
        $reportBoardLine->name = 'report board line';

        $this->assertTrue($reportBoardLine->save());
        $this->assertTrue($reportBoardLine->exists());
        $this->assertTrue($reportBoardLine->delete());
    }

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

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
