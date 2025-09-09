<?php

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Plugins\Informes\Model\Report;
use FacturaScripts\Plugins\Informes\Model\ReportBoard;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class ReportBoardTest extends TestCase
{
    use LogErrorsTrait;

    public function testCreateAndDelete(): void
    {
        $reportBoard = new ReportBoard();
        $reportBoard->name = 'report board';

        $this->assertTrue($reportBoard->save());
        $this->assertTrue($reportBoard->exists());
        $this->assertTrue($reportBoard->delete());
    }

    public function testAddLineTrue(): void
    {
        $reportBoard = new ReportBoard();

        $reportBoard->id = 9999;
        $reportBoard->name = 'Test board';
        $this->assertTrue($reportBoard->save());

        $report = new Report();
        $this->assertFalse($report->load('nobalance'));
        $this->assertTrue($reportBoard->addLine($report));

        $this->assertTrue($reportBoard->delete());
    }

    public function testAddLineFalse(): void
    {
        $reportBoard = new ReportBoard();

        $reportBoard->id = 9998;
        $reportBoard->name = 'Test board';
        $this->assertTrue($reportBoard->save());

        $report = new Report();
        
        $report->id = 999999;
        $report->name = 'Test report';
        $report->table = 'test_table';
        $report->type = Report::DEFAULT_TYPE;
        $this->assertTrue($report->save());

        $report2 = new Report();
        $report2->id = 999998;

        $this->assertFalse($reportBoard->addLine($report2));
        
        $this->assertTrue($reportBoard->delete());
        $this->assertTrue($report->delete());
    }

    public function testGetLines(): void
    {
        $reportBoard = new ReportBoard();

        $this->assertIsArray($reportBoard->getLines());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
