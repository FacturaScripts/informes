<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Informes;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Informes\Model\BalanceAccount;
use FacturaScripts\Plugins\Informes\Model\BalanceCode;
use ParseCsv\Csv;

class Init extends InitClass
{
    public function init()
    {
        $this->loadExtension(new Extension\Controller\EditCuenta());
    }

    public function update()
    {
        // inicializamos empresa para que aplique los cambios en la tabla
        new Empresa();

        // migramos los datos antiguos
        $this->migrateOldBalances();
        $this->migrateOldReports();
    }

    private function copyBalancePymes(): bool
    {
        // abrimos el csv balance_pymes.csv
        $csv = new Csv();
        $csv->auto(FS_FOLDER . '/Plugins/Informes/Data/Other/balance_pymes.csv');
        if (empty($csv->data)) {
            return false;
        }

        foreach ($csv->data as $row) {
            $balanceCode = new BalanceCode();
            $balanceCode->codbalance = $row['codbalance'];
            $balanceCode->description1 = $row['description1'];
            $balanceCode->description2 = $row['description2'];
            $balanceCode->description3 = $row['description3'];
            $balanceCode->description4 = $row['description4'];
            $balanceCode->level1 = $row['level1'];
            $balanceCode->level2 = $row['level2'];
            $balanceCode->level3 = $row['level3'];
            $balanceCode->level4 = $row['level4'];
            $balanceCode->nature = $row['nature'];
            $balanceCode->subtype = 'pymes';
            if (false === $balanceCode->save()) {
                return false;
            }

            // copiamos las cuentas
            $accounts = explode(',', $row['accounts']);
            foreach ($accounts as $account) {
                $balanceAccount = new BalanceAccount();
                $balanceAccount->idbalance = $balanceCode->id;
                $balanceAccount->codcuenta = trim($account);
                if (false === $balanceAccount->save()) {
                    return false;
                }
            }
        }

        return true;
    }

    private function copyOldBalances(DataBase $db): void
    {
        $balancesCsv = new Csv();
        $balancesCsv->auto(FS_FOLDER . '/Plugins/Informes/Data/Other/balances.csv');

        $balanceCuentasCsv = new Csv();
        $balanceCuentasCsv->auto(FS_FOLDER . '/Plugins/Informes/Data/Other/balancescuentas.csv');

        $balanceCuentasAbCsv = new Csv();
        $balanceCuentasAbCsv->auto(FS_FOLDER . '/Plugins/Informes/Data/Other/balancescuentasabreviadas.csv');
        if (empty($balancesCsv->data) || empty($balanceCuentasCsv->data) || empty($balanceCuentasAbCsv->data)) {
            return;
        }

        // inicializamos los modelos para que se creen las tablas
        new BalanceCode();
        new BalanceAccount();

        $db->beginTransaction();

        // eliminamos datos de las tablas
        $db->exec('DELETE FROM balance_accounts;');
        $db->exec('DELETE FROM balance_codes;');

        // copiamos los datos
        foreach ($balancesCsv->data as $row) {
            // copiamos el balance normal
            $balance = new BalanceCode();
            $balance->codbalance = $row['codbalance'];
            $balance->description1 = $row['descripcion1'];
            $balance->description2 = $row['descripcion2'];
            $balance->description3 = $row['descripcion3'];
            $balance->description4 = $row['descripcion4'];
            $balance->nature = $row['naturaleza'];
            $balance->level1 = $row['nivel1'];
            $balance->level2 = $row['nivel2'];
            $balance->level3 = $row['nivel3'];
            $balance->level4 = $row['nivel4'];
            $balance->subtype = 'normal';
            if (false === $balance->save()) {
                $this->toolBox()->i18nLog()->warning('balance-code-save-error');
                $db->rollback();
                return;
            }

            // copiamos las cuentas
            foreach ($balanceCuentasCsv->data as $row2) {
                if ($row2['codbalance'] === $row['codbalance']) {
                    $balanceAccount = new BalanceAccount();
                    $balanceAccount->idbalance = $balance->id;
                    $balanceAccount->codcuenta = $row2['codcuenta'];
                    if (false === $balanceAccount->save()) {
                        $this->toolBox()->i18nLog()->warning('balance-account-save-error');
                        $db->rollback();
                        return;
                    }
                }
            }

            // copiamos el balance abreviado
            $balanceAbr = clone $balance;
            $balanceAbr->id = null;
            $balanceAbr->subtype = 'abbreviated';
            if (false === $balanceAbr->save()) {
                $this->toolBox()->i18nLog()->warning('balance-code-save-error');
                $db->rollback();
                return;
            }

            // copiamos las cuentas
            foreach ($balanceCuentasAbCsv->data as $row2) {
                if ($row2['codbalance'] === $row['codbalance']) {
                    $balanceAccount = new BalanceAccount();
                    $balanceAccount->idbalance = $balanceAbr->id;
                    $balanceAccount->codcuenta = $row2['codcuenta'];
                    if (false === $balanceAccount->save()) {
                        $this->toolBox()->i18nLog()->warning('balance-account-save-error');
                        $db->rollback();
                        return;
                    }
                }
            }
        }

        if (false === $this->copyBalancePymes()) {
            $db->rollback();
            return;
        }

        $db->commit();
    }

    private function migrateOldBalances(): void
    {
        $db = new DataBase();
        if (false === $db->tableExists('balances')) {
            //$this->copyOldBalances($db);
            return;
        }

        // inicializamos los modelos para que se creen las tablas
        new BalanceCode();
        new BalanceAccount();

        $db->beginTransaction();

        // eliminamos datos de las tablas
        $db->exec('DELETE FROM balance_accounts;');
        $db->exec('DELETE FROM balance_codes;');

        foreach ($db->select('SELECT * FROM balances;') as $row) {
            // copiamos el balance normal
            $balance = new BalanceCode();
            $balance->codbalance = $row['codbalance'];
            $balance->description1 = $row['descripcion1'];
            $balance->description2 = $row['descripcion2'];
            $balance->description3 = $row['descripcion3'];
            $balance->description4 = $row['descripcion4'];
            $balance->nature = $row['naturaleza'];
            $balance->level1 = $row['nivel1'];
            $balance->level2 = $row['nivel2'];
            $balance->level3 = $row['nivel3'];
            $balance->level4 = $row['nivel4'];
            $balance->subtype = 'normal';
            if (false === $balance->save()) {
                $this->toolBox()->i18nLog()->warning('balance-code-save-error');
                $db->rollback();
                return;
            }

            // copiamos las cuentas
            if (false === $this->migrateOldBalanceAccounts($balance, $db, 'balancescuentas')) {
                $db->rollback();
                return;
            }

            // copiamos el balance abreviado
            $balanceAbr = clone $balance;
            $balanceAbr->id = null;
            $balanceAbr->subtype = 'abbreviated';
            if (false === $balanceAbr->save()) {
                $this->toolBox()->i18nLog()->warning('balance-code-save-error');
                $db->rollback();
                return;
            }

            // copiamos las cuentas
            if (false === $this->migrateOldBalanceAccounts($balanceAbr, $db, 'balancescuentasabreviadas')) {
                $db->rollback();
                return;
            }
        }

        if (false === $this->copyBalancePymes()) {
            $db->rollback();
            return;
        }

        $db->commit();

        // eliminamos las tablas balances, balancescuentas y balancescuentasabreviadas
        $db->exec('DROP TABLE balancescuentas;');
        $db->exec('DROP TABLE balancescuentasabreviadas;');
        $db->exec('DROP TABLE balances;');
    }

    private function migrateOldBalanceAccounts(BalanceCode $balanceCode, DataBase $db, string $tableName): bool
    {
        if (false === $db->tableExists($tableName)) {
            return true;
        }

        $sql = 'SELECT * FROM ' . $tableName . ' WHERE codbalance = ' . $db->var2str($balanceCode->codbalance) . ';';
        foreach ($db->select($sql) as $row) {
            $balanceAccount = new BalanceAccount();
            $balanceAccount->idbalance = $balanceCode->id;
            $balanceAccount->codcuenta = $row['codcuenta'];
            if (false === $balanceAccount->save()) {
                return false;
            }
        }

        return true;
    }

    private function migrateOldReports(): void
    {
        $db = new DataBase();
        $tables = [
            'reportsamounts' => 'reports_amounts',
            'reportsbalance' => 'reports_balance',
            'reportsledger' => 'reports_ledger',
        ];
        foreach ($tables as $before => $after) {
            if (false === $db->tableExists($before) || $db->tableExists($after)) {
                continue;
            }

            // renombramos la tabla
            $sql = 'ALTER TABLE ' . $before . ' RENAME TO ' . $after . ';';
            if (false === $db->exec($sql)) {
                $this->toolBox()->i18nLog()->warning('rename-table-error');
                return;
            }
        }
    }
}