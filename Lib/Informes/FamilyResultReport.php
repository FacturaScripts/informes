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

use FacturaScripts\Core\Tools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class FamilyResultReport extends ResultReport
{
    public static function render(array $formData): string
    {
        self::apply($formData);
        $varName = ($formData['action'] == "load-sales" or $formData['action'] == "load-family-sales") ? "ventas" : "compras";
        
        $html = '';

        asort(self::${$varName}[self::$year]['descripciones']);
        foreach (self::${$varName}[self::$year]['descripciones'] as $key => $value) {
            if (!isset(self::${$varName}[self::$year]['familias'][self::$parent_codfamilia][$key])) {
                continue;
            }

            $html .= ''
                . '<tr class="subfamily">'
                . '<td class="title">' . self::${$varName}[self::$year]['descripciones'][$key] . '</td>'
                . '<td class="porc text-right align-middle">';

            $percentage = (float)self::${$varName}[self::$year]['porc_ref'][self::$parent_codfamilia][$key];
            $html .= $percentage > 0 ? $percentage . ' %' : self::defaultPerc();

            $html .= ''
                . '</td>'
                . '<td class="total text-right align-middle">';

            $money = self::${$varName}[self::$year]['total_ref'][self::$parent_codfamilia][$key];
            $html .= $money ? Tools::money($money) : self::defaultMoney();

            $html .= ''
                . '</td>';

            for ($x = 1; $x <= 12; $x++) {
                $title = Tools::lang()->trans(strtolower(date("F", mktime(0, 0, 0, $x, 10))));
                $html .= '<td title="' . $title . '" class="month text-right align-middle">';
                $html .= isset(self::${$varName}[self::$year]['familias'][self::$parent_codfamilia][$key][$x]['pvptotal']) ?
                    Tools::money(self::${$varName}[self::$year]['familias'][self::$parent_codfamilia][$key][$x]['pvptotal']) :
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
