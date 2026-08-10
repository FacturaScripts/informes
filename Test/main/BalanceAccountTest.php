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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Informes\Migration\BalanceAccountRestriction;
use FacturaScripts\Plugins\Informes\Model\BalanceAccount;
use FacturaScripts\Plugins\Informes\Model\BalanceCode;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

/**
 * Description of BalanceAccount
 *
 * @author Carlos García Gómez     <carlos@facturascripts.com>
 * @author Daniel Ferández Giménez <contacto@danielfg.es>
 */
final class BalanceAccountTest extends TestCase
{
    use LogErrorsTrait;

    public static function restrictionProvider(): array
    {
        return [
            // sin restricción: la cuenta entra siempre, con cualquier saldo
            'null-deudor' => [null, 100.0, 0.0, true],
            'null-acreedor' => [null, 0.0, 100.0, true],
            'null-igual' => [null, 100.0, 100.0, true],
            'null-sin-movimientos' => [null, 0.0, 0.0, true],
            'vacia-deudor' => ['', 100.0, 0.0, true],
            'vacia-acreedor' => ['', 0.0, 100.0, true],
            'vacia-igual' => ['', 100.0, 100.0, true],
            'vacia-sin-movimientos' => ['', 0.0, 0.0, true],

            // restricción 'debe': solo cuando el saldo es deudor (debe > haber)
            'debe-deudor' => ['debe', 194.30, 0.0, true],
            'debe-deudor-neto' => ['debe', 300.0, 194.30, true],
            'debe-acreedor' => ['debe', 0.0, 194.30, false],
            'debe-acreedor-neto' => ['debe', 100.0, 300.0, false],
            'debe-igual' => ['debe', 100.0, 100.0, false],
            'debe-sin-movimientos' => ['debe', 0.0, 0.0, false],

            // restricción 'haber': solo cuando el saldo es acreedor (haber > debe)
            'haber-acreedor' => ['haber', 0.0, 194.30, true],
            'haber-acreedor-neto' => ['haber', 100.0, 300.0, true],
            'haber-deudor' => ['haber', 194.30, 0.0, false],
            'haber-deudor-neto' => ['haber', 300.0, 100.0, false],
            'haber-igual' => ['haber', 100.0, 100.0, false],
            'haber-sin-movimientos' => ['haber', 0.0, 0.0, false],

            // un valor no contemplado se comporta como si no hubiera restricción
            'valor-desconocido' => ['otro', 0.0, 100.0, true],
        ];
    }

    public static function setUpBeforeClass(): void
    {
        $balanceCode = new BalanceCode();
        $balanceAccount = new BalanceAccount();
        $database = new DataBase();
        $database->updateSequence($balanceCode::tableName(), $balanceCode->getModelFields());
        $database->updateSequence($balanceAccount::tableName(), $balanceAccount->getModelFields());
    }

    public function testAccountBalanceNoBalance(): void
    {
        // creamos un balance de cuenta sin balance
        $accountBalance = new BalanceAccount();
        $accountBalance->codcuenta = 'TEST';
        $accountBalance->desccuenta = 'TEST DESCRIPTION';

        // no debe guardar
        $this->assertFalse($accountBalance->save(), 'account-balance-can-save-without-balance');
    }

    public function testBalanceAccountRestrictionMigration(): void
    {
        $database = new DataBase();
        $this->assertTrue($database->beginTransaction(), 'cant-begin-transaction');

        try {
            // simulamos una instalación anterior a la migración: sin restricciones
            $sql = 'UPDATE ' . BalanceAccount::tableName() . ' SET restriction = NULL;';
            $this->assertTrue($database->exec($sql), 'cant-clear-restrictions');
            $this->assertSame([], $this->restrictionMap($database), 'restrictions-not-cleared');

            // ejecutamos la migración del plugin
            $migration = new BalanceAccountRestriction();
            $migration->run();

            // las 8 filas de cuentas de doble saldo del balance pymes quedan rellenas
            $this->assertEquals(
                $this->expectedRestrictions(),
                $this->restrictionMap($database),
                'wrong-restrictions-after-migration'
            );

            // y es idempotente: volver a ejecutarla no cambia nada
            $migration->run();
            $this->assertEquals(
                $this->expectedRestrictions(),
                $this->restrictionMap($database),
                'migration-is-not-idempotent'
            );
        } finally {
            // dejamos la base de datos como estaba
            $database->rollback();
        }
    }

    public function testCreate(): void
    {
        // creamos un balance
        $balance = new BalanceCode();
        $balance->codbalance = 'TEST';
        $balance->nature = 'TEST NATURALEZA';
        $balance->subtype = 'test';
        $this->assertTrue($balance->save(), 'balance-cant-save');

        // creamos un balance de cuenta para el balance anterior
        $accountBalance = new BalanceAccount();
        $accountBalance->idbalance = $balance->id;
        $accountBalance->codcuenta = 'TEST';
        $accountBalance->desccuenta = 'TEST DESCRIPTION';
        $this->assertTrue($accountBalance->save(), 'account-balance-cant-save');
        $this->assertNotNull($accountBalance->primaryColumnValue(), 'account-balance-not-stored');
        $this->assertTrue($accountBalance->exists(), 'account-balance-cant-persist');

        // eliminamos
        $this->assertTrue($accountBalance->delete(), 'account-balance-cant-delete');
        $this->assertTrue($balance->delete(), 'balance-cant-delete');
    }

    public function testDeleteCascade(): void
    {
        // creamos un balance
        $balance = new BalanceCode();
        $balance->codbalance = 'TEST';
        $balance->nature = 'TEST';
        $balance->subtype = 'test';
        $this->assertTrue($balance->save(), 'balance-cant-save');

        // creamos un balance de cuenta
        $accountBalance = new BalanceAccount();
        $accountBalance->idbalance = $balance->id;
        $accountBalance->codcuenta = 'TEST';
        $accountBalance->desccuenta = 'TEST DESCRIPTION';
        $this->assertTrue($accountBalance->save(), 'account-balance-cant-save');

        // eliminamos el balance
        $this->assertTrue($balance->delete(), 'balance-cant-delete');

        // comprobamos que no existe el balance de cuenta
        $this->assertFalse($accountBalance->exists(), 'account-balance-exists');
    }

    public function testHtmlOnFields(): void
    {
        // creamos un balance
        $balance = new BalanceCode();
        $balance->codbalance = 'TEST';
        $balance->nature = 'TEST';
        $balance->subtype = 'test';
        $this->assertTrue($balance->save(), 'balance-cant-save');

        // creamos un balance de cuenta con html en los campos
        $accountBalance = new BalanceAccount();
        $accountBalance->idbalance = $balance->id;
        $accountBalance->codcuenta = 'TEST';
        $accountBalance->desccuenta = '<b>Test Html</b>';
        $this->assertTrue($accountBalance->save(), 'account-balance-cant-save');

        // comprobamos que el html ha sido escapado
        $noHtml = Tools::noHtml('<b>Test Html</b>');
        $this->assertEquals($noHtml, $accountBalance->desccuenta, 'account-balance-wrong-html');

        // eliminamos
        $this->assertTrue($accountBalance->delete(), 'account-balance-cant-delete');
        $this->assertTrue($balance->delete(), 'balance-cant-delete');
    }

    /**
     * @dataProvider restrictionProvider
     */
    public function testMatchesRestriction(?string $restriction, float $debe, float $haber, bool $expected): void
    {
        $accountBalance = new BalanceAccount();
        $accountBalance->restriction = $restriction;

        $this->assertSame(
            $expected,
            $accountBalance->matchesRestriction($debe, $haber),
            'wrong-restriction-result'
        );
    }

    public function testRestrictionColumnAndCsvData(): void
    {
        $database = new DataBase();

        // la columna debe existir en la tabla
        $columns = [];
        foreach ($database->getColumns(BalanceAccount::tableName()) as $column) {
            $columns[] = $column['name'];
        }
        $this->assertContains('restriction', $columns, 'restriction-column-not-found');

        // y el csv del plan español debe traer las 8 filas con su restricción
        $this->assertEquals(
            $this->expectedRestrictions(),
            $this->restrictionMap($database),
            'wrong-csv-restrictions'
        );
    }

    public function testSaveAndLoadRestriction(): void
    {
        $balance = new BalanceCode();
        $balance->codbalance = 'TEST';
        $balance->nature = 'A';
        $balance->subtype = 'test';
        $this->assertTrue($balance->save(), 'balance-cant-save');

        $accountBalance = new BalanceAccount();
        $accountBalance->idbalance = $balance->id;
        $accountBalance->codcuenta = 'TEST';
        $accountBalance->desccuenta = 'TEST DESCRIPTION';
        $accountBalance->restriction = BalanceAccount::RESTRICTION_CREDIT;
        $this->assertTrue($accountBalance->save(), 'account-balance-cant-save');

        // recargamos desde la base de datos
        $reloaded = new BalanceAccount();
        $this->assertTrue($reloaded->load($accountBalance->id), 'account-balance-cant-load');
        $this->assertSame('haber', $reloaded->restriction, 'restriction-not-persisted');
        $this->assertFalse($reloaded->matchesRestriction(100.0, 0.0), 'restriction-not-applied');
        $this->assertTrue($reloaded->matchesRestriction(0.0, 100.0), 'restriction-not-applied');

        $this->assertTrue($balance->delete(), 'balance-cant-delete');
    }

    /**
     * Restricciones esperadas para las cuentas de doble saldo del balance pymes,
     * indexadas por 'codbalance|codcuenta'.
     */
    private function expectedRestrictions(): array
    {
        return [
            'A-B-III|5523' => 'debe',
            'A-B-III|5524' => 'debe',
            'A-B-IV|551' => 'debe',
            'A-B-IV|5525' => 'debe',
            'P-C-II-3|551' => 'haber',
            'P-C-II-3|5525' => 'haber',
            'P-C-III|5523' => 'haber',
            'P-C-III|5524' => 'haber',
        ];
    }

    /**
     * Devuelve las restricciones no vacías de las cuentas de doble saldo del
     * balance pymes, indexadas por 'codbalance|codcuenta'.
     */
    private function restrictionMap(DataBase $database): array
    {
        $sql = 'SELECT bc.codbalance AS codbalance, ba.codcuenta AS codcuenta, ba.restriction AS restriction'
            . ' FROM ' . BalanceAccount::tableName() . ' ba'
            . ' LEFT JOIN ' . BalanceCode::tableName() . ' bc ON bc.id = ba.idbalance'
            . " WHERE bc.subtype = 'pymes'"
            . " AND bc.codbalance IN ('A-B-III', 'A-B-IV', 'P-C-II-3', 'P-C-III')"
            . " AND ba.codcuenta IN ('551', '5523', '5524', '5525')"
            . " AND ba.restriction IS NOT NULL AND ba.restriction <> '';";

        $map = [];
        foreach ($database->select($sql) as $row) {
            $map[$row['codbalance'] . '|' . $row['codcuenta']] = $row['restriction'];
        }

        return $map;
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
