<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Core\DataSrc\Agentes;
use FacturaScripts\Core\DataSrc\Almacenes;
use FacturaScripts\Core\DataSrc\Series;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\EstadoDocumento;
use FacturaScripts\Plugins\Informes\Model\Report;
use FacturaScripts\Plugins\Informes\Model\ReportBoard;

class ReportGenerator
{
    /** @var array */
    private static $agent_reports = [];

    /** @var DataBase */
    private static $db;

    /** @var array */
    private static $table_reports = [];

    public static function generate(): int
    {
        $total = 0;

        // creamos los informes
        $tables = ['contactos', 'clientes', 'proveedores', 'facturascli', 'facturasprov', 'albaranescli',
            'albaranesprov', 'pedidoscli', 'pedidosprov', 'presupuestoscli', 'presupuestosprov'];
        foreach ($tables as $table) {
            $total += static::generateTableReport($table);
        }

        // creamos las pizarras
        $total += static::generateBoards();

        return $total;
    }

    protected static function db(): DataBase
    {
        if (null === self::$db) {
            self::$db = new DataBase();
        }

        return self::$db;
    }

    protected static function generateBoards(): int
    {
        $total = 0;

        // ventas mensuales
        $name = Tools::lang()->trans('b-monthly-sales');
        $done = static::generateBoard($name, [
            'r-contactos-new-months', 'r-clientes-new-months', 'r-facturascli-total-months',
            'r-albaranescli-total-months', 'r-pedidoscli-total-months', 'r-presupuestoscli-total-months',
        ]);
        if ($done) {
            $total++;
        }

        // compras mensuales
        $name = Tools::lang()->trans('b-monthly-purchases');
        $done = static::generateBoard($name, [
            'r-proveedores-new-months', 'r-facturasprov-total-months',
            'r-albaranesprov-total-months', 'r-pedidosprov-total-months', 'r-presupuestosprov-total-months',
        ]);
        if ($done) {
            $total++;
        }

        // ventas anuales
        $name = Tools::lang()->trans('b-annual-sales');
        $done = static::generateBoard($name, [
            'r-contactos-new-years', 'r-clientes-new-years',
            'r-contactos-countries', 'r-facturascli-countries',
            'r-facturascli-total-years',
            'r-albaranescli-total-years',
            'r-pedidoscli-total-years',
            'r-presupuestoscli-total-years',
        ]);
        if ($done) {
            $total++;
        }

        // compras anuales
        $name = Tools::lang()->trans('b-annual-purchases');
        $done = static::generateBoard($name, [
            'r-proveedores-new-years',
            'r-facturasprov-total-years',
            'r-albaranesprov-total-years',
            'r-pedidosprov-total-years',
            'r-presupuestosprov-total-years',
        ]);
        if ($done) {
            $total++;
        }

        // pizarras por tabla
        foreach (self::$table_reports as $table_name => $reports) {
            $report_names = [];
            foreach ($reports as $report) {
                $report_names[] = $report->name;
            }

            $name = Tools::lang()->trans('b-' . $table_name);
            $done = static::generateBoard($name, $report_names);
            if ($done) {
                $total++;
            }
        }

        // pizarras por agente
        foreach (self::$agent_reports as $codagente => $reports) {
            $report_names = [];
            foreach ($reports as $report) {
                $report_names[] = $report->name;
            }

            // no reemplazar por Agentes::get() hasta resolver bug con esa función
            $agent = new Agente();
            if (false === $agent->loadFromCode($codagente) || !empty($agent->fechabaja)) {
                continue;
            }

            $name = Tools::lang()->trans('b-agent', ['%name%' => $agent->nombre]);
            $done = static::generateBoard($name, $report_names);
            if ($done) {
                $total++;
            }
        }

        return $total;
    }

    protected static function generateBoard(string $name, array $report_names): bool
    {
        // comprobamos si ya existe la pizarra
        $board = new ReportBoard();
        $where = [new DataBaseWhere('name', $name)];
        if ($board->loadFromCode('', $where)) {
            return false;
        }

        // creamos la pizarra
        $board->name = $name;
        if (false === $board->save()) {
            return false;
        }

        // añadimos los informes
        $pos = 1;
        foreach ($report_names as $r_name) {
            $report = new Report();
            $report_name = Tools::lang()->trans($r_name);
            $whereName = [new DataBaseWhere('name', $report_name)];
            if (false === $report->loadFromCode('', $whereName)) {
                return false;
            }

            if (false === $board->addLine($report, $pos)) {
                return false;
            }

            $pos++;
        }

        return true;
    }

    protected static function generateReportsTotalByAgent(string $table_name): int
    {
        $total = 0;

        // recorre todos los agentes
        foreach (Agentes::all() as $agent) {
            // comprobamos si está dado de baja
            if (!empty($agent->fechabaja)) {
                continue;
            }

            // comprobamos si ya existe el informe
            $report = new Report();
            $name = Tools::lang()->trans('r-' . $table_name . '-total-agent', ['%name%' => $agent->nombre]);
            $where = [new DataBaseWhere('name', $name)];
            if ($report->loadFromCode('', $where)) {
                continue;
            }

            // creamos el informe
            $report->name = $name;
            $report->table = $table_name;
            $report->xcolumn = 'fecha';
            $report->xoperation = 'MONTHS';
            $report->ycolumn = 'total';
            $report->yoperation = 'SUM';
            if (false === $report->save()) {
                break;
            }

            // añadimos el filtro
            $report->addFilter('codagente', '=', $agent->codagente);
            $total++;

            // guardamos el informe para futuras referencias
            self::$agent_reports[$agent->codagente][] = $report;
        }

        return $total;
    }

    protected static function generateReportsTotalBySerie(string $table_name): int
    {
        $total = 0;

        // recorremos todas las series
        foreach (Series::all() as $serie) {
            // comprobamos si ya existe el informe
            $report = new Report();
            $name = Tools::lang()->trans('r-' . $table_name . '-total-serie', ['%serie%' => $serie->descripcion]);
            $where = [new DataBaseWhere('name', $name)];
            if ($report->loadFromCode('', $where)) {
                continue;
            }

            // creamos el informe
            $report->name = $name;
            $report->table = $table_name;
            $report->xcolumn = 'fecha';
            $report->xoperation = 'MONTHS';
            $report->ycolumn = 'total';
            $report->yoperation = 'SUM';
            if (false === $report->save()) {
                break;
            }

            // añadimos el filtro
            $report->addFilter('codserie', '=', $serie->codserie);
            $total++;

            // guardamos el informe para futuras referencias
            self::$table_reports[$table_name][] = $report;
        }

        return $total;
    }

    protected static function generateReportsTotalByWarehouse(string $table_name): int
    {
        $total = 0;

        // si solamente hay un almacén, no es necesario crear informes por almacén
        if (count(Almacenes::all()) <= 1) {
            return $total;
        }

        // recorremos todos los almacenes
        foreach (Almacenes::all() as $warehouse) {
            // comprobamos si ya existe el informe
            $report = new Report();
            $name = Tools::lang()->trans('r-' . $table_name . '-total-warehouse', ['%name%' => $warehouse->nombre]);
            $where = [new DataBaseWhere('name', $name)];
            if ($report->loadFromCode('', $where)) {
                continue;
            }

            // creamos el informe
            $report->name = $name;
            $report->table = $table_name;
            $report->xcolumn = 'fecha';
            $report->xoperation = 'MONTHS';
            $report->ycolumn = 'total';
            $report->yoperation = 'SUM';
            if (false === $report->save()) {
                break;
            }

            // añadimos el filtro
            $report->addFilter('codalmacen', '=', $warehouse->codalmacen);
            $total++;

            // guardamos el informe para futuras referencias
            self::$table_reports[$table_name][] = $report;
        }

        return $total;
    }

    protected static function generateReportsTotalByStatus(string $table_name): int
    {
        $total = 0;

        $type = [
            'albaranescli' => 'AlbaranCliente',
            'albaranesprov' => 'AlbaranProveedor',
            'facturascli' => 'FacturaCliente',
            'facturasprov' => 'FacturaProveedor',
            'pedidoscli' => 'PedidoCliente',
            'pedidosprov' => 'PedidoProveedor',
            'presupuestoscli' => 'PresupuestoCliente',
            'presupuestosprov' => 'PresupuestoProveedor',
        ];
        if (!array_key_exists($table_name, $type)) {
            return $total;
        }

        // recorre todos los estados del tipo
        $where = [new DataBaseWhere('tipodoc', $type[$table_name])];
        foreach (EstadoDocumento::all($where, [], 0, 0) as $status) {
            // comprobamos si ya existe el informe
            $report = new Report();
            $name = Tools::lang()->trans('r-' . $table_name . '-total-status', ['%name%' => $status->nombre]);
            $where = [new DataBaseWhere('name', $name)];
            if ($report->loadFromCode('', $where)) {
                continue;
            }

            // creamos el informe
            $report->name = $name;
            $report->table = $table_name;
            $report->xcolumn = 'fecha';
            $report->xoperation = 'MONTHS';
            $report->ycolumn = 'total';
            $report->yoperation = 'SUM';
            if (false === $report->save()) {
                break;
            }

            // añadimos el filtro
            $report->addFilter('idestado', '=', $status->idestado);
            $total++;

            // guardamos el informe para futuras referencias
            self::$table_reports[$table_name][] = $report;
        }

        return $total;
    }

    protected static function generateReportsCountry(string $table_name): int
    {
        // comprobamos si ya existe el informe
        $report = new Report();
        $name = Tools::lang()->trans('r-' . $table_name . '-countries');
        $where = [new DataBaseWhere('name', $name)];
        if ($report->loadFromCode('', $where)) {
            return 0;
        }

        // creamos el informe
        $report->name = $name;
        $report->table = $table_name;
        $report->type = 'map';
        $report->xcolumn = 'codpais';
        if (false === $report->save()) {
            return 0;
        }

        // guardamos el informe para futuras referencias
        self::$table_reports[$table_name][] = $report;

        return 1;
    }

    protected static function generateReportsFechaAlta(string $table_name): int
    {
        $total = 0;

        // comprobamos si ya existe el informe
        $reportMonths = new Report();
        $name = Tools::lang()->trans('r-' . $table_name . '-new-months');
        $where = [new DataBaseWhere('name', $name)];
        if ($reportMonths->loadFromCode('', $where)) {
            return $total;
        }

        // creamos el informe mensual
        $reportMonths->name = $name;
        $reportMonths->table = $table_name;
        $reportMonths->xcolumn = 'fechaalta';
        $reportMonths->xoperation = 'MONTHS';
        if (false === $reportMonths->save()) {
            return $total;
        }

        $total++;

        // creamos el informe anual
        $reportYears = new Report();
        $reportYears->name = Tools::lang()->trans('r-' . $table_name . '-new-years');
        $reportYears->table = $table_name;
        $reportYears->xcolumn = 'fechaalta';
        $reportYears->xoperation = 'YEAR';
        if (false === $reportYears->save()) {
            return $total;
        }

        $total++;

        // guardamos los informes para futuras referencias
        self::$table_reports[$table_name][] = $reportYears;
        self::$table_reports[$table_name][] = $reportMonths;

        return $total;
    }

    protected static function generateReportsFechaTotal(string $table_name): int
    {
        $total = 0;

        // comprobamos si ya existe el informe
        $reportMonths = new Report();
        $name = Tools::lang()->trans('r-' . $table_name . '-total-months');
        $where = [new DataBaseWhere('name', $name)];
        if ($reportMonths->loadFromCode('', $where)) {
            return $total;
        }

        // creamos el informe mensual
        $reportMonths->name = $name;
        $reportMonths->table = $table_name;
        $reportMonths->xcolumn = 'fecha';
        $reportMonths->xoperation = 'MONTHS';
        $reportMonths->ycolumn = 'total';
        $reportMonths->yoperation = 'SUM';
        if (false === $reportMonths->save()) {
            return $total;
        }

        $total++;

        // creamos el informe anual
        $reportYears = new Report();
        $reportYears->name = Tools::lang()->trans('r-' . $table_name . '-total-years');
        $reportYears->table = $table_name;
        $reportYears->xcolumn = 'fecha';
        $reportYears->xoperation = 'YEAR';
        $reportYears->ycolumn = 'total';
        $reportYears->yoperation = 'SUM';
        if (false === $reportYears->save()) {
            return $total;
        }

        $total++;

        // guardamos los informes para futuras referencias
        self::$table_reports[$table_name][] = $reportYears;
        self::$table_reports[$table_name][] = $reportMonths;

        return $total;
    }

    protected static function generateTableReport(string $name): int
    {
        $total = 0;

        // comprobamos la tabla
        $columns = [];
        foreach (static::db()->getColumns($name) as $column) {
            $columns[] = $column['name'];
        }
        if (empty($columns)) {
            return $total;
        }

        if (in_array('fechaalta', $columns)) {
            $total += static::generateReportsFechaAlta($name);
        }

        if (in_array('fecha', $columns) && in_array('total', $columns)) {
            $total += static::generateReportsFechaTotal($name);
        }

        if (in_array('codalmacen', $columns) &&
            in_array('fecha', $columns) &&
            in_array('total', $columns)) {
            $total += static::generateReportsTotalByWarehouse($name);
        }

        if (in_array('codserie', $columns) &&
            in_array('fecha', $columns) &&
            in_array('total', $columns)) {
            $total += static::generateReportsTotalBySerie($name);
        }

        if (in_array('idestado', $columns) &&
            in_array('fecha', $columns) &&
            in_array('total', $columns)) {
            $total += static::generateReportsTotalByStatus($name);
        }

        if (in_array('codagente', $columns) &&
            in_array('fecha', $columns) &&
            in_array('total', $columns)) {
            $total += static::generateReportsTotalByAgent($name);
        }

        if (in_array('codpais', $columns)) {
            $total += static::generateReportsCountry($name);
        }

        return $total;
    }
}
