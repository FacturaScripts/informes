<?php

namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Tools;

class ClientsBillingReport extends ReportClients
{
    public static function render(array $formData): string
    {
        self::applyStartBuild($formData);
        $varName = ($formData['action'] == "load-billing") ? "billing" : "";
        
        $monthNames = ['total-year', 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];

        $categories = [
            'Clientes facturados' => 'count_clients',
            'Importe facturado' => 'total_facturas'        
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
            . '<tbody>';

        
        foreach ($categories as $categoryKey => $categoryValue) {
            $html .= self::generateCategoryRow($categoryKey, $varName, $categoryValue);
        }
           
        $html .= self::generateGroupsRow();

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
            $css = $x == 0 ? 'porc' : 'month';

            if ($categoryValue === 'total_facturas') {
                
                $money = self::${$varName}[self::$year]['total_mes'][$x] ?? 0;
                $lastmoney = self::${$varName}[self::$lastyear]['total_mes'][$x] ?? 0;
            } else {
             
                if ($x > 0) {

                    $dateRange = [
                        'desde' => date('Y-m-01', strtotime(self::$year . "-$x-01")),  
                        'hasta' => date('Y-m-t', strtotime(self::$year . "-$x-01"))
                    ];
                    $clientCount = self::countDistinctClients($dateRange, self::$codejercicio, 'facturascli'); 
                    $money = $clientCount;

                    
                    $dateRangeLastYear = [
                        'desde' => date('Y-m-01', strtotime(self::$lastyear . "-$x-01")),
                        'hasta' => date('Y-m-t', strtotime(self::$lastyear . "-$x-01"))
                    ];
                    $clientCountLastYear = self::countDistinctClients($dateRangeLastYear, self::$codejercicio_ant, 'facturascli');
                    $lastmoney = $clientCountLastYear;           

                } else {                 

                    $totalClientsYear = self::countDistinctClientsPerYear(self::$codejercicio, 'facturascli'); 
                    $totalClientsLastYear = self::countDistinctClientsPerYear(self::$codejercicio_ant, 'facturascli'); 

                    $money = $totalClientsYear;  
                    $lastmoney = $totalClientsLastYear; 
                }
            }

            $html .= self::generateTableCell($money, $lastmoney, $css);
        }

        $html .= '</tr>';
        return $html;
    }


    private static function getRowClass($categoryValue): string
    {      
       
        switch ($categoryValue) {
            case 'count_clients':
                return 'table-primary';
            case 'total_facturas':
                return 'table-info';
            case 'groups-clients':
                return 'table-white';                
        }

        return 'table-success';
    }

    private static function generateTableCell($money, $lastmoney, $css): string
    {
        $html = '<td class="' . $css . '">'
            . ($money ? number_format($money, 0) : '0')
            . '<div class="small">'
            . ($lastmoney ? number_format($lastmoney, 0) : '0')
            . '</div></td>';
        return $html;
    }


    
    private static function generateGroupsRow(): string
    {            
        $html = '<tr class="groups-clients">' 
        . '<td class="title align-middle" colspan="14"><b>' . Tools::lang()->trans('groups') . '</b></td>'
        . '</tr>';
        $groups = self::getGroupsClients();

        if(!empty($groups)){

            foreach ($groups as $group) {               
               
                $groupCod = ($group['codigo_grupo'] === '0') ? null : $group['codigo_grupo'];

                
                $html .= '<tr class="table-success">'
                . '<td class="title align-middle"><b>' . $group['nombre_grupo'] . '</b><br/>'
                . '<small>' . Tools::lang()->trans('previous') . '</small></td>';
    
                for ($x = 0; $x <= 12; $x++) {
                    $css = $x == 0 ? 'porc' : 'month';
    
                    if ($x > 0) {
    
                        $dateRange = [
                            'desde' => date('Y-m-01', strtotime(self::$year . "-$x-01")),  
                            'hasta' => date('Y-m-t', strtotime(self::$year . "-$x-01"))
                        ];
                        $clientCount = self::invoicingByGroup($dateRange, self::$codejercicio, $groupCod); 
                        $money = $clientCount;
                            
                        $dateRangeLastYear = [
                            'desde' => date('Y-m-01', strtotime(self::$lastyear . "-$x-01")),
                            'hasta' => date('Y-m-t', strtotime(self::$lastyear . "-$x-01"))
                        ];
                        $clientCountLastYear = self::invoicingByGroup($dateRangeLastYear, self::$codejercicio_ant, $groupCod);
                        $lastmoney = $clientCountLastYear;           
    
                    } else {                 
    
                        $totalClientsYear = self::invoicingByGroupPerYear(self::$codejercicio, $groupCod); 
                        $totalClientsLastYear = self::invoicingByGroupPerYear(self::$codejercicio_ant, $groupCod); 
    
                        $money = $totalClientsYear;  
                        $lastmoney = $totalClientsLastYear; 
                    }
                    $html .= self::generateTableCell($money, $lastmoney, $css);
                }
    
                $html .= '</tr>';
    
            }

        }        

      
        return $html;
    }



}
