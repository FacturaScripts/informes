<?php

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Plugins\Informes\Model\ReportLedger;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class ReportLedgerTest extends TestCase
{
    use LogErrorsTrait;

    public function testCreateAndDelete(): void
    {
        $reportLedger = new ReportLedger();
        $reportLedger->name = 'report ledger';

        $this->assertTrue($reportLedger->save());
        $this->assertTrue($reportLedger->exists());
        $this->assertTrue($reportLedger->delete());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
