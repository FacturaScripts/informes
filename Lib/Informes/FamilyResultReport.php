<?php
/**
 * Copyright (C) 2019-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Base\ToolBox;

/**
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class FamilyResultReport extends ResultReport
{
    public static function render(array $formData)
    {
        self::apply($formData);

        $html = ''
            . '<table class="table table-hover mb-0">'
            . '<tbody>';

        foreach (self::$ventas[self::$year]['familias'][self::$parent_codfamilia] as $key => $value) {
            $html .= ''
                . '<tr>'
                . '<td class="title">' . self::$ventas[self::$year]['descripciones'][$key] . '</td>'
                . '<td class="porc text-right align-middle">';

            $percentage = (float) self::$ventas[self::$year]['porc_ref'][self::$parent_codfamilia][$key];
            $html .= $percentage > 0 ? $percentage . ' %' : self::defaultPerc();

            $html .= ''
                . '</td>'
                . '<td class="total text-right align-middle">';

            $money = self::$ventas[self::$year]['total_ref'][self::$parent_codfamilia][$key];
            $html .= $money ? ToolBox::coins()::format($money) : self::defaultMoney();

            $html .= ''
                . '</td>';

            for ($x = 1; $x <= 12; $x++) {
                $html .= '<td class="month text-right align-middle">';
                $html .= isset(self::$ventas[self::$year]['familias'][self::$parent_codfamilia][$key][$x]['pvptotal']) ? ToolBox::coins()::format(self::$ventas[self::$year]['familias'][self::$parent_codfamilia][$key][$x]['pvptotal']) : self::defaultMoney();
                $html .= ''
                    . '</td>';
            }

            $html .= ''
                . '</tr>';
        }

        $html .= ''
            . '</tbody>'
            . '</table>';

        return $html;
    }
}