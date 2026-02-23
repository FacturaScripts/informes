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
        // creamos un reporte
        $report = new Report();
        $report->name = 'report';
        $report->table = 'test';

        // comprobamos que se puede guardar
        $this->assertTrue($report->save());
        // comprobamos que existe
        $this->assertTrue($report->exists());
        // lo eliminamos
        $this->assertTrue($report->delete());
    }

    public function testAddFilter(): void
    {
        // creamos un reporte
        $report = new Report();
        $report->id = 9999;
        $report->name = 'Test Report';
        $report->table = 'test_table';
        $report->type = Report::DEFAULT_TYPE;
        $this->assertTrue($report->save());

        // comprobamos que se puede añadir un filtro
        $this->assertTrue($report->addFilter('table_column', '=', 'value'));
        // lo eliminamos
        $this->assertTrue($report->delete());
    }

    public function testAddFilterInvalidOperator(): void
    {
        $report = new Report();
        // comprobamos que no se puede añadir un filtro con operador inválido
        $this->assertFalse($report->addFilter('table_column', 'invalid_operator', 'value'));
    }

    public function testGetChartClassExists(): void
    {
        $report = new Report();
        // comprobamos que getChart() retorna un objeto cuando la clase existe
        $this->assertNotEquals('', $report->getChart());
    }

    public function testGetChartClassNotExists(): void
    {
        $report = new Report();
        $report->type = 'nonexistent_type';
        // comprobamos que getChart() retorna vacío cuando la clase no existe
        $this->assertEquals('', $report->getChart());
    }

    public function testGetFilters(): void
    {
        $report = new Report();
        // comprobamos que getFilters() retorna un array
        $this->assertIsArray($report->getFilters());
    }

    public function testGetSqlFilters(): void
    {
        // creamos un reporte
        $report = new Report();
        $report->id = 9998;
        $report->name = 'Test Report';
        $report->table = 'test_table';
        $report->type = Report::DEFAULT_TYPE;
        $this->assertTrue($report->save());

        // añadimos un filtro
        $this->assertTrue($report->addFilter('test_column', '=', 'test_value'));
        // comprobamos que getSqlFilters() genera la cláusula WHERE
        $this->assertStringStartsWith(' WHERE', $report->getSqlFilters());

        // lo eliminamos
        $this->assertTrue($report->delete());
    }

    public function testDeleteReportDeletesFilters(): void
    {
        // creamos un reporte
        $report = new Report();
        $report->id = 9997;
        $report->name = 'Test Report';
        $report->table = 'test_table';
        $report->type = Report::DEFAULT_TYPE;
        $this->assertTrue($report->save());

        // añadimos varios filtros
        $this->assertTrue($report->addFilter('column1', '=', 'value1'));
        $this->assertTrue($report->addFilter('column2', '>', 'value2'));

        // comprobamos que el reporte tiene filtros
        $filters = $report->getFilters();
        $this->assertCount(2, $filters);

        // eliminamos el reporte
        $this->assertTrue($report->delete());

        // comprobamos que los filtros se han eliminado automáticamente
        foreach ($filters as $filter) {
            $this->assertFalse($filter->exists());
        }
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
