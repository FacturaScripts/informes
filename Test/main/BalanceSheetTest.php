<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2025 Carlos García Gómez <carlos@facturascripts.com>
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

use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\BalanceCode;
use FacturaScripts\Plugins\Informes\Lib\Accounting\BalanceSheet;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use PHPUnit\Framework\TestCase;

final class BalanceSheetTest extends TestCase
{
    use DefaultSettingsTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    public function testCreateBalanceSheet(): void
    {
        // creamos el asiento
        $asiento = new Asiento();
        $asiento->concepto = 'Test';
        $this->assertTrue($asiento->save(), 'asiento-cant-save-1');
        $this->assertNotNull($asiento->id(), 'asiento-not-stored');
        $this->assertTrue($asiento->exists(), 'asiento-cant-persist');

        // añadimos una línea (caja, activo)
        $firstLine = $asiento->getNewLine();
        $firstLine->codsubcuenta = '5700000000';
        $firstLine->concepto = 'Test linea 1';
        $firstLine->debe = 100;
        $this->assertTrue($firstLine->save(), 'linea-cant-save-1');

        // añadimos otra línea (capital, patrimonio neto)
        $secondLine = $asiento->getNewLine();
        $secondLine->codsubcuenta = '1000000000';
        $secondLine->concepto = 'Test linea 2';
        $secondLine->haber = 100;
        $this->assertTrue($secondLine->save(), 'linea-cant-save-2');

        // obtenemos el balance de situación
        $balance = new BalanceSheet();
        $exercise = $asiento->getExercise();
        $pages = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin);
        $this->assertNotEmpty($pages, 'balance-cant-generate');

        // el balance de situación tiene dos naturalezas (activo y pasivo)
        $this->assertCount(2, $pages, 'balance-sheet-must-have-two-pages');

        // el importe del asiento (100) debe aparecer en la columna del ejercicio
        $this->assertTrue(
            $this->reportHasAmount($pages, $exercise->codejercicio, 100.0),
            'expected-amount-not-in-report'
        );

        // eliminamos el asiento
        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
    }

    public function testSortBalancesNaturalOrder(): void
    {
        $sheet = new class extends BalanceSheet {
            public function sortForTest(array $balances): array
            {
                return $this->sortBalances($balances);
            }
        };

        // niveles de un solo caracter y multi-dígito, desordenados
        $balances = [];
        foreach (['10', '2', 'A', '1'] as $level1) {
            $balance = new BalanceCode();
            $balance->level1 = $level1;
            $balances[] = $balance;
        }

        $sorted = array_map(function (BalanceCode $b) {
            return $b->level1;
        }, $sheet->sortForTest($balances));

        // orden natural: '1' < '2' < '10' < 'A' (los números antes que las letras)
        $this->assertSame(['1', '2', '10', 'A'], $sorted, 'balances-not-natural-sorted');
    }

    /**
     * Busca en las páginas del informe un importe (en valor absoluto) en la columna del ejercicio.
     */
    private function reportHasAmount(array $pages, string $codejercicio, float $amount): bool
    {
        foreach ($pages as $page) {
            foreach ($page as $row) {
                if (!isset($row[$codejercicio]) || $row[$codejercicio] === '') {
                    continue;
                }

                $value = (float)str_replace(['<b>', '</b>'], '', $row[$codejercicio]);
                if (abs(abs($value) - $amount) < 0.001) {
                    return true;
                }
            }
        }

        return false;
    }
}
