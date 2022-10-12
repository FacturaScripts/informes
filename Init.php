<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

class Init extends InitClass
{
    public function init()
    {
        // nada
    }

    public function update()
    {
        // inicializamos empresa para que aplique los cambios en la tabla
        new Empresa();

        // migramos los balances antiguos
        $this->migrateOldBalances();
    }

    private function migrateOldBalances(): void
    {
        $db = new DataBase();
        if (false === $db->tableExists('balances')) {
            return;
        }

        $db->beginTransaction();

        $sql = 'SELECT * FROM balances;';
        foreach ($db->select($sql) as $row) {
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

            if (false === $this->migrateOldBalanceAccounts($balanceAbr, $db, 'balancescuentasabreviadas')) {
                $db->rollback();
                return;
            }
        }

        $db->commit();
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
}