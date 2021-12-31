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
class SummaryResultResultReport extends ResultReport
{
    public static function render(array $formData)
    {
        self::apply($formData);

        $html = ''
            . '<div class="table-responsive">'
            . '<table class="table mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th class="card-title h4"><b>' . ToolBox::i18n()->trans('summary') . '</b></th>'
            . '<th class="porc table-info text-right">' . ToolBox::i18n()->trans('monthly-average') . '</th>'
            . '<th class="total table-info text-right">' . ToolBox::i18n()->trans('total') . '</th>'
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
            . '<tbody>'
            . '<tr>'
            . '<td class="title align-middle"><b>' . ToolBox::i18n()->trans('sales') . '</b></td>'
            . '<td class="porc table-info text-right">';

        $money = self::$ventas[self::$year]['total_mes']['media'];
        $lastmoney = self::$ventas[self::$lastyear]['total_mes']['media'];
        $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : self::defaultMoney();
        $html .= '<div class="small">';
        $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : self::defaultMoney();

        $html .= ''
            . '</div>'
            . '</td>';

        for ($x = 0; $x <= 12; $x++) {
            $css = $x == 0 ? 'total table-info' : 'month';
            $money = self::$ventas[self::$year]['total_mes'][$x];
            $lastmoney = self::$ventas[self::$lastyear]['total_mes'][$x];
            $html .= '<td class="' . $css . ' text-right">';
            $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : self::defaultMoney();
            $html .= '<div class="small">';
            $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : self::defaultMoney();
            $html .= ''
                . '</div>'
                . '</td>';
        }

        $html .= '</tr>'
            . '<tr>'
            . '<td class="title align-middle"><b>' . ToolBox::i18n()->trans('purchases') . '</b></td>'
            . '<td class="porc table-info text-right">';

        $money = self::$gastos[self::$year]['total_mes']['media'];
        $lastmoney = self::$gastos[self::$lastyear]['total_mes']['media'];
        $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : self::defaultMoney();
        $html .= '<div class="small">';
        $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : self::defaultMoney();

        $html .= ''
            . '</div>'
            . '</td>';

        for ($x = 0; $x <= 12; $x++) {
            $css = $x == 0 ? 'total table-info' : 'month';
            $money = self::$gastos[self::$year]['total_mes'][$x];
            $lastmoney = self::$gastos[self::$lastyear]['total_mes'][$x];
            $html .= '<td class="' . $css . ' text-right">';
            $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : self::defaultMoney();
            $html .= '<div class="small">';
            $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : self::defaultMoney();
            $html .= ''
                . '</div>'
                . '</td>';
        }

        $html .= ''
            . '</tr>'
            . '<tr>'
            . '<td class="porc align-middle"><b>' . ToolBox::i18n()->trans('result') . '</b></td>'
            . '<td class="title table-info text-right">';

        $money = self::$resultado[self::$year]['total_mes']['media'];
        $lastmoney = self::$resultado[self::$lastyear]['total_mes']['media'];
        $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : self::defaultMoney();
        $html .= '<div class="small">';
        $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : self::defaultMoney();

        $html .= ''
            . '</div>'
            . '</td>';

        for ($x = 0; $x <= 12; $x++) {
            $css = $x == 0 ? 'total table-info' : 'month';
            $money = self::$resultado[self::$year]['total_mes'][$x];
            $lastmoney = self::$resultado[self::$lastyear]['total_mes'][$x];
            $html .= '<td class="' . $css . ' text-right">';
            $html .= $money ? $money < 0 ? '<span class="text-danger">' . ToolBox::coins()::format($money) . '</span>' : ToolBox::coins()::format($money) : self::defaultMoney();
            $html .= '<div class="small">';
            $html .= $lastmoney ? ToolBox::coins()::format($lastmoney) : self::defaultMoney();
            $html .= ''
                . '</div>'
                . '</td>';
        }

        $html .= '</tr>'
            . '</tbody>'
            . '</table>'
            . '</div>';

        return $html;
    }
}