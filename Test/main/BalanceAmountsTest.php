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
use FacturaScripts\Dinamic\Model\Cuenta;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Plugins\Informes\Lib\Accounting\BalanceAmounts;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class BalanceAmountsTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    public function testNewBalanceAmounts(): void
    {
        // creamos el asiento
        $asiento = $this->createAsiento('1000000000', '5700000000', 100);

        // obtenemos el balance de sumas y saldos
        $balance = new BalanceAmounts();
        $exercise = $asiento->getExercise();
        $pages = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin);
        $this->assertNotEmpty($pages, 'balance-amounts-empty');

        // eliminamos el asiento
        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
    }

    public function testRowsAndTotals(): void
    {
        $asiento = $this->createAsiento('1000000000', '5700000000', 100);
        $exercise = $asiento->getExercise();

        $balance = new BalanceAmounts();
        $pages = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin, [
            'format' => 'CSV'
        ]);
        $this->assertNotEmpty($pages, 'balance-amounts-empty');
        [$rows, $totals] = $pages;

        // la subcuenta de capital debe aparecer con 100 al debe
        $capital = $this->findRow($rows, '1000000000');
        $this->assertNotNull($capital, 'capital-row-not-found');
        $this->assertEquals(100.0, (float)$capital['debe']);
        $this->assertEquals(0.0, (float)$capital['haber']);
        $this->assertEquals(100.0, (float)$capital['saldo']);

        // la subcuenta de caja debe aparecer con 100 al haber
        $caja = $this->findRow($rows, '5700000000');
        $this->assertNotNull($caja, 'caja-row-not-found');
        $this->assertEquals(0.0, (float)$caja['debe']);
        $this->assertEquals(100.0, (float)$caja['haber']);
        $this->assertEquals(-100.0, (float)$caja['saldo']);

        // deben aparecer las cuentas de la jerarquía con los importes acumulados
        foreach (['1', '10', '100', '5', '57', '570'] as $codcuenta) {
            $accountRow = $this->findRow($rows, $codcuenta);
            $this->assertNotNull($accountRow, 'account-row-not-found-' . $codcuenta);
        }
        $this->assertEquals(100.0, (float)$this->findRow($rows, '100')['debe']);
        $this->assertEquals(100.0, (float)$this->findRow($rows, '570')['haber']);

        // los totales deben cuadrar
        $this->assertEquals((float)$totals[0]['debe'], (float)$totals[0]['haber'], 'totals-not-balanced');

        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
    }

    public function testIgnoreOpening(): void
    {
        // asiento de apertura: caja 500 al debe, capital 500 al haber
        $opening = $this->createAsiento('5700000000', '1000000000', 500, Asiento::OPERATION_OPENING);

        // asiento normal: compras 100 al debe, caja 100 al haber
        $normal = $this->createAsiento('6000000000', '5700000000', 100);

        $exercise = $normal->getExercise();
        $balance = new BalanceAmounts();

        // sin ignorar la apertura: la caja acumula apertura + movimiento
        $pagesAll = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin, [
            'format' => 'CSV'
        ]);
        $this->assertNotEmpty($pagesAll, 'balance-amounts-empty');
        $cajaAll = $this->findRow($pagesAll[0], '5700000000');
        $this->assertEquals(500.0, (float)$cajaAll['debe']);
        $this->assertEquals(100.0, (float)$cajaAll['haber']);

        // ignorando la apertura: solo el movimiento normal
        $pagesIgnored = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin, [
            'format' => 'CSV',
            'ignore_opening' => true
        ]);
        $this->assertNotEmpty($pagesIgnored, 'balance-amounts-empty');
        $cajaIgnored = $this->findRow($pagesIgnored[0], '5700000000');
        $this->assertEquals(0.0, (float)$cajaIgnored['debe']);
        $this->assertEquals(100.0, (float)$cajaIgnored['haber']);

        // el capital solo tiene la partida de apertura: al ignorarla no debe aparecer
        $this->assertNull($this->findRow($pagesIgnored[0], '1000000000'), 'capital-should-not-appear');

        // la diferencia de totales debe ser exactamente el asiento de apertura
        $diff = (float)$pagesAll[1][0]['debe'] - (float)$pagesIgnored[1][0]['debe'];
        $this->assertEquals(500.0, $diff, 'totals-difference-not-opening');

        $this->assertTrue($normal->delete(), 'asiento-cant-delete');
        $this->assertTrue($opening->delete(), 'asiento-apertura-cant-delete');
    }

    public function testShowBalanceOpening(): void
    {
        // asiento de apertura: caja 500 al debe, capital 500 al haber
        $opening = $this->createAsiento('5700000000', '1000000000', 500, Asiento::OPERATION_OPENING);

        // asiento normal: compras 100 al debe, caja 100 al haber
        $normal = $this->createAsiento('6000000000', '5700000000', 100);

        $exercise = $normal->getExercise();
        $balance = new BalanceAmounts();

        // ignoramos la apertura pero mostramos su saldo en la columna de apertura
        $pages = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin, [
            'format' => 'CSV',
            'ignore_opening' => true,
            'show_balance_opening' => true
        ]);
        $this->assertNotEmpty($pages, 'balance-amounts-empty');
        $rows = $pages[0];

        // el capital no tiene movimientos en el periodo, pero debe aparecer con su saldo de apertura
        $capital = $this->findRow($rows, '1000000000');
        $this->assertNotNull($capital, 'capital-row-not-found');
        $this->assertEquals(0.0, (float)$capital['debe']);
        $this->assertEquals(0.0, (float)$capital['haber']);
        $this->assertEquals(-500.0, (float)$capital['opening']);

        // su cuenta también debe aparecer
        $this->assertNotNull($this->findRow($rows, '100'), 'capital-account-row-not-found');

        // la caja muestra el movimiento del periodo y su saldo de apertura
        $caja = $this->findRow($rows, '5700000000');
        $this->assertNotNull($caja, 'caja-row-not-found');
        $this->assertEquals(100.0, (float)$caja['haber']);
        $this->assertEquals(500.0, (float)$caja['opening']);

        // las compras no están en la apertura: su saldo de apertura es 0
        $compras = $this->findRow($rows, '6000000000');
        $this->assertNotNull($compras, 'compras-row-not-found');
        $this->assertEquals(0.0, (float)$compras['opening']);

        $this->assertTrue($normal->delete(), 'asiento-cant-delete');
        $this->assertTrue($opening->delete(), 'asiento-apertura-cant-delete');
    }

    public function testLevelOne(): void
    {
        $asiento = $this->createAsiento('1000000000', '5700000000', 100);
        $exercise = $asiento->getExercise();

        $balance = new BalanceAmounts();
        $pages = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin, [
            'format' => 'CSV',
            'level' => 1
        ]);
        $this->assertNotEmpty($pages, 'balance-amounts-empty');
        $rows = $pages[0];

        // solo deben aparecer cuentas de un dígito, sin subcuentas
        $this->assertNotNull($this->findRow($rows, '1'), 'account-1-not-found');
        $this->assertNotNull($this->findRow($rows, '5'), 'account-5-not-found');
        $this->assertNull($this->findRow($rows, '100'), 'account-100-should-not-appear');
        $this->assertNull($this->findRow($rows, '1000000000'), 'subaccount-should-not-appear');

        // las cuentas de nivel 1 acumulan los importes de toda su jerarquía
        $this->assertEquals(100.0, (float)$this->findRow($rows, '1')['debe']);
        $this->assertEquals(100.0, (float)$this->findRow($rows, '5')['haber']);

        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
    }

    public function testSubaccountFilter(): void
    {
        $asiento = $this->createAsiento('5700000000', '1000000000', 100);
        $exercise = $asiento->getExercise();

        $balance = new BalanceAmounts();
        $pages = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin, [
            'format' => 'CSV',
            'subaccount-from' => '5700000000',
            'subaccount-to' => '5700000000'
        ]);
        $this->assertNotEmpty($pages, 'balance-amounts-empty');
        [$rows, $totals] = $pages;

        // solo debe aparecer la caja, con su cuenta
        $caja = $this->findRow($rows, '5700000000');
        $this->assertNotNull($caja, 'caja-row-not-found');
        $this->assertEquals(100.0, (float)$caja['debe']);
        $this->assertNull($this->findRow($rows, '1000000000'), 'capital-should-not-appear');

        // los totales solo incluyen la subcuenta filtrada
        $this->assertEquals(100.0, (float)$totals[0]['debe']);
        $this->assertEquals(0.0, (float)$totals[0]['haber']);

        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
    }

    public function testAccountWithDirectSubaccountsAndChildAccounts(): void
    {
        // creamos un asiento para disponer del ejercicio
        $asiento = new Asiento();
        $asiento->concepto = 'Test';
        $this->assertTrue($asiento->save(), 'asiento-cant-save');
        $exercise = $asiento->getExercise();

        // subcuenta colgada directamente de la cuenta 11, que también tiene cuentas hijas (112, 113...)
        $sub1 = $this->createSubcuenta('11', '1199999999', $exercise->codejercicio);

        // subcuenta colgada de la cuenta hija 112
        $sub2 = $this->createSubcuenta('112', '1129999999', $exercise->codejercicio);

        // movimientos en ambas
        $firstLine = $asiento->getNewLine();
        $firstLine->codsubcuenta = $sub1->codsubcuenta;
        $firstLine->concepto = 'Test linea 1';
        $firstLine->debe = 200;
        $this->assertTrue($firstLine->save(), 'linea-cant-save-1');

        $secondLine = $asiento->getNewLine();
        $secondLine->codsubcuenta = $sub2->codsubcuenta;
        $secondLine->concepto = 'Test linea 2';
        $secondLine->haber = 200;
        $this->assertTrue($secondLine->save(), 'linea-cant-save-2');

        // generamos con las casillas de ignorar desmarcadas: la antigua comprobación de cuadre
        // comparaba el total jerárquico de la cuenta 11 con sus subcuentas directas y abortaba
        $balance = new BalanceAmounts();
        $pages = $balance->generate($exercise->idempresa, $exercise->fechainicio, $exercise->fechafin, [
            'format' => 'CSV',
            'ignore_opening' => false,
            'ignoreregularization' => false,
            'ignoreclosure' => false
        ]);
        $this->assertNotEmpty($pages, 'balance-amounts-empty-mixed-account');
        $rows = $pages[0];

        // la cuenta 11 acumula la subcuenta directa y la de su cuenta hija
        $row11 = $this->findRow($rows, '11');
        $this->assertNotNull($row11, 'account-11-not-found');
        $this->assertEquals(200.0, (float)$row11['debe']);
        $this->assertEquals(200.0, (float)$row11['haber']);

        // y ambas subcuentas aparecen listadas
        $this->assertNotNull($this->findRow($rows, $sub1->codsubcuenta), 'direct-subaccount-not-found');
        $this->assertNotNull($this->findRow($rows, $sub2->codsubcuenta), 'child-subaccount-not-found');

        $this->assertTrue($asiento->delete(), 'asiento-cant-delete');
        $this->assertTrue($sub1->delete(), 'subcuenta-cant-delete-1');
        $this->assertTrue($sub2->delete(), 'subcuenta-cant-delete-2');
    }

    public function testDatesOutsideExercise(): void
    {
        $balance = new BalanceAmounts();
        $pages = $balance->generate(1, '01-01-1971', '31-12-1971');
        $this->assertEmpty($pages, 'balance-amounts-should-be-empty');
    }

    /**
     * Crea y guarda un asiento con dos partidas: $debeCod al debe y $haberCod al haber.
     */
    private function createAsiento(string $debeCod, string $haberCod, float $amount, ?string $operacion = null): Asiento
    {
        $asiento = new Asiento();
        $asiento->concepto = 'Test';
        $asiento->operacion = $operacion;
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
     * Crea una subcuenta asociada explícitamente a la cuenta indicada.
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
     * Busca una fila del balance por el valor de la columna 'cuenta'.
     */
    private function findRow(array $rows, string $cuenta): ?array
    {
        foreach ($rows as $row) {
            if ($row['cuenta'] === $cuenta) {
                return $row;
            }
        }
        return null;
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
