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
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PurchasesResultReport extends ResultReport
{
    public static function render(array $formData): string
    {
        self::apply($formData);

        $html = '<div class="table-responsive">'
            . '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th class="title">' . ToolBox::i18n()->trans('account') . '</th>'
            . '<th class="porc">%</th>'
            . '<th class="total">' . ToolBox::i18n()->trans('total') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('january') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('february') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('march') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('april') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('may') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('june') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('july') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('august') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('september') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('october') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('november') . '</th>'
            . '<th class="month">' . ToolBox::i18n()->trans('december') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>';

        // fila de totales
        if (self::$gastos[self::$year]) {
            $html .= '<tr>'
                . '<td class="title align-middle">' . ToolBox::i18n()->trans('all') . '</td>'
                . '<td class="porc align-middle">100.0 %</td>';

            for ($x = 0; $x <= 12; $x++) {
                $css = $x == 0 ? 'total' : 'month';
                $money = self::$gastos[self::$year]['total_mes'][$x];
                $lastmoney = self::$gastos[self::$lastyear]['total_mes'][$x];
                $html .= '<td class="' . $css . '">';
                $html .= $money ? ToolBox::coins()::format($money) : self::defaultMoney();
                $html .= '<div class="small">';
                $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : self::defaultMoney();
                $html .= '</div>'
                    . '</td>';
            }

            $html .= '</tr>';
        }

        $cont = 1;
        foreach (self::$gastos[self::$year]['cuentas'] as $key => $value) {
            $html .= '<tr codcuenta="' . $key . '" data-target="#gastos-' . $cont . '" class="gastos pointer">'
                . '<td class="title align-middle">' . self::$gastos[self::$year]['descripciones'][$key] . '</td>'
                . '<td class="porc align-middle">';

            $percentage = (float)self::$gastos[self::$year]['porc_cuenta'][$key];
            $html .= $percentage > 0 ? ToolBox::numbers()::format($percentage, 1) . ' %' : self::defaultPerc();

            $html .= '</td>'
                . '<td class="total align-middle">';

            $money = self::$gastos[self::$year]['total_cuenta'][$key];
            $html .= $money ? ToolBox::coins()::format($money) : self::defaultMoney();

            $html .= '</td>';

            for ($x = 1; $x <= 12; $x++) {
                $title = ToolBox::i18n()->trans(strtolower(date("F", mktime(0, 0, 0, $x, 10))));
                $html .= '<td title="' . $title . '" class="month align-middle">';
                $html .= isset(self::$gastos[self::$year]['total_cuenta_mes'][$key][$x]) ?
                    ToolBox::coins()::format(self::$gastos[self::$year]['total_cuenta_mes'][$key][$x]) :
                    self::defaultMoney();
                $html .= ''
                    . '</td>';
            }

            $html .= ''
                . '</tr>';

            $cont++;
        }

        $html .= ''
            . '</tbody>'
            . '</table>'
            . '</div>';

        return $html;
    }
}