<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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
    public static $charts = [
        'totales' => [],
        'families' => [],
    ];

    public static function render(array $formData): string
    {
        self::apply($formData);
        self::chartsBuild();

        $monthNames = ['total', 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
        $categories = ['ventas' => 'sales', 'compras' => "purchases", 'gastos' => 'expenses', 'resultado' => 'result'];

        $html = '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th class="title"><b>' . Tools::lang()->trans('summary') . '</b></th>';

        foreach ($monthNames as $month) {
            $html .= '<th class="' . ($month === 'total' ? 'porc' : 'month') . '">' . Tools::lang()->trans($month) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($categories as $key => $category) {
            $html .= self::generateCategoryRow($key, $category);
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    private static function generateCategoryRow($categoryKey, $categoryName): string
    {
        $html = '<tr class="' . self::getRowClass($categoryName) . '">'
            . '<td class="title align-middle"><b>' . Tools::lang()->trans($categoryName) . '</b><br/>'
            . '<small>' . Tools::lang()->trans('previous') . '</small></td>';

        for ($x = 0; $x <= 12; $x++) {
            $css = $x == 0 ? 'porc' : 'month';
            $money = self::${$categoryKey}[self::$year]['total_mes'][$x];
            $lastmoney = self::${$categoryKey}[self::$lastyear]['total_mes'][$x] ?? 0;

            $html .= self::generateTableCell($money, $lastmoney, $css);
        }

        $html .= '</tr>';
        return $html;
    }

    private static function getRowClass($categoryName): string
    {
        switch ($categoryName) {
            case 'sales':
                return 'table-success';
            case 'purchases':
                return 'table-warning';
            case 'expenses':
                return 'table-danger';
            case 'result':
                return 'table-primary';
        }

        return 'table-success';
    }

    private static function generateTableCell($money, $lastmoney, $css): string
    {
        $html = '<td class="' . $css . '">'
            . ($money ? ($money < 0 ? '<span class="text-danger">' : '') . Tools::money($money) . ($money < 0 ? '</span>' : '') : self::defaultMoney())
            . '<div class="small">'
            . ($lastmoney ? Tools::money($lastmoney) : self::defaultMoney())
            . '</div></td>';
        return $html;
    }

    protected static function chartsBuild(): void
    {
        /**
         * CHARTS
         * *****************************************************************
         */
        for ($mes = 1; $mes <= 12; $mes++) {
            self::$charts['totales']['ventas'][$mes - 1] = round(self::$ventas[self::$year]['total_mes'][$mes], FS_NF0);
            self::$charts['totales']['compras'][$mes - 1] = round(self::$compras[self::$year]['total_mes'][$mes], FS_NF0);
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
                . '<td class="align-middle"><span style="color: ' . $color . '"><i class="fa-solid fa-square"></i></span></td>'
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
                . '<td class="align-middle"><span style="color: ' . $color . '"><i class="fa-solid fa-square"></i></span></td>'
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
                . '<td class="align-middle"><span style="color: ' . $color . '"><i class="fa-solid fa-square"></i></span></td>'
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
                . '<td class="align-middle"><span style="color: ' . $color . '"><i class="fa-solid fa-square"></i></span></td>'
                . '<td>' . self::$ventas[self::$year]['agentes'][$codagente]['descripcion'] . '</td>'
                . '<td class="porc align-middle">' . $porc . ' %</td>'
                . '<td class="total align-middle">' . Tools::money($totalaux) . '</td>'
                . '</tr>';
        }
    }
}
