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

use FacturaScripts\Core\Tools;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Plugins\Informes\Model\ReportFilter;
use PHPUnit\Framework\TestCase;

final class ReportFilterTest extends TestCase
{
    use LogErrorsTrait;

    public function testCreateAndDelete(): void
    {
        $reportFilter = new ReportFilter();
        $reportFilter->id = 9999;
        $reportFilter->operator = '=';
        $reportFilter->table_column = 'column';
        $reportFilter->value = 'value';

        $this->assertTrue($reportFilter->save());
        $this->assertTrue($reportFilter->exists());
        $this->assertTrue($reportFilter->delete());
    }

    public function testGetDynamicValueWithExistingValue(): void
    {
        $reportFilter = new ReportFilter();

        $this->assertEquals(Tools::dateTime(), $reportFilter->getDynamicValue('{now}'));
    }

    public function testGetDynamicValueWithNonExistingValue(): void
    {
        $reportFilter = new ReportFilter();

        $this->assertEquals('value', $reportFilter->getDynamicValue('value'));
    }

    public function testGetDynamicValues(): void
    {
        $reportFilter = new ReportFilter();

        $expectedValues = [
            '{now}' => Tools::dateTime(),
            '{-1 hour}' => Tools::dateTime('-1 hour'),
            '{-6 hours}' => Tools::dateTime('-6 hours'),
            '{-12 hours}' => Tools::dateTime('-12 hours'),
            '{-24 hours}' => Tools::dateTime('-24 hours'),
            '{today}' => Tools::date(),
            '{-1 day}' => Tools::date('-1 day'),
            '{-7 days}' => Tools::date('-7 days'),
            '{-15 days}' => Tools::date('-15 days'),
            '{-1 month}' => Tools::date('-1 month'),
            '{-3 months}' => Tools::date('-3 months'),
            '{-6 months}' => Tools::date('-6 months'),
            '{-1 year}' => Tools::date('-1 year'),
            '{-2 years}' => Tools::date('-2 years'),
        ];

        $this->assertEquals($expectedValues, $reportFilter->getDynamicValues());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
