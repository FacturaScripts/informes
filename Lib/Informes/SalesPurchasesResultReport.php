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
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SalesPurchasesResultReport extends ResultReport
{
    public static function render(array $formData): string
    {
        self::apply($formData);
        $varName = ($formData['action'] == "load-sales" or $formData['action'] == "load-family-sales") ? "ventas" : "compras";

        // Definir los meses en un arreglo
        $meses = [
            'january', 'february', 'march', 'april', 'may', 'june',
            'july', 'august', 'september', 'october', 'november', 'december'
        ];

        // Comenzar a construir el HTML
        $html = '<div class="table-responsive">'
            . '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th class="title">' . Tools::lang()->trans('family') . '</th>'
            . '<th class="porc">%</th>'
            . '<th class="total">' . Tools::lang()->trans('total') . '</th>';

        // Añadir los meses usando un bucle
        foreach ($meses as $mes) {
            $html .= '<th class="month">' . Tools::lang()->trans($mes) . '</th>';
        }

        // Finalizar la etiqueta de cabecera y la de cuerpo de la tabla
        $html .= '</tr>'
            . '</thead>'
            . '<tbody>';

        if (self::${$varName}[self::$year]) {
            $html .= '<tr>'
                . '<td class="title align-middle">' . Tools::lang()->trans('all') . '</td>'
                . '<td class="porc align-middle">100.0 %</td>';

            for ($x = 0; $x <= 12; $x++) {
                $css = $x == 0 ? 'total' : 'month';
                $money = self::${$varName}[self::$year]['total_mes'][$x];
                $lastmoney = self::${$varName}[self::$lastyear]['total_mes'][$x];
                $html .= '<td class="' . $css . '">';
                $html .= $money ? Tools::money($money) : self::defaultMoney();
                $html .= '<div class="small">';
                $html .= $lastmoney ? Tools::money($lastmoney) : self::defaultMoney();
                $html .= '</div>'
                    . '</td>';
            }

            $html .= '</tr>';
        }

        if (isset(self::${$varName}[self::$year]['descripciones'])) {
            asort(self::${$varName}[self::$year]['descripciones']);
            $cont = 1;
            foreach (self::${$varName}[self::$year]['descripciones'] as $key => $value) {
                if (!isset(self::${$varName}[self::$year]['familias'][$key])) {
                    continue;
                }

                $html .= ''
                    . '<tr codfamilia="' . $key . '" data-target="#ventas-' . $cont . '" class="ventas pointer">'
                    . '<td class="title">' . self::${$varName}[self::$year]['descripciones'][$key] . '</td>'
                    . '<td class="porc align-middle">';

                $percentage = (float)self::${$varName}[self::$year]['porc_fam'][$key];
                $html .= $percentage > 0 ? $percentage . ' %' : self::defaultPerc();

                $html .= '</td>'
                    . '<td class="total align-middle">';

                $money = self::${$varName}[self::$year]['total_fam'][$key];
                $html .= $money ? Tools::money($money) : self::defaultMoney();

                $html .= '</td>';

                for ($x = 1; $x <= 12; $x++) {
                    $title = Tools::lang()->trans(strtolower(date("F", mktime(0, 0, 0, $x, 10))));
                    $html .= '<td title="' . $title . '" class="month align-middle">';
                    $html .= isset(self::${$varName}[self::$year]['total_fam_mes'][$key][$x]) ?
                        Tools::money(self::${$varName}[self::$year]['total_fam_mes'][$key][$x]) :
                        self::defaultMoney();
                    $html .= '</td>';
                }

                $html .= '</tr>';

                $cont++;
            }
        }

        $html .= '</tbody>'
            . '</table>'
            . '</div>';

        return $html;
    }
}
