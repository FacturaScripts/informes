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
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    use LogErrorsTrait;

    public function testCreateAndDelete(): void
    {
        $report = new Report();
        $report->name = 'report';
        $report->table = 'test';

        $this->assertTrue($report->save());
        $this->assertTrue($report->exists());
        $this->assertTrue($report->delete());
    }

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

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
