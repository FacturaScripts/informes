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
use FacturaScripts\Plugins\Informes\Model\ReportBoardLine;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class ReportBoardLineTest extends TestCase
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

        // creamos un tablero de reportes
        $reportBoard = new ReportBoard();
        $reportBoard->name = 'Test Board';
        $this->assertTrue($reportBoard->save());

        // creamos una línea de tablero asociada al reporte y tablero
        $reportBoardLine = new ReportBoardLine();
        $reportBoardLine->idreport = $report->id;
        $reportBoardLine->idreportboard = $reportBoard->id;

        // comprobamos que se puede guardar
        $this->assertTrue($reportBoardLine->save());

        // comprobamos que existe
        $this->assertTrue($reportBoardLine->exists());

        // lo eliminamos
        $this->assertTrue($reportBoardLine->delete());
        $this->assertTrue($reportBoard->delete());
        $this->assertTrue($report->delete());
    }

    public function testGetBoard(): void
    {
        // creamos un tablero de reportes
        $reportBoard = new ReportBoard();
        $reportBoard->name = 'Test Board';
        $this->assertTrue($reportBoard->save());

        // creamos una línea asociada al tablero
        $reportBoardLine = new ReportBoardLine();
        $reportBoardLine->idreportboard = $reportBoard->id;

        // comprobamos que getBoard() retorna el tablero correcto
        $this->assertIsObject($reportBoardLine->getBoard());
        $this->assertEquals($reportBoard->id, $reportBoardLine->getBoard()->id);

        // lo eliminamos
        $this->assertTrue($reportBoard->delete());
    }

    public function testGetReport(): void
    {
        // creamos un reporte
        $report = new Report();
        $report->name = 'Test Report';
        $report->table = 'test_table';
        $report->type = Report::DEFAULT_TYPE;
        $this->assertTrue($report->save());

        // creamos una línea asociada al reporte
        $reportBoardLine = new ReportBoardLine();
        $reportBoardLine->idreport = $report->id;

        // comprobamos que getReport() retorna el reporte correcto
        $this->assertIsObject($reportBoardLine->getReport());
        $this->assertEquals($report->id, $reportBoardLine->getReport()->id);

        // lo eliminamos
        $this->assertTrue($report->delete());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
