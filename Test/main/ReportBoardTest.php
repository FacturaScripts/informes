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
        // creamos un tablero de reportes
        $reportBoard = new ReportBoard();
        $reportBoard->name = 'report board';
        $this->assertTrue($reportBoard->save());

        // comprobamos que existe
        $this->assertTrue($reportBoard->exists());

        // lo eliminamos
        $this->assertTrue($reportBoard->delete());
    }

    public function testAddLineTrue(): void
    {
        // creamos un tablero de reportes
        $reportBoard = new ReportBoard();
        $reportBoard->name = 'Test board';
        $this->assertTrue($reportBoard->save());

        // creamos un reporte
        $report = new Report();
        $report->name = 'Test Report';
        $report->table = 'test_table';
        $report->type = Report::DEFAULT_TYPE;
        $this->assertTrue($report->save());

        // comprobamos que se puede añadir una línea al tablero
        $this->assertTrue($reportBoard->addLine($report));

        // lo eliminamos
        $this->assertTrue($reportBoard->delete());
        $this->assertTrue($report->delete());
    }

    public function testAddLineFalse(): void
    {
        // creamos un tablero de reportes
        $reportBoard = new ReportBoard();
        $reportBoard->name = 'Test board';
        $this->assertTrue($reportBoard->save());

        // creamos un reporte válido
        $report = new Report();
        $report->name = 'Test report';
        $report->table = 'test_table';
        $report->type = Report::DEFAULT_TYPE;
        $this->assertTrue($report->save());

        // creamos un reporte inválido (sin guardar)
        $report2 = new Report();

        // comprobamos que no se puede añadir un reporte inválido
        $this->assertFalse($reportBoard->addLine($report2));

        // lo eliminamos
        $this->assertTrue($reportBoard->delete());
        $this->assertTrue($report->delete());
    }

    public function testGetLines(): void
    {
        $reportBoard = new ReportBoard();

        // comprobamos que getLines() retorna un array
        $this->assertIsArray($reportBoard->getLines());
    }

    public function testDeleteBoardDeletesLines(): void
    {
        // creamos el primer reporte
        $report1 = new Report();
        $report1->name = 'Test Report 1';
        $report1->table = 'test_table';
        $report1->type = Report::DEFAULT_TYPE;
        $this->assertTrue($report1->save());

        // creamos el segundo reporte
        $report2 = new Report();
        $report2->name = 'Test Report 2';
        $report2->table = 'test_table';
        $report2->type = Report::DEFAULT_TYPE;
        $this->assertTrue($report2->save());

        // creamos un tablero de reportes
        $reportBoard = new ReportBoard();
        $reportBoard->name = 'Test Board';
        $this->assertTrue($reportBoard->save());

        // añadimos varias líneas al tablero
        $this->assertTrue($reportBoard->addLine($report1, 1));
        $this->assertTrue($reportBoard->addLine($report2, 2));
        
        // comprobamos que el tablero tiene líneas
        $lines = $reportBoard->getLines();
        $this->assertCount(2, $lines);
        
        // eliminamos el tablero
        $this->assertTrue($reportBoard->delete());
        
        // comprobamos que las líneas se han eliminado automáticamente
        foreach ($lines as $line) {
            $this->assertFalse($line->exists());
        }
        
        // lo eliminamos
        $this->assertTrue($report1->delete());
        $this->assertTrue($report2->delete());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
