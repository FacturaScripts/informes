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

namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Base\ToolBox;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class AccountResultReport extends ResultReport
{
    public static function render(array $formData)
    {
        self::apply($formData);

        $html = '';

        foreach (self::$gastos[self::$year]['cuentas'][self::$parent_codcuenta] as $key => $value) {
            $html .= ''
                . '<tr class="subaccount">'
                . '<td class="title">' . self::$gastos[self::$year]['descripciones'][$key] . '</td>'
                . '<td class="porc text-right align-middle">';

            $percentage = (float)self::$gastos[self::$year]['porc_subcuenta'][self::$parent_codcuenta][$key];
            $html .= $percentage > 0 ? $percentage . ' %' : self::defaultPerc();

            $html .= ''
                . '</td>'
                . '<td class="total text-right align-middle">';

            $money = self::$gastos[self::$year]['total_subcuenta'][self::$parent_codcuenta][$key];
            $html .= $money ? ToolBox::coins()::format($money) : self::defaultMoney();

            $html .= ''
                . '</td>';

            for ($x = 1; $x <= 12; $x++) {
                $title = ToolBox::i18n()->trans(strtolower(date("F", mktime(0, 0, 0, $x, 10))));
                $html .= '<td title="' . $title . '" class="month text-right align-middle">';
                $html .= isset(self::$gastos[self::$year]['cuentas'][self::$parent_codcuenta][$key][$x]['pvptotal']) ?
                    ToolBox::coins()::format(self::$gastos[self::$year]['cuentas'][self::$parent_codcuenta][$key][$x]['pvptotal']) :
                    self::defaultMoney();
                $html .= ''
                    . '</td>';
            }

            $html .= ''
                . '</tr>';
        }

        return $html;
    }
}