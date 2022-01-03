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
class SummaryResultReport extends ResultReport
{
    public static $charts = array(
        'totales' => [],
        'families' => [],
    );

    protected static function charts_build()
    {
        /**
         * CHARTS
         * *****************************************************************
         */
        for ($mes = 1; $mes <= 12; $mes++) {
            self::$charts['totales']['ventas'][$mes-1] = self::$ventas[self::$year]['total_mes'][$mes];
            self::$charts['totales']['gastos'][$mes-1] = self::$gastos[self::$year]['total_mes'][$mes];
            self::$charts['totales']['resultado'][$mes-1] = self::$resultado[self::$year]['total_mes'][$mes];
        }

        foreach (self::$ventas[self::$year]['porc_fam'] as $codfamilia => $porc) {
            $totalaux = round(self::$ventas[self::$year]['total_fam'][$codfamilia], FS_NF0);
            $fam_desc = 'Sin Familia';
            if ($codfamilia != 'SIN_FAMILIA' && isset(self::$ventas[self::$year]['descripciones'][$codfamilia])) {
                $fam_desc = self::$ventas[self::$year]['descripciones'][$codfamilia];
            }

            self::$charts['families']['codfamilia'][] = $codfamilia;
            self::$charts['families']['labels'][] = $fam_desc;
            self::$charts['families']['porc'][] = $porc;
            self::$charts['families']['colors'][] = '#' . self::randomColor();
            self::$charts['families']['totales'][] = $totalaux;
        }
    }

    public static function render(array $formData)
    {
        self::apply($formData);
        self::charts_build();

        $html = ''
            . '<div class="table-responsive">'
            . '<table class="table mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th class="title h4"><b>' . ToolBox::i18n()->trans('summary') . '</b></th>'
            . '<th class="porc table-info">' . ToolBox::i18n()->trans('monthly-average') . '</th>'
            . '<th class="total table-info">' . ToolBox::i18n()->trans('total') . '</th>'
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
            . '<tbody>'
            . '<tr>'
            . '<td class="title align-middle"><b>' . ToolBox::i18n()->trans('sales') . '</b></td>'
            . '<td class="porc table-info">';

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
            $html .= '<td class="' . $css . '">';
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
            . '<td class="porc table-info">';

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
            $html .= '<td class="' . $css . '">';
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
            . '<td class="title align-middle"><b>' . ToolBox::i18n()->trans('result') . '</b></td>'
            . '<td class="porc table-info">';

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
            $html .= '<td class="' . $css . '">';
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