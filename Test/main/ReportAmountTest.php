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

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
