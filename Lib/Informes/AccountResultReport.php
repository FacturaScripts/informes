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
class AccountResultReport extends ResultReport
{
    public static function render(array $formData)
    {
        self::apply($formData);

        $html = ''
            . '<table class="table table-hover mb-0">'
            . '<tbody>';

        foreach (self::$gastos[self::$year]['cuentas'][self::$parent_codcuenta] as $key => $value) {
            $html .= ''
                . '<tr>'
                . '<td class="title">' . self::$gastos[self::$year]['descripciones'][$key] . '</td>'
                . '<td class="porc text-right align-middle">';

            $percentage = (float) self::$gastos[self::$year]['porc_subcuenta'][self::$parent_codcuenta][$key];
            $html .= $percentage > 0 ? $percentage . ' %' : self::defaultPerc();

            $html .= ''
                . '</td>'
                . '<td class="total text-right align-middle">';

            $money = self::$gastos[self::$year]['total_subcuenta'][self::$parent_codcuenta][$key];
            $html .= $money ? ToolBox::coins()::format($money) : self::defaultMoney();

            $html .= ''
                . '</td>';

            for ($x = 1; $x <= 12; $x++) {
                $html .= '<td class="month text-right align-middle">';
                $html .= isset(self::$gastos[self::$year]['cuentas'][self::$parent_codcuenta][$key][$x]['pvptotal']) ? ToolBox::coins()::format(self::$gastos[self::$year]['cuentas'][self::$parent_codcuenta][$key][$x]['pvptotal']) : self::defaultMoney();
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