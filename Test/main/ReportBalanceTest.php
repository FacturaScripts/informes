<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Plugins\Informes\Model\ReportBalance;
use FacturaScripts\Test\Traits\LogErrorsTrait;
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
