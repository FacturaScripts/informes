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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\GrupoClientes;

class ReportClients
{
    const NO_GROUP = 'without-group';
    protected static $codejercicio;
    protected static $codejercicio_ant;
    protected static $gastos;
    protected static $lastyear;
    protected static $parent_codcuenta;
    protected static $parent_codfamilia;
    protected static $resultado;
    protected static $ventas;
    protected static $compras;
    protected static $year;
    protected static $clientes = [];
    protected static $activos = [];
    protected static $inactivos = [];
    
    protected static $billing = [];
    protected static $unpaid = [];
    protected static $codpais  = [];
    protected static $provincia = [];
    
    protected static function applyStartBuild(array $formData)
    {
        $eje = new Ejercicio();
        $eje->loadFromCode($formData['codejercicio']);

        $year = date('Y', strtotime($eje->fechafin));
    
        self::$codejercicio = FALSE;
        self::$codejercicio_ant = FALSE;
        self::$lastyear = FALSE;
        self::$year = FALSE;

        $modelEjerc = new Ejercicio();
        $where = [new DataBaseWhere('idempresa', $eje->idempresa)];
        $order = ['fechainicio' => 'desc'];

        foreach ($modelEjerc->all($where, $order, 0, 0) as $eje) {
            if ($eje->codejercicio == $formData['codejercicio'] or date('Y', strtotime($eje->fechafin)) == $year) {
                self::$codejercicio = $eje->codejercicio;
                self::$year = date('Y', strtotime($eje->fechafin));
            } else if (self::$year) {
                self::$codejercicio_ant = $eje->codejercicio;
                self::$lastyear = date('Y', strtotime($eje->fechafin));
                break;
            }
        }

        self::$parent_codcuenta = isset($formData['parent_codcuenta']) ? (string)$formData['parent_codcuenta'] : null;
        self::$parent_codfamilia = isset($formData['parent_codfamilia']) ? (string)$formData['parent_codfamilia'] : null;        

        self::$codpais = (!empty($formData['codpais']))? $formData['codpais']:false;
        self::$provincia = (!empty($formData['provincia']))? $formData['provincia']:false;

        switch ($formData['action']) {
            case 'load-summary':
                self::summary_count_clients_build_year(self::$year, self::$codejercicio);
                self::summary_count_clients_build_year(self::$lastyear, self::$codejercicio_ant);
                break;               

            case 'load-billing':
                self::billing_build_year(self::$year, self::$codejercicio, $formData['action']);
                self::billing_build_year(self::$lastyear, self::$codejercicio_ant, $formData['action']);
                break;

            case 'load-unpaid':                
                self::clients_unpaid_build_year(self::$year, self::$codejercicio, $formData['action']);
                self::clients_unpaid_build_year(self::$lastyear, self::$codejercicio_ant, $formData['action']);
                break;
        }
    }
   

    protected static function dataInvoicesNew(array $ventas, array $date, string $codejercicio, int $mes, float &$ventas_total_ser_meses, float &$ventas_total_pag_meses): array
    {
        $dataBase = new DataBase();

        // Generar SQL para facturas
        $sqlFacturas = self::generateInvoicesSQL($dataBase, $date, $codejercicio);
        $gruposclientes = $dataBase->select($sqlFacturas);

        foreach ($gruposclientes as $grupo) {
            $codgrupo = $grupo['grupo_codigo'];
            $importe = (int)$grupo['total_importe'];

            // Actualizar las ventas totales por grupo
            self::updateArrayAmountsAndCounts($ventas, 'total_pag_mes', 'total_pag', $codgrupo, $mes, $importe);
            $ventas['pagos'][$codgrupo][$mes] = ['pvptotal' => $importe];
            $ventas_total_pag_meses += $importe;
        }

        // Generar SQL para clientes
        $sqlClientes = self::generateClientsSQL($dataBase, $date);
        $gruposclientes2 = $dataBase->select($sqlClientes);

        foreach ($gruposclientes2 as $grupo) {
            $codgrupo = $grupo['grupo_codigo'];
            $count = (int)$grupo['total_clientes'];

            // Actualizar el conteo de clientes por grupo
            self::updateArrayAmountsAndCounts($ventas, 'total_ser_mes', 'total_ser', $codgrupo, $mes, $count);
            $ventas['series'][$codgrupo][$mes] = ['pvptotal' => $count];
            $ventas_total_ser_meses += $count;
        }

        return $ventas;
    }

    protected static function updateArrayAmountsAndCounts(array &$ventas, string $mesKey, string $totalKey, string $codgrupo, int $mes, int $value): void
    {
        if (isset($ventas[$mesKey][$codgrupo][$mes])) {
            $ventas[$mesKey][$codgrupo][$mes] += $value;
        } else {
            $ventas[$mesKey][$codgrupo][$mes] = $value;
        }

        if (isset($ventas[$totalKey][$codgrupo])) {
            $ventas[$totalKey][$codgrupo] += $value;
        } else {
            $ventas[$totalKey][$codgrupo] = $value;
        }
    }

    protected static function generateInvoicesSQL($dataBase, array $date, string $codejercicio): string
    {
        $baseSQL = "
            SELECT SUM(fc.totaleuros) AS total_importe, 
                   IF(g.nombre IS NULL, 'Sin Grupo', g.nombre) AS grupo_nombre, 
                   IF(g.codgrupo IS NULL, '0', g.codgrupo) AS grupo_codigo
            FROM facturascli fc
            LEFT JOIN clientes cli ON fc.codcliente = cli.codcliente
            LEFT JOIN gruposclientes g ON g.codgrupo = cli.codgrupo";
    
        if (self::$codpais || self::$provincia) {
            $baseSQL .= " LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON fc.codcliente=contactos_paises.codcliente";
        }
    
        $baseSQL .= "
            WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
              AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
              AND fc.codejercicio = " . $dataBase->var2str($codejercicio);
    
        if (self::$codpais || self::$provincia) {
            $baseSQL .= " AND contactos_paises.codigo_pais IS NOT NULL";
        }
    
        $baseSQL .= " GROUP BY g.codgrupo";
    
        return $baseSQL;
    }

    protected static function generateClientsSQL($dataBase, array $date): string
    {
        $baseSQL = "
            SELECT COUNT(cli.codcliente) AS total_clientes, 
                IF(g.nombre IS NULL, 'Sin Grupo', g.nombre) AS grupo_nombre, 
                IF(g.codgrupo IS NULL, '0', g.codgrupo) AS grupo_codigo
            FROM clientes AS cli
            LEFT JOIN gruposclientes g ON cli.codgrupo = g.codgrupo";

        if (self::$codpais || self::$provincia) {
            $baseSQL .= " LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON cli.codcliente = contactos_paises.codcliente";
        }

        $baseSQL .= "
            WHERE cli.fechaalta >= " . $dataBase->var2str($date['desde']) . "
            AND cli.fechaalta <= " . $dataBase->var2str($date['hasta']);

        if (self::$codpais || self::$provincia) {
            $baseSQL .= " AND contactos_paises.codigo_pais IS NOT NULL";
        }

        $baseSQL .= " GROUP BY cli.codgrupo";

        return $baseSQL;
    }   

    protected static function days_in_month($month, $year)
    {        
        return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
    }

    protected static function defaultMoney(): string
    {
        return '<span style="color:#ccc;">' . Tools::money(0) . '</span>';
    }
  
    protected static function randomColor(): string
    {
        return substr(str_shuffle('ABCDEF0123456789'), 0, 6);
    }
   
    protected static function setDescriptionGroupsForBilling(array $ventas): array
    {
        foreach ($ventas['pagos'] as $codpago => $pagos) {            
            if ($codpago === '0' || empty($codpago)) {
                $ventas['pagos'][$codpago]['descripcion'] = 'Sin agrupar';
                continue;
            }            
            $pago = new GrupoClientes();
            if ($pago->loadFromCode($codpago)) {
                $ventas['pagos'][$codpago]['descripcion'] = $pago->nombre;
                continue;
            }

            $ventas['pagos'][$codpago]['descripcion'] = $codpago;
        }

        return $ventas;
    }
    
    protected static function setDescriptionGroupsAndClients(array $ventas): array
    {
        foreach ($ventas['series'] as $codserie => $series) {

            if ($codserie === '0' || empty($codserie)) {
                $ventas['series'][$codserie]['descripcion'] = 'Sin agrupar';
                continue;
            }
            $serie = new GrupoClientes();
            if ($serie->loadFromCode($codserie)) {
                $ventas['series'][$codserie]['descripcion'] = $serie->nombre;
                continue;
            }

            $ventas['series'][$codserie]['descripcion'] = $codserie;
        }

        return $ventas;
    }    

    protected static function setPercentageGroupsForBilling(array $ventas, float $ventas_total_pag_meses): array
    {
        foreach ($ventas['pagos'] as $codpago => $pagos) {
            if ($ventas_total_pag_meses != 0) {
                $ventas['porc_pag'][$codpago] = round($ventas['total_pag'][$codpago] * 100 / $ventas_total_pag_meses, FS_NF0);
            }
        }

        return $ventas;
    }

   
    protected static function setPercentageGroupsAndClients(array $ventas, float $ventas_total_ser_meses): array
    {
        foreach ($ventas['series'] as $codserie => $series) {
            if ($ventas_total_ser_meses != 0) {
                $ventas['porc_ser'][$codserie] = round($ventas['total_ser'][$codserie] * 100 / $ventas_total_ser_meses, FS_NF0);
            }
        }

        return $ventas;
    }
       
    protected static function countTotalClients($mes, $year, $dataBase)
    {
        $clientes['total_mes'][$mes]=0;
        $dia_mes = ReportClients::days_in_month($mes, $year);
        $date['desde'] = date('01-' . $mes . '-' . $year);
        $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

        $sql = "SELECT COUNT(cli.codcliente) AS total_clientes
                FROM clientes AS cli
                WHERE cli.fechaalta >= " . $dataBase->var2str($date['desde']) . "
                AND cli.fechaalta <= " . $dataBase->var2str($date['hasta']) ;

        $result = $dataBase->select($sql);

        return !empty($result) ? (int)$result[0]['total_clientes'] : 0;

    }

    protected static function countTotalClientsPerCountry($mes, $year, $dataBase)
    {
        $clientes['total_mes'][$mes]=0;
        $dia_mes = ReportClients::days_in_month($mes, $year);
        $date['desde'] = date('01-' . $mes . '-' . $year);
        $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

        $sql = "SELECT COUNT(*) AS total_clientes
                FROM (
	                SELECT cli.codcliente, contactos_paises.codigo_pais
                    FROM clientes AS cli
                    LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON cli.codcliente=contactos_paises.codcliente  
                    					 
                    WHERE cli.fechaalta >= " . $dataBase->var2str($date['desde']) . "
                    AND cli.fechaalta <= " . $dataBase->var2str($date['hasta']) . "
                    AND contactos_paises.codigo_pais IS NOT NULL 
				) AS clientes_con_esp";

        $result = $dataBase->select($sql);

        return !empty($result) ? (int)$result[0]['total_clientes'] : 0;

    }

    protected static function countActiveClients($mes, $year, $dataBase)
    {
        $activos['total_mes'][$mes]=0;
        $dia_mes = ReportClients::days_in_month($mes, $year);
        $date['desde'] = date('01-' . $mes . '-' . $year);
        $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

        $sql = "SELECT COUNT(cli.codcliente) AS active_clientes
                FROM clientes AS cli
                WHERE cli.fechaalta >= " . $dataBase->var2str($date['desde']) . "
                AND cli.fechaalta <= " . $dataBase->var2str($date['hasta']) . "
                AND cli.debaja=0" ;

        $result = $dataBase->select($sql);

        return !empty($result) ? (int)$result[0]['active_clientes'] : 0;

    }

    protected static function countActiveClientsPerCountry($mes, $year, $dataBase)
    {
        $activos['total_mes'][$mes]=0;
        $dia_mes = ReportClients::days_in_month($mes, $year);
        $date['desde'] = date('01-' . $mes . '-' . $year);
        $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

        $sql = "SELECT COUNT(*) AS active_clientes
        FROM (
            SELECT cli.codcliente, contactos_paises.codigo_pais
            FROM clientes AS cli
            LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON cli.codcliente=contactos_paises.codcliente 
            					 
            WHERE cli.fechaalta >= " . $dataBase->var2str($date['desde']) . "
            AND cli.fechaalta <= " . $dataBase->var2str($date['hasta']) . "
            AND cli.debaja=0
            AND contactos_paises.codigo_pais IS NOT NULL 
        ) AS clientes_con_esp";

        $result = $dataBase->select($sql);

        return !empty($result) ? (int)$result[0]['active_clientes'] : 0;

    }

    
    protected static function countInactiveClients($mes, $year, $dataBase)
    {
        $inactivos['total_mes'][$mes]=0;
        $dia_mes = ReportClients::days_in_month($mes, $year);
        $date['desde'] = date('01-' . $mes . '-' . $year);
        $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

        $sql = "SELECT COUNT(cli.codcliente) AS inactive_clientes
                FROM clientes AS cli
                WHERE cli.fechabaja >= " . $dataBase->var2str($date['desde']) . "
                AND cli.fechabaja <= " . $dataBase->var2str($date['hasta']) . "
                AND cli.debaja=1" ;

        $result = $dataBase->select($sql);

        return !empty($result) ? (int)$result[0]['inactive_clientes'] : 0;

    }
    
    protected static function countInactiveClientsPerCountry($mes, $year, $dataBase)
    {
        $inactivos['total_mes'][$mes]=0;
        $dia_mes = ReportClients::days_in_month($mes, $year);
        $date['desde'] = date('01-' . $mes . '-' . $year);
        $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);
        
        $sql = "SELECT COUNT(*) AS inactive_clientes
                FROM (
                    SELECT cli.codcliente, contactos_paises.codigo_pais
                    FROM clientes AS cli
                    LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON cli.codcliente=contactos_paises.codcliente                    
                    
                    WHERE cli.fechabaja >= " . $dataBase->var2str($date['desde']) . "
                    AND cli.fechabaja <= " . $dataBase->var2str($date['hasta']) . "
                    AND cli.debaja=1
                    AND contactos_paises.codigo_pais IS NOT NULL 
                ) AS clientes_con_esp";                

        $result = $dataBase->select($sql);

        return !empty($result) ? (int)$result[0]['inactive_clientes'] : 0;

    }


    protected static function countTotalClientsWithInvoices($mes, $year, $dataBase)
    {
        //$facturas_cli['total_mes'][$mes]=0;
        $dia_mes = ReportClients::days_in_month($mes, $year);
        $date['desde'] = date('01-' . $mes . '-' . $year);
        $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

        $sql = "SELECT COUNT(DISTINCT codcliente) AS total_clientes
                FROM facturascli
                WHERE fecha >= " . $dataBase->var2str($date['desde']) . "
                AND fecha <= " . $dataBase->var2str($date['hasta']) ;

        $result = $dataBase->select($sql);

        return !empty($result) ? (int)$result[0]['total_clientes'] : 0;
    }
    
    protected static function summary_count_clients_build_year($year, $codejercicio)
    {
        self::sales_purchases_build_year($year, $codejercicio, "load-summary");       

        $resultado = array(
            'total_mes' => [],
        );

        $dataBase = new DataBase();
                
        for ($mes = 1; $mes <= 12; $mes++) {
            
            $resultado['total_mes'][$mes] = 0;

            if (!isset(self::$ventas[$year]['total_mes'][$mes])) {
                self::$ventas[$year]['total_mes'][$mes] = 0;
                self::$ventas[$year]['total_mes']['media'] = 0;
            }

            if (!isset(self::$gastos[$year]['total_mes'][$mes])) {
                self::$gastos[$year]['total_mes'][$mes] = 0;
                self::$gastos[$year]['total_mes']['media'] = 0;
            }

            
            $resultado['total_mes'][$mes] = round(self::$ventas[$year]['total_mes'][$mes] - self::$gastos[$year]['total_mes'][$mes], FS_NF0);
            
            
            if( self::$codpais == false && self::$provincia == false){
                $clientes['total_mes'][$mes] = self::countTotalClients($mes, $year, $dataBase);
                $activos['total_mes'][$mes] = self::countActiveClients($mes, $year, $dataBase);
                $inactivos['total_mes'][$mes] = self::countInactiveClients($mes, $year, $dataBase);    
            }else{
                $clientes['total_mes'][$mes] = self::countTotalClientsPerCountry($mes, $year, $dataBase);
                $activos['total_mes'][$mes] = self::countActiveClientsPerCountry($mes, $year, $dataBase);
                $inactivos['total_mes'][$mes] = self::countInactiveClientsPerCountry($mes, $year, $dataBase);
            }                        

        }

        if (!isset(self::$ventas[$year]['total_mes'][0])) {
            self::$ventas[$year]['total_mes'][0] = 0;
        }

        if (!isset(self::$gastos[$year]['total_mes'][0])) {
            self::$gastos[$year]['total_mes'][0] = 0;
        }       

        
        $clientes['total_mes'][0] = array_sum($clientes['total_mes']); 
        $clientes['total_mes']['media'] = round($clientes['total_mes'][0] / 12, FS_NF0); 
        
        self::$clientes[$year] = $clientes;      
        
        
        $activos['total_mes'][0] = array_sum($activos['total_mes']); 
        $activos['total_mes']['media'] = round($activos['total_mes'][0] / 12, FS_NF0); 
        
        self::$activos[$year] = $activos;
        
        
        $inactivos['total_mes'][0] = array_sum($inactivos['total_mes']); 
        $inactivos['total_mes']['media'] = round($inactivos['total_mes'][0] / 12, FS_NF0); 
        
        self::$inactivos[$year] = $inactivos;       
         
        $resultado['total_mes'][0] = round(self::$ventas[$year]['total_mes'][0] - self::$gastos[$year]['total_mes'][0], FS_NF0);
        $resultado['total_mes']['media'] = round((self::$ventas[$year]['total_mes']['media'] - self::$gastos[$year]['total_mes']['media']), FS_NF0);

        
        self::$resultado[$year] = $resultado;
    }

    protected static function getContactosPaises($dataBase, $codpais, $provincia)
    {
  
        
        $query = "(
            SELECT DISTINCT co.codcliente,
            GROUP_CONCAT(DISTINCT co.codpais ORDER BY co.codpais SEPARATOR ', ') AS codigo_pais,
            GROUP_CONCAT(DISTINCT co.provincia ORDER BY co.provincia SEPARATOR ', ') AS provincia_nombre
            FROM contactos co 
            WHERE co.codpais <> '' 
            AND co.codpais IS NOT NULL 
            AND co.codcliente IS NOT NULL 
            GROUP BY co.codcliente ";

        
        $havingConditions = [];

        if (!empty($codpais)) {
            $havingConditions[] = "FIND_IN_SET(" . $dataBase->var2str($codpais) . ", codigo_pais) > 0";
           
        }

        if (!empty($provincia)) {
            $havingConditions[] = "FIND_IN_SET(" . $dataBase->var2str($provincia) . ", provincia_nombre) > 0";
          
        }

        
        if (!empty($havingConditions)) {
            $query .= " HAVING " . implode(' AND ', $havingConditions);
           
        }

        
        $query .= ") AS contactos_paises";

        return $query;                
    }

    protected static function billing_build_year(string $year, string $codejercicio, string $action): void
    {
        $key = ($action == "load-billing" ) ? "billing" : "";

        $date = array(
            'desde' => '',
            'hasta' => '',
        );

        ${$key} = array(        
            'total_mes' => [],           
        );

        $ventas_total_fam_meses = 0;        
        $countMonth = 0;

        
        for ($mes = 1; $mes <= 12; $mes++) {
            
            ${$key}['total_mes'][$mes] = 0;

            if ($year) {
                $dia_mes = ReportClients::days_in_month($mes, $year);
                $date['desde'] = date('01-' . $mes . '-' . $year);
                $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

                $tablename = ($action == "load-billing") ? "facturascli" : "";            
                ${$key} = self::invoiceLinesAmount(${$key}, $date, $codejercicio, $mes, $ventas_total_fam_meses, $countMonth, $tablename);                
                
            
            }
        }

        
        ${$key}['total_mes'][0] = round($ventas_total_fam_meses, FS_NF0);

        if ($countMonth > 0) {
            ${$key}['total_mes']['media'] = round($ventas_total_fam_meses / $countMonth, FS_NF0);
        } else {
            ${$key}['total_mes']['media'] = round($ventas_total_fam_meses, FS_NF0);
        }               
        
        self::${$key}[$year] = ${$key};
    }

    protected static function clients_unpaid_build_year(string $year, string $codejercicio, string $action): void
    {
        $key = ($action == "load-unpaid") ? "unpaid" : "";

        $date = array(
            'desde' => '',
            'hasta' => '',
        );

        ${$key} = array(    
            'total_mes' => []           
        );

        $ventas_total_fam_meses = 0;       

        
        $countMonth = 0;

        
        for ($mes = 1; $mes <= 12; $mes++) {
            
            ${$key}['total_mes'][$mes] = 0;

            if ($year) {
                $dia_mes = ReportClients::days_in_month($mes, $year);
                $date['desde'] = date('01-' . $mes . '-' . $year);
                $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

                $tablename = ($action == "load-unpaid") ? "facturascli" : "";                

                ${$key} = self::invoicesWithUnpaidReceipts(${$key}, $date, $codejercicio, $mes, $ventas_total_fam_meses, $countMonth, $tablename);                                            
            }
        }

      
        
        ${$key}['total_mes'][0] = round($ventas_total_fam_meses, FS_NF0);

        if ($countMonth > 0) {
            ${$key}['total_mes']['media'] = round($ventas_total_fam_meses / $countMonth, FS_NF0);
        } else {
            ${$key}['total_mes']['media'] = round($ventas_total_fam_meses, FS_NF0);
        }                
        
        self::${$key}[$year] = ${$key};
    }
   
    
    protected static function sales_purchases_build_year(string $year, string $codejercicio, string $action): void
    {

        $key = ($action == "load-summary" or $action == "load-family-sales") ? "ventas" : "compras";

        $date = array(
            'desde' => '',
            'hasta' => '',
        );

        ${$key} = array(
            
            'pagos' => [],        
            'series' => [],            
            'total_mes' => [],            
            'porc_ser' => [],            
            'porc_pag' => []            
        );

        $ventas_total_fam_meses = 0;
        $ventas_total_ser_meses = 0;
        $ventas_total_pag_meses = 0;        

        
        $countMonth = 0;

        
        for ($mes = 1; $mes <= 12; $mes++) {
            
            ${$key}['total_mes'][$mes] = 0;

            if ($year) {
                $dia_mes = ReportClients::days_in_month($mes, $year);
                $date['desde'] = date('01-' . $mes . '-' . $year);
                $date['hasta'] = date($dia_mes . '-' . $mes . '-' . $year);

                $tablename = ($action == "load-summary") ? "facturascli" : "";
                
                ${$key} = self::invoiceLines(${$key}, $date, $codejercicio, $mes, $ventas_total_fam_meses, $countMonth, $tablename);                
                ${$key} = self::dataInvoicesNew(${$key}, $date, $codejercicio, $mes, $ventas_total_ser_meses, $ventas_total_pag_meses);                
                
                 if ($year == self::$year) {                    
                    ${$key} = self::setDescriptionGroupsAndClients(${$key});
                    ${$key} = self::setDescriptionGroupsForBilling(${$key});
                    
                }
            }
        }

        ${$key}['total_mes'][0] = round($ventas_total_fam_meses, FS_NF0);

        if ($countMonth > 0) {
            ${$key}['total_mes']['media'] = round($ventas_total_fam_meses / $countMonth, FS_NF0);
        } else {
            ${$key}['total_mes']['media'] = round($ventas_total_fam_meses, FS_NF0);
        }
                        
        ${$key} = self::setPercentageGroupsAndClients(${$key}, $ventas_total_ser_meses);
        ${$key} = self::setPercentageGroupsForBilling(${$key}, $ventas_total_pag_meses);        
        
        self::${$key}[$year] = ${$key};
    }

    protected static function invoiceLines(array $ventas, array $date, string $codejercicio, int $mes, float &$ventas_total_fam_meses, int &$countMonth, string $tablename): array
    {
        $dataBase = new DataBase();       

        if( self::$codpais == false && self::$provincia == false){
            $sql = "SELECT sum(fc.totaleuros)  as pvptotal 
            FROM {$tablename} as fc 
            WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
            AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
            AND fc.codejercicio = " . $dataBase->var2str($codejercicio);   
        }else{
            $sql = "SELECT sum(fc.totaleuros)  as pvptotal 
            FROM {$tablename} as fc 
            LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON fc.codcliente=contactos_paises.codcliente
            WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
            AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
            AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
            AND contactos_paises.codigo_pais IS NOT NULL";   
        }
         
        
        $lineas = $dataBase->select($sql);
        foreach ($lineas as $dl) {
            
            $pvptotal = (int)$dl['pvptotal'];            
            
            $ventas['total_mes'][$mes] = $pvptotal + $ventas['total_mes'][$mes];
            $ventas_total_fam_meses = $pvptotal + $ventas_total_fam_meses;

        }

        if ($ventas['total_mes'][$mes] > 0) {
            $countMonth++;
        }

        return $ventas;
    }

    protected static function invoiceLinesAmount(array $ventas, array $date, string $codejercicio, int $mes, float &$ventas_total_fam_meses, int &$countMonth, string $tablename): array
    {
        $dataBase = new DataBase();

        if( self::$codpais == false && self::$provincia == false){
          
            
            $sql = "SELECT SUM(fc.totaleuros) AS pvptotal 
                            FROM {$tablename} as fc             
                            WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
                            AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
                            AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
                            GROUP BY fc.idfactura"; 

            
        }else{
        
            

            $sql = "SELECT SUM(fc.totaleuros) AS pvptotal 
                    FROM facturascli as fc   
                    LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON fc.codcliente=contactos_paises.codcliente                     

                    WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
                    AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
                    AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
                    AND contactos_paises.codigo_pais IS NOT NULL";                         
        }                                     

        $lineas = $dataBase->select($sql);
        foreach ($lineas as $dl) {
            $pvptotal = (float)$dl['pvptotal'];
            
            $ventas['total_mes'][$mes] += $pvptotal;
            $ventas_total_fam_meses += $pvptotal;
        }

        if ($ventas['total_mes'][$mes] > 0) {
            $countMonth++;
        }

        return $ventas;
    }

    
    protected static function countDistinctClientsPerYear(string $codejercicio, string $tablename): int
    {
        $dataBase = new DataBase();

        if( self::$codpais == false && self::$provincia == false){

            $sql = "SELECT COUNT(DISTINCT fc.codcliente) as client_count 
                    FROM {$tablename} as fc
                    WHERE fc.codejercicio = " . $dataBase->var2str($codejercicio);
        }else{
            $sql = "SELECT COUNT(*) AS client_count
                    FROM (
                        SELECT fc.codcliente, contactos_paises.codigo_pais
                        FROM facturascli as fc                
                        LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON fc.codcliente=contactos_paises.codcliente
                                                        
                        WHERE fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
                        AND contactos_paises.codigo_pais IS NOT NULL
                        GROUP BY fc.codcliente          
                    ) AS clientes_facturados";
        }

        $result = $dataBase->select($sql);
        return $result[0]['client_count'] ?? 0; 
    }

    protected static function countDistinctClients(array $date, string $codejercicio, string $tablename): int
    {
        $dataBase = new DataBase();

        if( self::$codpais == false && self::$provincia == false){

            $sql = "SELECT COUNT(DISTINCT fc.codcliente) as client_count 
                    FROM {$tablename} as fc
                    WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
                    AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
                    AND fc.codejercicio = " . $dataBase->var2str($codejercicio);
        }else{
            $sql = "SELECT COUNT(*) AS client_count
                    FROM (
                        SELECT fc.codcliente, contactos_paises.codigo_pais
                        FROM facturascli as fc                
                        LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON fc.codcliente=contactos_paises.codcliente
                                                        
                        WHERE fc.fecha >=  " . $dataBase->var2str($date['desde']) . "
                        AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
                        AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
                        AND contactos_paises.codigo_pais IS NOT NULL
                        GROUP BY fc.codcliente          
                    ) AS clientes_facturados";
        }

        $result = $dataBase->select($sql);
        return $result[0]['client_count'] ?? 0; 
    }

    protected static function invoicesWithUnpaidReceipts(array $ventas, array $date, string $codejercicio, int $mes, float &$ventas_total_fam_meses, int &$countMonth, string $tablename): array
    {
        $dataBase = new DataBase();

        if( self::$codpais == false && self::$provincia == false){
            $sql = "SELECT SUM(rpc.importe) AS pvptotal
                    FROM {$tablename} fc
                    LEFT JOIN recibospagoscli rpc ON fc.idfactura=rpc.idfactura
                    WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
                    AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
                    AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "  
                    AND rpc.pagado = 0 
                    HAVING pvptotal <>0";

        }else{
            $sql = "SELECT SUM(rpc.importe) AS pvptotal
                    FROM facturascli fc
                    LEFT JOIN recibospagoscli rpc ON fc.idfactura=rpc.idfactura                
                    LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON fc.codcliente=contactos_paises.codcliente 
                    WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
                    AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
                    AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "  
                    AND rpc.pagado = 0 
                    AND contactos_paises.codigo_pais IS NOT NULL
                    HAVING pvptotal <>0";
        }

        $lineas = $dataBase->select($sql);       

        foreach ($lineas as $dl) {
            $pvptotal = (float)$dl['pvptotal'];

            // Solo se acumula el total mensual y el total general
            $ventas['total_mes'][$mes] += $pvptotal;
            $ventas_total_fam_meses += $pvptotal;
        }

        if ($ventas['total_mes'][$mes] > 0) {
            $countMonth++;
        }

        return $ventas;
    }

    protected static function countDistinctClientsUnpaid(array $date, string $codejercicio, string $tablename): int
    {
        $dataBase = new DataBase();

        if( self::$codpais == false && self::$provincia == false){
            $sql = "SELECT COUNT(DISTINCT fc.codcliente) AS client_count
                    FROM {$tablename} fc
                    LEFT JOIN recibospagoscli rpc ON fc.idfactura = rpc.idfactura
                    WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
                    AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
                    AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
                    AND rpc.pagado = 0
                    AND rpc.importe <> 0";
        }else{
            $sql = "SELECT COUNT(DISTINCT fc.codcliente) AS client_count , contactos_paises.codigo_pais
                    FROM facturascli fc
                    LEFT JOIN recibospagoscli rpc ON fc.idfactura = rpc.idfactura                    					       
					LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON fc.codcliente=contactos_paises.codcliente                    
					WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
                    AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
                    AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
					AND rpc.pagado = 0
					AND rpc.importe <> 0
					AND contactos_paises.codigo_pais IS NOT NULL";
        }


        $result = $dataBase->select($sql);
        return $result[0]['client_count'] ?? 0;
    }

    protected static function countDistinctClientsUnpaidPerYear(string $codejercicio, string $tablename): int
    {
        $dataBase = new DataBase();

        if( self::$codpais == false && self::$provincia == false){
                
            $sql = "SELECT COUNT(DISTINCT fc.codcliente) AS client_count
                    FROM {$tablename} fc
                    LEFT JOIN recibospagoscli rpc ON fc.idfactura = rpc.idfactura
                    WHERE fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
                    AND rpc.pagado = 0
                    AND rpc.importe <> 0";
        }else{
            $sql = "SELECT COUNT(DISTINCT fc.codcliente) AS client_count
                    FROM {$tablename} fc
                    LEFT JOIN recibospagoscli rpc ON fc.idfactura = rpc.idfactura
                    LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON fc.codcliente=contactos_paises.codcliente  
                    WHERE fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
                    AND rpc.pagado = 0
                    AND rpc.importe <> 0
                    AND contactos_paises.codigo_pais IS NOT NULL";
        }

        $result = $dataBase->select($sql);
        return $result[0]['client_count'] ?? 0;
    }

    protected static function selectAllClientWithUnpaidInvoices(int $month, string $codejercicio, $codpais, $provincia): array
    {
    
        $dataBase = new DataBase();

        if( $codpais == false && $provincia == false){
            $sql = "SELECT                 
            fc.nombrecliente, 
            SUM(rpc.importe) AS importe_impago, 
            MONTH(fc.fecha) AS mes, 
            YEAR(fc.fecha) AS anio, 
            GROUP_CONCAT(DISTINCT fc.codigo ORDER BY fc.codigo SEPARATOR ', ') AS codigo_factura,
            GROUP_CONCAT(DISTINCT fc.idfactura ORDER BY fc.idfactura SEPARATOR ', ') AS id_factura
            
            FROM facturascli fc 
            LEFT JOIN recibospagoscli rpc ON fc.idfactura=rpc.idfactura 
            WHERE  MONTH(fc.fecha)= " . $month ."
            AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
            AND rpc.pagado = 0 
            GROUP BY fc.codcliente, MONTH(fc.fecha)";
        }else{
            $sql = "SELECT  contactos_paises.codigo_pais,               
            fc.nombrecliente, 
            SUM(rpc.importe) AS importe_impago, 
            MONTH(fc.fecha) AS mes, 
            YEAR(fc.fecha) AS anio, 
            GROUP_CONCAT(DISTINCT fc.codigo ORDER BY fc.codigo SEPARATOR ', ') AS codigo_factura,
            GROUP_CONCAT(DISTINCT fc.idfactura ORDER BY fc.idfactura SEPARATOR ', ') AS id_factura
            
            FROM facturascli fc 
            LEFT JOIN recibospagoscli rpc ON fc.idfactura=rpc.idfactura 
            LEFT JOIN " . self::getContactosPaises($dataBase, $codpais, $provincia) . " ON fc.codcliente=contactos_paises.codcliente  
            WHERE  MONTH(fc.fecha)= " . $month ."
            AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
            AND rpc.pagado = 0 
            AND contactos_paises.codigo_pais IS NOT NULL
            GROUP BY fc.codcliente, MONTH(fc.fecha)";
        }
           
        $result = $dataBase->select($sql);
        return (!empty($result[0]['nombrecliente']))? $result: [];
    }

    protected static function selectAllClientPerYearWithUnpaidInvoices(string $codejercicio, $codpais, $provincia): array
    {
        $dataBase = new DataBase();

        if( $codpais == false && $provincia == false){
            $sql = "SELECT                 
            fc.nombrecliente, 
            SUM(rpc.importe) AS importe_impago, 
            MONTH(fc.fecha) AS mes, 
            YEAR(fc.fecha) AS anio, 
            GROUP_CONCAT(DISTINCT fc.codigo ORDER BY fc.codigo SEPARATOR ', ') AS codigo_factura,
            GROUP_CONCAT(DISTINCT fc.idfactura ORDER BY fc.idfactura SEPARATOR ', ') AS id_factura

            FROM facturascli fc 
            LEFT JOIN recibospagoscli rpc ON fc.idfactura=rpc.idfactura         
            WHERE fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
            AND rpc.pagado = 0 
            GROUP BY fc.codcliente
            ORDER BY importe_impago DESC";

        }else{
            $sql = "SELECT                 
            fc.nombrecliente, 
            SUM(rpc.importe) AS importe_impago, 
            MONTH(fc.fecha) AS mes, 
            YEAR(fc.fecha) AS anio, 
            GROUP_CONCAT(DISTINCT fc.codigo ORDER BY fc.codigo SEPARATOR ', ') AS codigo_factura,
            GROUP_CONCAT(DISTINCT fc.idfactura ORDER BY fc.idfactura SEPARATOR ', ') AS id_factura

            FROM facturascli fc 
            LEFT JOIN recibospagoscli rpc ON fc.idfactura=rpc.idfactura 
            LEFT JOIN " . self::getContactosPaises($dataBase, $codpais, $provincia) . " ON fc.codcliente=contactos_paises.codcliente  
            WHERE fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
            AND rpc.pagado = 0 
            AND contactos_paises.codigo_pais IS NOT NULL
            GROUP BY fc.codcliente            
            ORDER BY importe_impago DESC";
        }
    
      

        $result = $dataBase->select($sql);
        return (!empty($result[0]['nombrecliente']))? $result: [];
    }    
    
    protected static function getGroupsClients(): array
    {
        $dataBase = new DataBase();        
        $sql = "SELECT DISTINCT 
                IF(cli.codgrupo IS NULL OR cli.codgrupo = '', '0', cli.codgrupo) AS codigo_grupo, 
                IF(cli.codgrupo IS NULL OR cli.codgrupo = '', 'Sin grupo', g.nombre) AS nombre_grupo
                FROM clientes cli
                LEFT JOIN gruposclientes g ON cli.codgrupo = g.codgrupo";        

        $result = $dataBase->select($sql);
        return $result; 
    }

    
    protected static function invoicingByGroup(array $date, string $codejercicio, ?string $codgrupo): int
    {
        $dataBase = new DataBase();

        $sql = "SELECT SUM(fc.totaleuros) AS total_importe        
            FROM facturascli as fc
            LEFT JOIN clientes cli ON fc.codcliente=cli.codcliente";

        
        if (self::$codpais || self::$provincia) {
            $sql .= " LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON fc.codcliente=contactos_paises.codcliente";
        }

        $sql .= " WHERE fc.fecha >= " . $dataBase->var2str($date['desde']) . "
                AND fc.fecha <= " . $dataBase->var2str($date['hasta']) . "
                AND fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
                AND cli.codgrupo= " . $dataBase->var2str($codgrupo);

        
        if (is_null($codgrupo)) {
            $sql .= " AND (cli.codgrupo IS NULL OR cli.codgrupo = '')";
        } else {
            $sql .= " AND cli.codgrupo = " . $dataBase->var2str($codgrupo);
        }

        
        $conditions = [];

        if (!empty(self::$codpais)) {
            $conditions[] = "FIND_IN_SET(" . $dataBase->var2str(self::$codpais) . ", codigo_pais) > 0";
        }

        if (!empty(self::$provincia)) {
            $conditions[] = "FIND_IN_SET(" . $dataBase->var2str(self::$provincia) . ", provincia_nombre) > 0";
        }

        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(' AND ', $conditions);
        }

        
        $sql .= " AND fc.totaleuros IS NOT NULL HAVING total_importe > 0";

        
        $result = $dataBase->select($sql);
        return !empty($result[0]['total_importe']) ? (int)$result[0]['total_importe'] : 0;



    }

    protected static function invoicingByGroupPerYear(string $codejercicio, ?string $codgrupo): int
    {
        $dataBase = new DataBase();


        
        $sql = "SELECT SUM(fc.totaleuros) AS total_importe                    
                FROM facturascli as fc
                LEFT JOIN clientes cli ON fc.codcliente=cli.codcliente";

        
        if (self::$codpais || self::$provincia) {
            $sql .= " LEFT JOIN " . self::getContactosPaises($dataBase, self::$codpais, self::$provincia) . " ON fc.codcliente=contactos_paises.codcliente";
        }

        
        $sql .= " WHERE fc.codejercicio = " . $dataBase->var2str($codejercicio) . "
                AND cli.codgrupo= " . $dataBase->var2str($codgrupo);


        
        if (is_null($codgrupo)) {
            $sql .= " AND (cli.codgrupo IS NULL OR cli.codgrupo = '')";
        } else {
            $sql .= " AND cli.codgrupo = " . $dataBase->var2str($codgrupo);
        }
        
        
        $conditions = [];
        
        if (!empty(self::$codpais)) {
            $conditions[] = "FIND_IN_SET(" . $dataBase->var2str(self::$codpais) . ", codigo_pais) > 0";
        }

        if (!empty(self::$provincia)) {
            $conditions[] = "FIND_IN_SET(" . $dataBase->var2str(self::$provincia) . ", provincia_nombre) > 0";
        }

        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(' AND ', $conditions);
        }

        
        $sql .= " AND fc.totaleuros IS NOT NULL HAVING total_importe > 0";

        
        $result = $dataBase->select($sql);
        return !empty($result[0]['total_importe']) ? (int)$result[0]['total_importe'] : 0;        

    }
    

}
