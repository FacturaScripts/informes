<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2025 Carlos García Gómez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Html;
use FacturaScripts\Core\Tools;
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

    public function testCanShowBalance(): void
    {
        $report = new Report();
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'total';

        // con sumas o recuentos agrupados por periodos consecutivos se muestra el saldo
        foreach (['', 'SUM', 'COUNT', 'COUNT_DISTINCT'] as $yOperation) {
            foreach (['DAY', 'WEEK', 'MONTHS', 'YEAR', 'UNIXTIME_MONTH'] as $xOperation) {
                $report->xoperation = $xOperation;
                $report->yoperation = $yOperation;
                $this->assertTrue($report->canShowBalance(), $xOperation . ' / ' . $yOperation);
            }
        }

        // con medias, máximos o mínimos no se muestra el saldo
        $report->xoperation = 'MONTHS';
        foreach (['AVERAGE', 'MAXIMUM', 'MINIMUM'] as $yOperation) {
            $report->yoperation = $yOperation;
            $this->assertFalse($report->canShowBalance(), $yOperation);
        }

        // con agrupaciones cíclicas, por categorías o sin agrupar no se muestra el saldo
        $report->yoperation = 'SUM';
        foreach (['', 'HOUR', 'MONTH', 'HAS_A_VALUE', 'DOES_NOT_HAVE_A_VALUE'] as $xOperation) {
            $report->xoperation = $xOperation;
            $this->assertFalse($report->canShowBalance(), $xOperation);
        }
    }

    public function testColumnLabels(): void
    {
        $report = new Report();

        // sin columnas se usan las etiquetas genéricas
        $this->assertEquals(Tools::trans('x-column'), $report->getXColumnLabel());
        $this->assertEquals(Tools::trans('count'), $report->getYColumnLabel());

        // se traduce el nombre de la columna sin el prefijo de la tabla
        $report->xcolumn = 'facturascli.fecha';
        $fecha = Tools::trans('fecha') === 'fecha' ? 'Fecha' : Tools::trans('fecha');
        $this->assertEquals($fecha, $report->getXColumnLabel());

        // la columna Y incluye la operación
        $report->ycolumn = 'total';
        $total = Tools::trans('total') === 'total' ? 'Total' : Tools::trans('total');
        $this->assertEquals($total . ' (' . mb_strtolower(Tools::trans('sum')) . ')', $report->getYColumnLabel());

        $report->yoperation = 'AVERAGE';
        $this->assertEquals($total . ' (' . mb_strtolower(Tools::trans('average')) . ')', $report->getYColumnLabel());

        // las expresiones SQL se muestran tal cual
        $report->xcolumn = 'COALESCE(codcliente, codproveedor)';
        $this->assertEquals('COALESCE(codcliente, codproveedor)', $report->getXColumnLabel());
    }

    public function testGetYDecimals(): void
    {
        $report = new Report();

        // los recuentos no tienen decimales
        $this->assertSame(0, $report->getYDecimals());

        $report->ycolumn = 'total';
        $report->yoperation = 'COUNT';
        $this->assertSame(0, $report->getYDecimals());

        // el resto de operaciones usa los decimales predeterminados
        $report->yoperation = 'SUM';
        $this->assertNull($report->getYDecimals());
    }

    public function testGetComparedReport(): void
    {
        // sin informe comparado devuelve null
        $report = new Report();
        $report->name = 'Test Report';
        $report->table = 'test_table';
        $this->assertNull($report->getComparedReport());

        // creamos el informe comparado
        $compared = new Report();
        $compared->name = 'Test Compared Report';
        $compared->table = 'test_table';
        $this->assertTrue($compared->save());

        // comprobamos que se carga el informe comparado
        $report->compared = $compared->id;
        $comparedReport = $report->getComparedReport();
        $this->assertNotNull($comparedReport);
        $this->assertEquals($compared->id, $comparedReport->id);

        // si no existe el informe comparado devuelve null
        $this->assertTrue($compared->delete());
        $this->assertNull($report->getComparedReport());
    }

    public function testRenderChartTables(): void
    {
        // creamos un informe y otro comparado que cuentan los informes por mes
        $compared = new Report();
        $compared->name = 'Test Chart Compared';
        $compared->table = 'reports';
        $compared->type = Report::DEFAULT_TYPE;
        $compared->xcolumn = 'creationdate';
        $compared->xoperation = 'MONTHS';
        $compared->ycolumn = 'id';
        $compared->yoperation = 'COUNT';
        $this->assertTrue($compared->save());

        $report = new Report();
        $report->name = 'Test Chart Main';
        $report->table = 'reports';
        $report->type = Report::DEFAULT_TYPE;
        $report->xcolumn = 'creationdate';
        $report->xoperation = 'MONTHS';
        $report->ycolumn = 'id';
        $report->yoperation = 'COUNT';
        $report->compared = $compared->id;
        $this->assertTrue($report->save());

        // las tres tablas (informe, comparado y diferencia) muestran el saldo
        $balanceHeader = '<th class="text-end">' . Tools::trans('balance') . '</th>';
        $yHeader = '<th class="text-end">' . htmlspecialchars($report->getYColumnLabel()) . '</th>';
        $html = Html::render('Master/htmlChart.html.twig', ['fsc' => ['views' => [['model' => $report]]]]);
        $this->assertEquals(3, substr_count($html, $balanceHeader));
        $this->assertEquals(3, substr_count($html, $yHeader));

        // si el comparado usa otra operación, su tabla y la diferencia no muestran el saldo
        $compared->yoperation = 'AVERAGE';
        $this->assertTrue($compared->save());
        $html = Html::render('Master/htmlChart.html.twig', ['fsc' => ['views' => [['model' => $report]]]]);
        $this->assertEquals(1, substr_count($html, $balanceHeader));
        $this->assertEquals(1, substr_count($html, $yHeader));
        $this->assertStringContainsString('<th class="text-end">' . Tools::trans('y-column') . '</th>', $html);

        // eliminamos los informes
        $this->assertTrue($report->delete());
        $this->assertTrue($compared->delete());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
