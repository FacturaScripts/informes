<?php

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Plugins\Informes\Model\ReportBalance;
use PHPUnit\Framework\TestCase;

final class ReportBalanceTest extends TestCase
{
    use LogErrorsTrait;

    public function testCreateAndDelete(): void
    {
        $reportBalance = new ReportBalance();
        $reportBalance->name = 'report balance';

        $this->assertTrue($reportBalance->save());
        $this->assertTrue($reportBalance->exists());
        $this->assertTrue($reportBalance->delete());
    }

    public function testTypeList(): void
    {

        $reportBalance = new ReportBalance();

        $this->assertIsArray($reportBalance->typeList());
        $this->assertCount(3, $reportBalance->typeList());

    }

    public function testSubtypeList(): void
    {
    
        $reportBalance = new ReportBalance();
    
        $this->assertIsArray($reportBalance->subtypeList());
        $this->assertCount(2, $reportBalance->subtypeList());
    
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
