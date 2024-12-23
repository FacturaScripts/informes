<?php

namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Tools;

class SummaryReportClients extends ReportClients
{
    public static $charts = array(
        'totales' => []        
    );

    public static function render(array $formData): string
    {
        self::applyStartBuild($formData);
        self::charts_build();

        $monthNames = ['total', 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
        $categories = ['nuevos' => 'new', 'activos' => 'active', 'inactivos' => 'inactive', 'clientes' => 'all'];

        $html = '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th class="title"><b>' . Tools::lang()->trans('status') . '</b></th>';

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

            $html .= self::generateTableCell($money, $lastmoney, $css, $categoryKey);
        }

        $html .= '</tr>';
        return $html;
    }

    private static function getRowClass($categoryName): string
    {        
        switch ($categoryName) {
            case 'new':
                return 'table-info';
            case 'active':
                return 'table-success';
            case 'inactive':
                return 'table-danger';
            case 'all':
                return 'table-primary';
        }

        return 'table-success';
    }

    private static function generateTableCell($money, $lastmoney, $css, $categoryKey = ''): string
    {
        if ($categoryKey === 'clientes' || $categoryKey === 'activos' || $categoryKey === 'inactivos' || $categoryKey === 'nuevos') {
            $html = '<td class="' . $css . '">'
                . ($money ? ($money < 0 ? '<span class="text-danger">' : '') . number_format($money, 0) . ($money < 0 ? '</span>' : '') : '0')
                . '<div class="small">'
                . ($lastmoney ? number_format($lastmoney, 0) : '0')
                . '</div></td>';
        } else {
            $html = '<td class="' . $css . '">'
                . ($money ? ($money < 0 ? '<span class="text-danger">' : '') . Tools::money($money) . ($money < 0 ? '</span>' : '') : self::defaultMoney())
                . '<div class="small">'
                . ($lastmoney ? Tools::money($lastmoney) : self::defaultMoney())
                . '</div></td>';
        }
        return $html;
    }

    protected static function charts_build()
    {          
        for ($mes = 1; $mes <= 12; $mes++) {
            self::$charts['totales']['nuevos_clientes'][$mes - 1] = self::$nuevos[self::$year]['total_mes'][$mes];
        }  
        
        self::$charts['Grupos-Clientes']['table'] = '';
        arsort(self::$ventas[self::$year]['porc_ser']);
        foreach (self::$ventas[self::$year]['porc_ser'] as $codserie => $porc) {
            $color = '#' . self::randomColor();
            $totalaux = round(self::$ventas[self::$year]['total_ser'][$codserie], FS_NF0);
            self::$charts['Grupos-Clientes']['codserie'][] = $codserie;
            self::$charts['Grupos-Clientes']['labels'][] = self::$ventas[self::$year]['series'][$codserie]['descripcion'];
            self::$charts['Grupos-Clientes']['porc'][] = $porc;
            self::$charts['Grupos-Clientes']['colors'][] = $color;
            self::$charts['Grupos-Clientes']['totales'][] = $totalaux;

            self::$charts['Grupos-Clientes']['table'] .= '<tr>'
                . '<td class="align-middle"><span style="color: ' . $color . '"><i class="fas fa-square"></i></span></td>'
                . '<td>' . self::$ventas[self::$year]['series'][$codserie]['descripcion'] . '</td>'
                . '<td class="porc align-middle">' . $porc . ' %</td>'
                . '<td class="total align-middle">' . Tools::money($totalaux) . '</td>'
                . '</tr>';
        }
                

        self::$charts['Grupos']['table'] = '';
        arsort(self::$ventas[self::$year]['porc_pag']);
        foreach (self::$ventas[self::$year]['porc_pag'] as $codpago => $porc) {
            $color = '#' . self::randomColor();
            $totalaux = round(self::$ventas[self::$year]['total_pag'][$codpago], FS_NF0);
            self::$charts['Grupos']['codpago'][] = $codpago;
            self::$charts['Grupos']['labels'][] = self::$ventas[self::$year]['pagos'][$codpago]['descripcion'];
            self::$charts['Grupos']['porc'][] = $porc;
            self::$charts['Grupos']['colors'][] = $color;
            self::$charts['Grupos']['totales'][] = $totalaux;

            self::$charts['Grupos']['table'] .= '<tr>'
                . '<td class="align-middle"><span style="color: ' . $color . '"><i class="fas fa-square"></i></span></td>'
                . '<td>' . self::$ventas[self::$year]['pagos'][$codpago]['descripcion'] . '</td>'
                . '<td class="porc align-middle">' . $porc . ' %</td>'
                . '<td class="total align-middle">' . Tools::money($totalaux) . '</td>'
                . '</tr>';
        }
    }
}