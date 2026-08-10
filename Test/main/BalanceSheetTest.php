<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2026 Carlos García Gómez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\BalanceCode;
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Plugins\Informes\Lib\Accounting\BalanceSheet;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class BalanceSheetTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    /**
     * No regresión: la amortización acumulada (281) no tiene restricción, así que
     * con saldo acreedor debe seguir apareciendo en negativo en el activo (minora
     * el inmovilizado). Un recorte genérico a 0 de los importes negativos del
     * activo rompería esta presentación.
     */
    public function testAccumulatedDepreciationStaysNegative(): void
    {
        // gasto de amortización al debe, amortización acumulada al haber
        $asiento = $this->createAsiento('6810000000', '2811000000', 50.00);
        $codejercicio = $asiento->getExercise()->codejercicio;
        [$assets, $liabilities] = $this->generatePymes($asiento);

        // la 281 minora el activo: debe seguir saliendo negativa
        $this->assertEqualsWithDelta(
            -50.00,
            $this->accountAmount($assets, '281', $codejercicio),
            0.001,
            'account-281-should-be-negative-in-assets'
        );

        // el gasto se refleja en el resultado del ejercicio (cuenta 129) del patrimonio neto
        $this->assertEqualsWithDelta(
            -50.00,
            $this->accountAmount($liabilities, '129', $codejercicio),
            0.001,
            'account-129-wrong-amount'
        );

        // y el balance cuadra: -50 en el activo y -50 en el patrimonio neto
        $this->assertEqualsWithDelta(
            $this->totalAmount($assets, $codejercicio),
            $this->totalAmount($liabilities, $codejercicio),
            0.001,
            'balance-sheet-not-balanced'
        );

        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
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

    /**
     * Regresión: las cuentas de doble saldo (551) están asignadas a la vez a un
     * código de Activo (A-B-IV) y a uno de Pasivo (P-C-II-3) en el subtipo pymes.
     * Con saldo acreedor solo deben aparecer en el Pasivo; si aparecen también en
     * el Activo (en negativo) el balance se descuadra.
     */
    public function testDoubleAccountCreditorOnlyInLiabilities(): void
    {
        // caja al debe, cuenta corriente con socios (551) al haber
        $asiento = $this->createAsiento('5700000000', '5510000000', 194.30);
        $codejercicio = $asiento->getExercise()->codejercicio;
        [$assets, $liabilities] = $this->generatePymes($asiento);

        // la 551 con saldo acreedor no debe aparecer en el activo
        $this->assertNull(
            $this->accountAmount($assets, '551', $codejercicio),
            'account-551-should-not-be-in-assets'
        );

        // pero sí en el pasivo, con signo positivo
        $this->assertEqualsWithDelta(
            194.30,
            $this->accountAmount($liabilities, '551', $codejercicio),
            0.001,
            'account-551-wrong-amount-in-liabilities'
        );

        // la caja (cuenta 57) sí está en el activo con el saldo deudor
        $this->assertEqualsWithDelta(
            194.30,
            $this->accountAmount($assets, '57', $codejercicio),
            0.001,
            'account-57-wrong-amount-in-assets'
        );

        // aserción principal del bug: activo == patrimonio neto + pasivo
        $totalAssets = $this->totalAmount($assets, $codejercicio);
        $totalLiabilities = $this->totalAmount($liabilities, $codejercicio);
        $this->assertNotNull($totalAssets, 'assets-totals-row-not-found');
        $this->assertNotNull($totalLiabilities, 'liabilities-totals-row-not-found');
        $this->assertEqualsWithDelta(194.30, $totalAssets, 0.001, 'wrong-assets-total');
        $this->assertEqualsWithDelta($totalAssets, $totalLiabilities, 0.001, 'balance-sheet-not-balanced');

        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
    }

    /**
     * Caso simétrico: con saldo deudor la 551 solo debe aparecer en el activo.
     */
    public function testDoubleAccountDebitOnlyInAssets(): void
    {
        // cuenta corriente con socios (551) al debe, caja al haber
        $asiento = $this->createAsiento('5510000000', '5700000000', 194.30);
        $codejercicio = $asiento->getExercise()->codejercicio;
        [$assets, $liabilities] = $this->generatePymes($asiento);

        // la 551 con saldo deudor va al activo
        $this->assertEqualsWithDelta(
            194.30,
            $this->accountAmount($assets, '551', $codejercicio),
            0.001,
            'account-551-wrong-amount-in-assets'
        );

        // y no debe aparecer en el pasivo (allí saldría en negativo)
        $this->assertNull(
            $this->accountAmount($liabilities, '551', $codejercicio),
            'account-551-should-not-be-in-liabilities'
        );

        // la caja queda con saldo acreedor: sale negativa en el activo
        $this->assertEqualsWithDelta(
            -194.30,
            $this->accountAmount($assets, '57', $codejercicio),
            0.001,
            'account-57-wrong-amount-in-assets'
        );

        // y el balance sigue cuadrando (aquí ambos totales son cero)
        $this->assertEqualsWithDelta(
            $this->totalAmount($assets, $codejercicio),
            $this->totalAmount($liabilities, $codejercicio),
            0.001,
            'balance-sheet-not-balanced'
        );

        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
    }

    /**
     * El otro par de cuentas de doble saldo: 5523 y 5524 (A-B-III en el activo,
     * P-C-III en el pasivo).
     */
    public function testGroupCurrentAccountRestriction(): void
    {
        // cuenta corriente con empresas del grupo (5523) al haber
        $asiento = $this->createAsiento('5700000000', '5523000000', 500.00);
        $codejercicio = $asiento->getExercise()->codejercicio;
        [$assets, $liabilities] = $this->generatePymes($asiento);

        $this->assertNull(
            $this->accountAmount($assets, '5523', $codejercicio),
            'account-5523-should-not-be-in-assets'
        );
        $this->assertEqualsWithDelta(
            500.00,
            $this->accountAmount($liabilities, '5523', $codejercicio),
            0.001,
            'account-5523-wrong-amount-in-liabilities'
        );
        $this->assertEqualsWithDelta(
            $this->totalAmount($assets, $codejercicio),
            $this->totalAmount($liabilities, $codejercicio),
            0.001,
            'balance-sheet-not-balanced'
        );

        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
    }

    /**
     * La restricción se evalúa sobre el saldo neto de la cuenta, sumando todas sus
     * subcuentas: una subcuenta deudora y otra acreedora de la 551 deben netearse
     * y decidir en qué página aparece la cuenta.
     */
    public function testRestrictionUsesNetAccountBalance(): void
    {
        $asiento = new Asiento();
        $asiento->concepto = 'Test';
        $this->assertTrue($asiento->save(), 'asiento-cant-save');
        $exercise = $asiento->getExercise();

        // segunda subcuenta de la 551
        $subcuenta = $this->createSubcuenta('551', '5510000002', $exercise->codejercicio);

        // 5510000000 deudora por 300 y 5510000002 acreedora por 194,30 => neto deudor 105,70
        $line1 = $asiento->getNewLine();
        $line1->codsubcuenta = '5510000000';
        $line1->concepto = 'Test linea 1';
        $line1->debe = 300.00;
        $this->assertTrue($line1->save(), 'linea-cant-save-1');

        $line2 = $asiento->getNewLine();
        $line2->codsubcuenta = $subcuenta->codsubcuenta;
        $line2->concepto = 'Test linea 2';
        $line2->haber = 194.30;
        $this->assertTrue($line2->save(), 'linea-cant-save-2');

        $line3 = $asiento->getNewLine();
        $line3->codsubcuenta = '5700000000';
        $line3->concepto = 'Test linea 3';
        $line3->haber = 105.70;
        $this->assertTrue($line3->save(), 'linea-cant-save-3');

        $codejercicio = $exercise->codejercicio;
        [$assets, $liabilities] = $this->generatePymes($asiento);

        // saldo neto deudor: la cuenta va al activo por la diferencia
        $this->assertEqualsWithDelta(
            105.70,
            $this->accountAmount($assets, '551', $codejercicio),
            0.001,
            'account-551-wrong-net-amount-in-assets'
        );
        $this->assertNull(
            $this->accountAmount($liabilities, '551', $codejercicio),
            'account-551-should-not-be-in-liabilities'
        );
        $this->assertEqualsWithDelta(
            $this->totalAmount($assets, $codejercicio),
            $this->totalAmount($liabilities, $codejercicio),
            0.001,
            'balance-sheet-not-balanced'
        );

        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
        $this->assertTrue($subcuenta->delete(), 'subcuenta-cant-delete');
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
     * Devuelve el importe de la fila de una cuenta del balance. Las filas de cuenta
     * se escriben como '      <codcuenta>. <descripcion>' (seis espacios de sangría).
     * Devuelve null si la cuenta no aparece en la página (porque su saldo es cero).
     */
    private function accountAmount(array $page, string $codcuenta, string $codejercicio): ?float
    {
        $prefix = '      ' . $codcuenta . '. ';
        foreach ($page as $row) {
            $description = $this->cleanValue($row['descripcion'] ?? '');
            if (str_starts_with($description, $prefix)) {
                return (float)$this->cleanValue($row[$codejercicio] ?? '0');
            }
        }

        return null;
    }

    /**
     * Quita la negrita que se añade cuando el formato de salida es PDF.
     */
    private function cleanValue(string $value): string
    {
        return str_replace(['<b>', '</b>'], '', $value);
    }

    /**
     * Crea y guarda un asiento con dos partidas: $debeCod al debe y $haberCod al haber.
     */
    private function createAsiento(string $debeCod, string $haberCod, float $amount): Asiento
    {
        $asiento = new Asiento();
        $asiento->concepto = 'Test';
        $this->assertTrue($asiento->save(), 'asiento-cant-save');
        $this->assertTrue($asiento->exists(), 'asiento-cant-persist');

        $firstLine = $asiento->getNewLine();
        $firstLine->codsubcuenta = $debeCod;
        $firstLine->concepto = 'Test linea 1';
        $firstLine->debe = $amount;
        $this->assertTrue($firstLine->save(), 'linea-cant-save-1');

        $secondLine = $asiento->getNewLine();
        $secondLine->codsubcuenta = $haberCod;
        $secondLine->concepto = 'Test linea 2';
        $secondLine->haber = $amount;
        $this->assertTrue($secondLine->save(), 'linea-cant-save-2');

        return $asiento;
    }

    /**
     * Crea una subcuenta colgada de la cuenta indicada.
     */
    private function createSubcuenta(string $codcuenta, string $codsubcuenta, string $codejercicio): Subcuenta
    {
        $cuenta = new Cuenta();
        $where = [Where::eq('codejercicio', $codejercicio), Where::eq('codcuenta', $codcuenta)];
        $this->assertTrue($cuenta->loadWhere($where), 'cuenta-not-found-' . $codcuenta);

        $subcuenta = new Subcuenta();
        $subcuenta->codcuenta = $cuenta->codcuenta;
        $subcuenta->idcuenta = $cuenta->idcuenta;
        $subcuenta->codejercicio = $codejercicio;
        $subcuenta->codsubcuenta = $codsubcuenta;
        $subcuenta->descripcion = 'Test ' . $codsubcuenta;
        $this->assertTrue($subcuenta->save(), 'subcuenta-cant-save-' . $codsubcuenta);
        return $subcuenta;
    }

    /**
     * Genera el balance de situación con el subtipo pymes y formato no PDF, para
     * que los importes salgan como números planos y sin negrita.
     */
    private function generatePymes(Asiento $asiento): array
    {
        $exercise = $asiento->getExercise();
        $balance = new BalanceSheet();
        $pages = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin, [
            'subtype' => 'pymes',
            'format' => 'CSV'
        ]);

        $this->assertNotEmpty($pages, 'balance-cant-generate-pymes');
        $this->assertCount(2, $pages, 'balance-sheet-must-have-two-pages');
        return $pages;
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

    protected function tearDown(): void
    {
        $this->logErrors();
    }

    /**
     * Devuelve el importe de la fila de totales de una página (la que empieza por 'Total (').
     * Devuelve null si no la encuentra.
     */
    private function totalAmount(array $page, string $codejercicio): ?float
    {
        foreach ($page as $row) {
            $description = $this->cleanValue($row['descripcion'] ?? '');
            if (str_starts_with($description, 'Total (')) {
                return (float)$this->cleanValue($row[$codejercicio] ?? '0');
            }
        }

        return null;
    }
}
