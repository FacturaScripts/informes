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
use FacturaScripts\Plugins\Informes\Model\Report;
use FacturaScripts\Plugins\Informes\Model\ReportFilter;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class ReportFilterTest extends TestCase
{
    use LogErrorsTrait;

    public function testCreateAndDelete(): void
    {
        // creamos un reporte
        $report = new Report();
        $report->name = 'Test Report';
        $report->table = 'test_table';
        $report->type = Report::DEFAULT_TYPE;
        $this->assertTrue($report->save());

        // creamos un filtro asociado al reporte
        $reportFilter = new ReportFilter();
        $reportFilter->id_report = $report->id;
        $reportFilter->operator = '=';
        $reportFilter->table_column = 'column';
        $reportFilter->value = 'value';

        // comprobamos que se puede guardar
        $this->assertTrue($reportFilter->save());

        // comprobamos que existe
        $this->assertTrue($reportFilter->exists());

        // lo eliminamos
        $this->assertTrue($reportFilter->delete());
        $this->assertTrue($report->delete());
    }

    public function testGetDynamicValueWithExistingValue(): void
    {
        $reportFilter = new ReportFilter();

        // comprobamos que getDynamicValue() convierte valores dinámicos
        $this->assertEquals(Tools::dateTime(), $reportFilter->getDynamicValue('{now}'));
    }

    public function testGetDynamicValueWithNonExistingValue(): void
    {
        $reportFilter = new ReportFilter();

        // comprobamos que getDynamicValue() retorna el valor original si no es dinámico
        $this->assertEquals('value', $reportFilter->getDynamicValue('value'));
    }

    public function testGetDynamicValues(): void
    {
        $reportFilter = new ReportFilter();

        // definimos los valores dinámicos esperados
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

        // comprobamos que getDynamicValues() retorna todos los valores dinámicos
        $this->assertEquals($expectedValues, $reportFilter->getDynamicValues());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
