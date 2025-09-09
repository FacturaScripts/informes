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
