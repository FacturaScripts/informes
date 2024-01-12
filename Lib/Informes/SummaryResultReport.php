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
class SummaryResultReport extends ResultReport
{
    public static $charts = array(
        'totales' => [],
        'families' => [],
    );

    public static function render(array $formData): string
    {
        self::apply($formData);
        self::charts_build();

        $html = '<div class="table-responsive">'
            . '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th class="title"><b>' . Tools::lang()->trans('summary') . '</b></th>'
            . '<th class="porc">' . Tools::lang()->trans('monthly-average') . '</th>'
            . '<th class="total">' . Tools::lang()->trans('total') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('january') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('february') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('march') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('april') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('may') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('june') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('july') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('august') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('september') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('october') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('november') . '</th>'
            . '<th class="month">' . Tools::lang()->trans('december') . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>';

        // ventas
        $html .= '<tr class="table-success">'
            . '<td class="title align-middle"><b>' . Tools::lang()->trans('sales') . '</b><br/>'
            . '<small>' . Tools::lang()->trans('previous') . '</small></td>'
            . '<td class="porc">';

        $money = self::$ventas[self::$year]['total_mes']['media'];
        $lastmoney = self::$ventas[self::$lastyear]['total_mes']['media'] ?? 0;
        $html .= $money ?
            $money < 0 ? '<span class="text-danger">' . Tools::money($money) . '</span>' : Tools::money($money) :
            self::defaultMoney();
        $html .= '<div class="small">';
        $html .= $lastmoney ? Tools::money($lastmoney) : self::defaultMoney();

        $html .= '</div>'
            . '</td>';

        for ($x = 0; $x <= 12; $x++) {
            $css = $x == 0 ? 'total' : 'month';
            $money = self::$ventas[self::$year]['total_mes'][$x];
            $lastmoney = self::$ventas[self::$lastyear]['total_mes'][$x] ?? 0;
            $html .= '<td class="' . $css . '">';
            $html .= $money ? $money < 0 ?
                '<span class="text-danger">' . Tools::money($money) . '</span>' : Tools::money($money) :
                self::defaultMoney();
            $html .= '<div class="small">';
            $html .= $lastmoney ? Tools::money($lastmoney) : self::defaultMoney();
            $html .= '</div>'
                . '</td>';
        }
        $html .= '</tr>';

        // compras
        $html .= '<tr class="table-danger">'
            . '<td class="title align-middle"><b>' . Tools::lang()->trans('expenses') . '</b><br/>'
            . '<small>' . Tools::lang()->trans('previous') . '</small></td>'
            . '<td class="porc">';

        $money = self::$gastos[self::$year]['total_mes']['media'];
        $lastmoney = self::$gastos[self::$lastyear]['total_mes']['media'] ?? 0;
        $html .= $money ? $money < 0 ?
            '<span class="text-danger">' . Tools::money($money) . '</span>' : Tools::money($money) :
            self::defaultMoney();
        $html .= '<div class="small">';
        $html .= $lastmoney ? Tools::money($lastmoney) : self::defaultMoney();

        $html .= '</div>'
            . '</td>';

        for ($x = 0; $x <= 12; $x++) {
            $css = $x == 0 ? 'total' : 'month';
            $money = self::$gastos[self::$year]['total_mes'][$x];
            $lastmoney = self::$gastos[self::$lastyear]['total_mes'][$x] ?? 0;
            $html .= '<td class="' . $css . '">';
            $html .= $money ? $money < 0 ?
                '<span class="text-danger">' . Tools::money($money) . '</span>' : Tools::money($money) :
                self::defaultMoney();
            $html .= '<div class="small">';
            $html .= $lastmoney ? Tools::money($lastmoney) : self::defaultMoney();
            $html .= '</div>'
                . '</td>';
        }
        $html .= '</tr>';

        // resultados
        $html .= '<tr class="table-primary">'
            . '<td class="title align-middle"><b>' . Tools::lang()->trans('result') . '</b><br/>'
            . '<small>' . Tools::lang()->trans('previous') . '</small></td>'
            . '<td class="porc">';

        $money = self::$resultado[self::$year]['total_mes']['media'];
        $lastmoney = self::$resultado[self::$lastyear]['total_mes']['media'] ?? 0;
        $html .= $money ? $money < 0 ?
            '<span class="text-danger">' . Tools::money($money) . '</span>' : Tools::money($money) :
            self::defaultMoney();
        $html .= '<div class="small">';
        $html .= $lastmoney ? Tools::money($lastmoney) : self::defaultMoney();

        $html .= '</div>'
            . '</td>';

        for ($x = 0; $x <= 12; $x++) {
            $css = $x == 0 ? 'total' : 'month';
            $money = self::$resultado[self::$year]['total_mes'][$x];
            $lastmoney = self::$resultado[self::$lastyear]['total_mes'][$x] ?? 0;
            $html .= '<td class="' . $css . '">';
            $html .= $money ? $money < 0 ?
                '<span class="text-danger">' . Tools::money($money) . '</span>' : Tools::money($money) :
                self::defaultMoney();
            $html .= '<div class="small">';
            $html .= $lastmoney ? Tools::money($lastmoney) : self::defaultMoney();
            $html .= '</div>'
                . '</td>';
        }

        $html .= '</tr>'
            . '</tbody>'
            . '</table>'
            . '</div>';
        return $html;
    }

    protected static function charts_build()
    {
        /**
         * CHARTS
         * *****************************************************************
         */
        for ($mes = 1; $mes <= 12; $mes++) {
            self::$charts['totales']['ventas'][$mes - 1] = round(self::$ventas[self::$year]['total_mes'][$mes], FS_NF0);
            self::$charts['totales']['gastos'][$mes - 1] = round(self::$gastos[self::$year]['total_mes'][$mes], FS_NF0);
            self::$charts['totales']['resultado'][$mes - 1] = round(self::$resultado[self::$year]['total_mes'][$mes], FS_NF0);
        }

        self::$charts['families']['table'] = '';
        arsort(self::$ventas[self::$year]['porc_fam']);
        foreach (self::$ventas[self::$year]['porc_fam'] as $codfamilia => $porc) {
            $totalaux = round(self::$ventas[self::$year]['total_fam'][$codfamilia], FS_NF0);
            $fam_desc = Tools::lang()->trans('no-family');
            if ($codfamilia != 'SIN_FAMILIA' && isset(self::$ventas[self::$year]['descripciones'][$codfamilia])) {
                $fam_desc = self::$ventas[self::$year]['descripciones'][$codfamilia];
            }

            $color = '#' . self::randomColor();
            self::$charts['families']['codfamilia'][] = $codfamilia;
            self::$charts['families']['labels'][] = $fam_desc;
            self::$charts['families']['porc'][] = $porc;
            self::$charts['families']['colors'][] = $color;
            self::$charts['families']['totales'][] = $totalaux;

            self::$charts['families']['table'] .= ''
                . '<tr>'
                . '<td class="align-middle"><span style="color: ' . $color . '"><i class="fas fa-square"></i></span></td>'
                . '<td>' . $fam_desc . '</td>'
                . '<td class="porc align-middle">' . $porc . ' %</td>'
                . '<td class="total align-middle">' . Tools::money($totalaux) . '</td>'
                . '</tr>';
        }

        self::$charts['series']['table'] = '';
        arsort(self::$ventas[self::$year]['porc_ser']);
        foreach (self::$ventas[self::$year]['porc_ser'] as $codserie => $porc) {
            $color = '#' . self::randomColor();
            $totalaux = round(self::$ventas[self::$year]['total_ser'][$codserie], FS_NF0);
            self::$charts['series']['codserie'][] = $codserie;
            self::$charts['series']['labels'][] = self::$ventas[self::$year]['series'][$codserie]['descripcion'];
            self::$charts['series']['porc'][] = $porc;
            self::$charts['series']['colors'][] = $color;
            self::$charts['series']['totales'][] = $totalaux;

            self::$charts['series']['table'] .= ''
                . '<tr>'
                . '<td class="align-middle"><span style="color: ' . $color . '"><i class="fas fa-square"></i></span></td>'
                . '<td>' . self::$ventas[self::$year]['series'][$codserie]['descripcion'] . '</td>'
                . '<td class="porc align-middle">' . $porc . ' %</td>'
                . '<td class="total align-middle">' . Tools::money($totalaux) . '</td>'
                . '</tr>';
        }

        self::$charts['pagos']['table'] = '';
        arsort(self::$ventas[self::$year]['porc_pag']);
        foreach (self::$ventas[self::$year]['porc_pag'] as $codpago => $porc) {
            $color = '#' . self::randomColor();
            $totalaux = round(self::$ventas[self::$year]['total_pag'][$codpago], FS_NF0);
            self::$charts['pagos']['codpago'][] = $codpago;
            self::$charts['pagos']['labels'][] = self::$ventas[self::$year]['pagos'][$codpago]['descripcion'];
            self::$charts['pagos']['porc'][] = $porc;
            self::$charts['pagos']['colors'][] = $color;
            self::$charts['pagos']['totales'][] = $totalaux;

            self::$charts['pagos']['table'] .= ''
                . '<tr>'
                . '<td class="align-middle"><span style="color: ' . $color . '"><i class="fas fa-square"></i></span></td>'
                . '<td>' . self::$ventas[self::$year]['pagos'][$codpago]['descripcion'] . '</td>'
                . '<td class="porc align-middle">' . $porc . ' %</td>'
                . '<td class="total align-middle">' . Tools::money($totalaux) . '</td>'
                . '</tr>';
        }

        self::$charts['agentes']['table'] = '';
        arsort(self::$ventas[self::$year]['porc_age']);
        foreach (self::$ventas[self::$year]['porc_age'] as $codagente => $porc) {
            $color = '#' . self::randomColor();
            $totalaux = round(self::$ventas[self::$year]['total_age'][$codagente], FS_NF0);
            self::$charts['agentes']['codagente'][] = $codagente;
            self::$charts['agentes']['labels'][] = self::$ventas[self::$year]['agentes'][$codagente]['descripcion'];
            self::$charts['agentes']['porc'][] = $porc;
            self::$charts['agentes']['colors'][] = $color;
            self::$charts['agentes']['totales'][] = $totalaux;

            self::$charts['agentes']['table'] .= ''
                . '<tr>'
                . '<td class="align-middle"><span style="color: ' . $color . '"><i class="fas fa-square"></i></span></td>'
                . '<td>' . self::$ventas[self::$year]['agentes'][$codagente]['descripcion'] . '</td>'
                . '<td class="porc align-middle">' . $porc . ' %</td>'
                . '<td class="total align-middle">' . Tools::money($totalaux) . '</td>'
                . '</tr>';
        }
    }
}