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
