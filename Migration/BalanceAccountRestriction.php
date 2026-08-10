<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2017-2026 Carlos García Gómez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Informes\Migration;

use FacturaScripts\Core\Template\MigrationClass;
use FacturaScripts\Dinamic\Model\BalanceAccount;
use FacturaScripts\Dinamic\Model\BalanceCode;

/**
 * Rellena la restricción (solo saldo deudor / solo saldo acreedor) de las cuentas de
 * doble saldo del balance pymes (551, 5523, 5524, 5525) en instalaciones ya existentes,
 * donde el CSV de datos no se reimporta al actualizar el plugin.
 */
final class BalanceAccountRestriction extends MigrationClass
{
    const MIGRATION_NAME = 'balance_account_restriction_4.24';

    public function run(): void
    {
        // instanciamos los modelos para que se cree la columna restriction antes de
        // ejecutar los UPDATE; Init::update() se ejecuta al arranque de la petición,
        // cuando ninguna tabla se ha comprobado todavía
        new BalanceCode();
        new BalanceAccount();

        $db = self::db();
        if (false === $db->tableExists('balance_accounts') || false === $db->tableExists('balance_codes')) {
            return;
        }

        // cuentas de doble saldo del balance pymes: en el código de Activo solo computan
        // con saldo deudor, y en el de Pasivo solo con saldo acreedor
        $restrictions = [
            'A-B-III' => [BalanceAccount::RESTRICTION_DEBIT, ['5523', '5524']],
            'A-B-IV' => [BalanceAccount::RESTRICTION_DEBIT, ['551', '5525']],
            'P-C-II-3' => [BalanceAccount::RESTRICTION_CREDIT, ['551', '5525']],
            'P-C-III' => [BalanceAccount::RESTRICTION_CREDIT, ['5523', '5524']],
        ];

        foreach ($restrictions as $codbalance => [$restriction, $accounts]) {
            $accountsList = implode(',', array_map([$db, 'var2str'], $accounts));
            $sql = 'UPDATE balance_accounts SET restriction = ' . $db->var2str($restriction)
                . " WHERE (restriction IS NULL OR restriction = '')"
                . ' AND codcuenta IN (' . $accountsList . ')'
                . ' AND idbalance IN (SELECT id FROM balance_codes'
                . " WHERE subtype = 'pymes' AND codbalance = " . $db->var2str($codbalance) . ');';
            $db->exec($sql);
        }
    }
}
