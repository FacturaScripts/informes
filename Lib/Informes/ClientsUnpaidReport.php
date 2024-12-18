<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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

class ClientsUnpaidReport extends ReportClients
{
    public static function render(array $formData): string
    {
        self::applyStartBuild($formData);
        $varName = ($formData['action'] == "load-unpaid") ? "unpaid" : "";
        
        $monthNames = ['total', 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];

        $categories = [
            'unpaid-customers' => 'count_clients_impagos',
            'unpaid-amount' => 'total_facturas_impagas'
        ];
        
        $html = '<div class="table-responsive">'
            . '<table class="table table-hover mb-0">'
            . '<thead>'
            . '<tr>'
            . '<th class="title"></th>';

        
        foreach ($monthNames as $month) {
            $html .= '<th class="month">' . Tools::lang()->trans($month) . '</th>';
        }

        
        $html .= '</tr>'
            . '</thead>'
            . '<tbody  class="tbody-unpaid-clients">';

        
        foreach ($categories as $categoryKey => $categoryValue) {
            $html .= self::generateCategoryRow($categoryKey, $varName, $categoryValue);
        }

        $html .= '</tbody>'
            . '</table>'
            . '</div>';

        return $html;
    }


    private static function generateCategoryRow($categoryKey, $varName, $categoryValue): string
    {
        $html = '<tr class="' . self::getRowClass($categoryValue) . '">'
            . '<td class="title align-middle"><b>' . Tools::lang()->trans($categoryKey) . '</b><br/>'
            . '<small>' . Tools::lang()->trans('previous') . '</small></td>';

        for ($x = 0; $x <= 12; $x++) {

            $css = $x == 0 ? 'tot_anio' : 'month';            
            
            if ($categoryValue === 'total_facturas_impagas') {                
                $money = self::${$varName}[self::$year]['total_mes'][$x] ?? 0;
                $lastmoney = self::${$varName}[self::$lastyear]['total_mes'][$x] ?? 0;
            } else {
             
                if ($x > 0) {

                    $dateRange = [
                        'desde' => date('Y-m-01', strtotime(self::$year . "-$x-01")),  
                        'hasta' => date('Y-m-t', strtotime(self::$year . "-$x-01"))
                    ];
                    $clientCount = self::countDistinctClientsUnpaid($dateRange, self::$codejercicio, 'facturascli'); 
                    $money = $clientCount;

                    
                    $dateRangeLastYear = [
                        'desde' => date('Y-m-01', strtotime(self::$lastyear . "-$x-01")),
                        'hasta' => date('Y-m-t', strtotime(self::$lastyear . "-$x-01"))
                    ];
                    $clientCountLastYear = self::countDistinctClientsUnpaid($dateRangeLastYear, self::$codejercicio_ant, 'facturascli');
                    $lastmoney = $clientCountLastYear;
           

                } else {
                                            
                    $totalClientsYear = self::countDistinctClientsUnpaidPerYear(self::$codejercicio, 'facturascli');
                    $totalClientsLastYear = self::countDistinctClientsUnpaidPerYear(self::$codejercicio_ant, 'facturascli');                  

                    $money = $totalClientsYear; 
                    $lastmoney = $totalClientsLastYear;                    
                }
            }

            $html .= self::generateTableCell($money, $lastmoney, $css, $x, self::$codejercicio, self::$codejercicio_ant);
        }

        $html .= '</tr>';
        return $html;
    }


    private static function getRowClass($categoryValue): string
    {      
       
        switch ($categoryValue) {
            case 'count_clients_impagos':
                return 'table-primary';
            case 'total_facturas_impagas':
                return 'table-info';
        }

        return 'table-success';
    }

    private static function generateTableCell($money, $lastmoney, $css, $month, $year, $year_ant): string
    {
        $class='';
        if($css==='tot_anio'){
            $class='text-right';
        }
        $html = '<td>'
            . '<div style="cursor:pointer" title="Haz click para ver el detalle" class="' . $css . ' '.$class.' click-celda-impago" data-month="' . $month . '" data-year="' . $year . '">'
            . ($money ? number_format($money, 0) : '0')
            . '</div>'
            . '<div style="cursor:pointer" title="Haz click para ver el detalle" class="small text-right click-celda-impago" data-month="' . $month . '" data-year="' . $year_ant . '">'
            . ($lastmoney ? number_format($lastmoney, 0) : '0')
            . '</div></td>';
        return $html;
    }

    
    public static function generateClientsWithUnpaidInvocesRows(array $formData): string
    {      
        $action = $formData['action'];
        $month = $formData['month'];
        $ejercicio = $formData['year'];
        $codpais = $formData['codpais'];
        $provincia = $formData['provincia'];        
        
        
        $html = '<table class="table table-hover mb-0">'
            . '<thead><tr>'
            . '<th>Cliente</th>'
            . '<th class="text-center">Mes</th>'
            . '<th class="text-center">Año</th>'
            . '<th class="text-right">Importe impago</th>'
            . '<th class="text-right">Facturas</th>'
            . '</tr></thead>'
            . '<tbody>';

        
        if($month > 0){            
            $clients = self::selectAllClientWithUnpaidInvoices($month, $ejercicio, $codpais, $provincia);           
        }else{            
            $clients = self::selectAllClientPerYearWithUnpaidInvoices($ejercicio, $codpais, $provincia);            
        }
        

        if (!empty($clients)) {
            foreach ($clients as $client) {
                
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($client['nombrecliente']) . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars($client['mes']) . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars($client['anio']) . '</td>';
                $html .= '<td class="text-right">' . htmlspecialchars(number_format($client['importe_impago'], 0, ",",".")) . '</td>';                
                
                $codigosArray = explode(', ', $client['codigo_factura']); 
                $idsArray = explode(', ', $client['id_factura']); 
                $enlaces = []; 
                
                if (count($codigosArray) === count($idsArray)) {
                    foreach ($codigosArray as $index => $codigo) {
                        
                        $idFactura = $idsArray[$index];
                        
                        $enlaces[] = '<a href="/EditFacturaCliente?code=' . urlencode($idFactura) . '">' . htmlspecialchars($codigo) . '</a>';
                    }
                }
                
                $html .= '<td class="text-right">' . implode(', ', $enlaces) . '</td>';            
                $html .= '</tr>';
            }
        } else {
            
            $html .= '<tr><td colspan="9" class="text-center">No hay facturas impagas para este mes</td></tr>';
        }

        $html .= '</tbody></table>';

        return $html;
   
    }


}