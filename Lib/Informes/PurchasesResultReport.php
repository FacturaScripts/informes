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
class PurchasesResultReport extends ResultReport
{
    public static function render(array $formData)
    {
        self::apply($formData);

        $html = ''
            . '<div class="table-responsive">'
            . '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th class="title"></th>'
            . '<th class="porc text-right">%</th>'
            . '<th class="total text-right">' . ToolBox::i18n()->trans('total') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('january') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('february') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('march') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('april') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('may') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('june') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('july') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('august') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('september') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('october') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('november') . '</th>'
            . '<th class="month text-right">' . ToolBox::i18n()->trans('december') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>';

        if (self::$gastos[self::$year]) {
            $html .= ''
                . '<tr>'
                . '<td class="title"></td>'
                . '<td class="porc align-middle"></td>';

            for ($x = 0; $x <= 12; $x++) {
                $css = $x == 0 ? 'total' : 'month';
                $money = self::$gastos[self::$year]['total_mes'][$x];
                $lastmoney = self::$gastos[self::$lastyear]['total_mes'][$x];
                $html .= '<td class="' . $css . ' text-right">';
                $html .= $money ? ToolBox::coins()::format($money) : self::defaultMoney();
                $html .= '<div class="small">';
                $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : self::defaultMoney();
                $html .= ''
                    . '</div>'
                    . '</td>';
            }

            $html .= ''
                . '</tr>';
        }

        foreach (self::$gastos[self::$year]['cuentas'] as $key => $value) {
            $html .= ''
                . '<tr codcuenta="' . $key . '" data-target="#gastos-' . $key . '" class="gastos cursor-pointer">'
                . '<td class="title align-middle">' . self::$gastos[self::$year]['descripciones'][$key] . '</td>'
                . '<td class="porc text-right align-middle">';

            $percentage = (float) self::$gastos[self::$year]['porc_cuenta'][$key];
            $html .= $percentage > 0 ? $percentage . ' %' : self::defaultPerc();

            $html .= ''
                . '</td>'
                . '<td class="total text-right align-middle">';

            $money = self::$gastos[self::$year]['total_cuenta'][$key];
            $html .= $money ? ToolBox::coins()::format($money) : self::defaultMoney();

            $html .= ''
                . '</td>';

            for ($x = 1; $x <= 12; $x++) {
                $html .= '<td class="month text-right align-middle">';
                $html .= isset(self::$gastos[self::$year]['total_cuenta_mes'][$key][$x]) ? ToolBox::coins()::format(self::$gastos[self::$year]['total_cuenta_mes'][$key][$x]) : self::defaultMoney();
                $html .= ''
                    . '</td>';
            }

            $html .= ''
                . '</tr>'
                . '<tr>'
                . '<td colspan="15" class="hiddenRow">'
                . '<div class="collapse" id="gastos-' . $key . '">'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        $html .= ''
            . '</tbody>'
            . '</table>'
            . '</div>';

        return $html;
    }
}