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
use FacturaScripts\Core\DataSrc\Almacenes;
use FacturaScripts\Core\DataSrc\Series;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\EstadoDocumento;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\Informes\Model\Report;
use FacturaScripts\Plugins\Informes\Model\ReportBoard;

class ReportGenerator
{
    /** @var Report[] */
    private static $agent_reports = [];

    /** @var Report[] */
    private static $customer_reports = [];

    /** @var DataBase */
    private static $db;

    /** @var Report[] */
    private static $supplier_reports = [];

    /** @var Report[] */
    private static $table_reports = [];

    /** @var Report[] */
    private static $user_reports = [];

    public static function generate(): int
    {
        $total = 0;

        // creamos los informes
        $tables = ['contactos', 'clientes', 'facturascli', 'albaranescli', 'pedidoscli', 'presupuestoscli', 'serviciosat',
            'proveedores', 'facturasprov', 'albaranesprov', 'pedidosprov', 'presupuestosprov'];
        foreach ($tables as $table) {
            $total += static::generateTableReport($table);
        }

        // creamos las pizarras
        $total += static::generateBoards();

        return $total;
    }

    public static function generateForAgent(string $codagente): int
    {
        $total = 0;

        // comprobamos si el agente existe
        $agent = new Agente();
        if (false === $agent->loadFromCode($codagente)) {
            return $total;
        }

        // creamos los informes
        $tables = ['facturascli', 'albaranescli', 'pedidoscli', 'presupuestoscli'];
        foreach ($tables as $table) {
            $total += static::generateReportsTotalByAgent($table, $agent);
        }

        // creamos la pizarra
        $report_tags = [];
        if (array_key_exists($codagente, self::$agent_reports)) {
            foreach (self::$agent_reports[$codagente] as $report) {
                $report_tags[] = $report->tag;
            }
        }

        $name = Tools::lang()->trans('b-agent', ['%name%' => $agent->nombre]);
        $tag = 'b-agent-' . $codagente;
        $done = static::generateBoard($name, $tag, $report_tags);
        if ($done) {
            $total++;
        }

        return $total;
    }

    public static function generateForCustomer(string $codcliente): int
    {
        $total = 0;

        // comprobamos si el cliente existe
        $customer = new Cliente();
        if (false === $customer->loadFromCode($codcliente)) {
            return $total;
        }

        // creamos los informes
        $tables = ['facturascli', 'albaranescli', 'pedidoscli', 'presupuestoscli'];
        foreach ($tables as $table) {
            $total += static::generateReportsTotalByCustomer($table, $customer);
        }

        // creamos la pizarra
        $report_tags = [];
        if (array_key_exists($codcliente, self::$customer_reports)) {
            foreach (self::$customer_reports[$codcliente] as $report) {
                $report_tags[] = $report->tag;
            }
        }

        $name = Tools::lang()->trans('b-customer', ['%name%' => $customer->nombre]);
        $tag = 'b-customer-' . $codcliente;
        $done = static::generateBoard($name, $tag, $report_tags);
        if ($done) {
            $total++;
        }

        return $total;
    }

    public static function generateForSupplier(string $codproveedor): int
    {
        $total = 0;

        // comprobamos si el proveedor existe
        $supplier = new Proveedor();
        if (false === $supplier->loadFromCode($codproveedor)) {
            return $total;
        }

        // creamos los informes
        $tables = ['facturasprov', 'albaranesprov', 'pedidosprov', 'presupuestosprov'];
        foreach ($tables as $table) {
            $total += static::generateReportsTotalBySupplier($table, $supplier);
        }

        // creamos la pizarra
        $report_tags = [];
        if (array_key_exists($codproveedor, self::$supplier_reports)) {
            foreach (self::$supplier_reports[$codproveedor] as $report) {
                $report_tags[] = $report->tag;
            }
        }

        $name = Tools::lang()->trans('b-supplier', ['%name%' => $supplier->nombre]);
        $tag = 'b-supplier-' . $codproveedor;
        $done = static::generateBoard($name, $tag, $report_tags);
        if ($done) {
            $total++;
        }

        return $total;
    }

    public static function generateForUser(string $username): int
    {
        $total = 0;

        // comprobamos si el usuario existe
        $user = new User();
        if (false === $user->loadFromCode($username)) {
            return $total;
        }

        // creamos los informes
        $tables = ['facturascli', 'albaranescli', 'pedidoscli', 'presupuestoscli', 'facturasprov', 'albaranesprov',
            'pedidosprov', 'presupuestosprov'];
        foreach ($tables as $table) {
            $total += static::generateReportsTotalByUser($table, $user);
        }

        // creamos la pizarra
        $report_tags = [];
        if (array_key_exists($username, self::$user_reports)) {
            foreach (self::$user_reports[$username] as $report) {
                $report_tags[] = $report->tag;
            }
        }

        $name = Tools::lang()->trans('b-user', ['%name%' => $user->nick]);
        $tag = 'b-user-' . $username;
        $done = static::generateBoard($name, $tag, $report_tags);
        if ($done) {
            $total++;
        }

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

        // pizarras por tabla
        foreach (self::$table_reports as $table_name => $reports) {
            $report_tags = [];
            foreach ($reports as $report) {
                $report_tags[] = $report->tag;
            }

            $name = Tools::lang()->trans('b-' . $table_name);
            $tag = 'b-' . $table_name;
            $done = static::generateBoard($name, $tag, $report_tags);
            if ($done) {
                $total++;
            }
        }

        // ventas anuales
        $name = Tools::lang()->trans('b-annual-sales');
        $tag = 'b-annual-sales';
        $done = static::generateBoard(
            $name,
            $tag, ['r-contactos-new-years', 'r-clientes-new-years', 'r-contactos-countries', 'r-facturascli-countries',
            'r-facturascli-total-years', 'r-albaranescli-total-years', 'r-pedidoscli-total-years',
            'r-presupuestoscli-total-years'],
            true
        );
        if ($done) {
            $total++;
        }

        // ventas mensuales
        $name = Tools::lang()->trans('b-monthly-sales');
        $tag = 'b-monthly-sales';
        $done = static::generateBoard(
            $name,
            $tag,
            ['r-contactos-new-months', 'r-clientes-new-months', 'r-facturascli-total-months',
                'r-albaranescli-total-months', 'r-pedidoscli-total-months', 'r-presupuestoscli-total-months'],
            true
        );
        if ($done) {
            $total++;
        }

        // compras anuales
        $name = Tools::lang()->trans('b-annual-purchases');
        $tag = 'b-annual-purchases';
        $done = static::generateBoard(
            $name,
            $tag, ['r-proveedores-new-years', 'r-facturasprov-total-years', 'r-albaranesprov-total-years',
            'r-pedidosprov-total-years', 'r-presupuestosprov-total-years'],
            true
        );
        if ($done) {
            $total++;
        }

        // compras mensuales
        $name = Tools::lang()->trans('b-monthly-purchases');
        $tag = 'b-monthly-purchases';
        $done = static::generateBoard(
            $name,
            $tag,
            ['r-proveedores-new-months', 'r-facturasprov-total-months', 'r-albaranesprov-total-months',
                'r-pedidosprov-total-months', 'r-presupuestosprov-total-months'],
            true
        );
        if ($done) {
            $total++;
        }

        return $total;
    }

    protected static function generateBoard(string $name, string $tag, array $report_tags, bool $featured = false): bool
    {
        // comprobamos si ya existe la pizarra
        $board = new ReportBoard();
        $where = [new DataBaseWhere('tag', $tag)];
        if ($board->loadFromCode('', $where)) {
            return false;
        }

        // creamos la pizarra
        $board->featured = $featured;
        $board->name = $name;
        $board->tag = $tag;
        if (false === $board->save()) {
            return false;
        }

        // añadimos los informes
        $pos = 1;
        foreach ($report_tags as $r_tag) {
            $report = new Report();
            $whereTag = [new DataBaseWhere('tag', $r_tag)];
            if (false === $report->loadFromCode('', $whereTag)) {
                return false;
            }

            if (false === $board->addLine($report, $pos)) {
                return false;
            }

            $pos++;
        }

        return true;
    }

    protected static function generateReportsTotalByAgent(string $table_name, Agente $agent): int
    {
        $total = 0;

        // comprobamos si la tabla existe
        if (!self::db()->tableExists($table_name)) {
            return $total;
        }

        // comprobamos si ya existe el informe mensual
        $report = new Report();
        $tag = 'r-' . $table_name . '-total-agent-' . $agent->codagente;
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $report->loadFromCode('', $where)) {
            // creamos el informe
            $report->name = Tools::lang()->trans('r-' . $table_name . '-total-agent', ['%name%' => $agent->nombre]);
            $report->table = $table_name;
            $report->tag = $tag;
            $report->xcolumn = 'fecha';
            $report->xoperation = 'MONTHS';
            $report->ycolumn = 'total';
            $report->yoperation = 'SUM';
            if ($report->save()) {
                // añadimos el filtro
                $report->addFilter('codagente', '=', $agent->codagente);
                $total++;

                // guardamos el informe para futuras referencias
                self::$agent_reports[$agent->codagente][] = $report;
            }
        }

        // comprobamos si ya existe el informe anual
        $reportYear = new Report();
        $tag = 'r-' . $table_name . '-total-agent-' . $agent->codagente . '-year';
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $reportYear->loadFromCode('', $where)) {
            // creamos el informe
            $reportYear->name = Tools::lang()->trans('r-' . $table_name . '-total-agent-year', ['%name%' => $agent->nombre]);
            $reportYear->table = $table_name;
            $reportYear->tag = $tag;
            $reportYear->type = Report::TYPE_BAR;
            $reportYear->xcolumn = 'fecha';
            $reportYear->xoperation = 'YEAR';
            $reportYear->ycolumn = 'total';
            $reportYear->yoperation = 'SUM';
            if ($reportYear->save()) {
                // añadimos el filtro
                $reportYear->addFilter('codagente', '=', $agent->codagente);
                $total++;

                // guardamos el informe para futuras referencias
                self::$agent_reports[$agent->codagente][] = $reportYear;
            }
        }

        return $total;
    }

    protected static function generateReportsTotalByCustomer(string $table_name, Cliente $customer): int
    {
        $total = 0;

        // comprobamos si la tabla existe
        if (!self::db()->tableExists($table_name)) {
            return $total;
        }

        // comprobamos si ya existe el informe mensual
        $report = new Report();
        $tag = 'r-' . $table_name . '-total-customer-' . $customer->codcliente;
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $report->loadFromCode('', $where)) {
            // creamos el informe
            $report->name = Tools::lang()->trans('r-' . $table_name . '-total-customer', ['%name%' => $customer->nombre]);
            $report->table = $table_name;
            $report->tag = $tag;
            $report->xcolumn = 'fecha';
            $report->xoperation = 'MONTHS';
            $report->ycolumn = 'total';
            $report->yoperation = 'SUM';
            if ($report->save()) {
                // añadimos el filtro
                $report->addFilter('codcliente', '=', $customer->codcliente);
                $total++;

                // guardamos el informe para futuras referencias
                self::$customer_reports[$customer->codcliente][] = $report;
            }
        }

        // comprobamos si ya existe el informe anual
        $reportYear = new Report();
        $tag = 'r-' . $table_name . '-total-customer-' . $customer->codcliente . '-year';
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $reportYear->loadFromCode('', $where)) {
            // creamos el informe
            $reportYear->name = Tools::lang()->trans('r-' . $table_name . '-total-customer-year', ['%name%' => $customer->nombre]);
            $reportYear->table = $table_name;
            $reportYear->tag = $tag;
            $reportYear->type = Report::TYPE_BAR;
            $reportYear->xcolumn = 'fecha';
            $reportYear->xoperation = 'YEAR';
            $reportYear->ycolumn = 'total';
            $reportYear->yoperation = 'SUM';
            if ($reportYear->save()) {
                // añadimos el filtro
                $reportYear->addFilter('codcliente', '=', $customer->codcliente);
                $total++;

                // guardamos el informe para futuras referencias
                self::$customer_reports[$customer->codcliente][] = $reportYear;
            }
        }

        return $total;
    }

    protected static function generateReportsTotalBySerie(string $table_name): int
    {
        $total = 0;

        // recorremos todas las series
        foreach (Series::all() as $serie) {
            // comprobamos si ya existe el informe mensual
            $report = new Report();
            $tag = 'r-' . $table_name . '-total-serie-' . $serie->codserie;
            $where = [new DataBaseWhere('tag', $tag)];
            if (false === $report->loadFromCode('', $where)) {
                // creamos el informe
                $report->name = Tools::lang()->trans('r-' . $table_name . '-total-serie', ['%serie%' => $serie->descripcion]);
                $report->table = $table_name;
                $report->tag = $tag;
                $report->xcolumn = 'fecha';
                $report->xoperation = 'MONTHS';
                $report->ycolumn = 'total';
                $report->yoperation = 'SUM';
                if ($report->save()) {
                    // añadimos el filtro
                    $report->addFilter('codserie', '=', $serie->codserie);
                    $total++;

                    // guardamos el informe para futuras referencias
                    self::$table_reports[$table_name][] = $report;
                }
            }

            // comprobamos si ya existe el informe anual
            $reportYear = new Report();
            $tag = 'r-' . $table_name . '-total-serie-' . $serie->codserie . '-year';
            $where = [new DataBaseWhere('tag', $tag)];
            if (false === $reportYear->loadFromCode('', $where)) {
                // creamos el informe
                $reportYear->name = Tools::lang()->trans('r-' . $table_name . '-total-serie-year', ['%serie%' => $serie->descripcion]);
                $reportYear->table = $table_name;
                $reportYear->tag = $tag;
                $reportYear->type = Report::TYPE_BAR;
                $reportYear->xcolumn = 'fecha';
                $reportYear->xoperation = 'YEAR';
                $reportYear->ycolumn = 'total';
                $reportYear->yoperation = 'SUM';
                if ($reportYear->save()) {
                    // añadimos el filtro
                    $reportYear->addFilter('codserie', '=', $serie->codserie);
                    $total++;

                    // guardamos el informe para futuras referencias
                    self::$table_reports[$table_name][] = $reportYear;
                }
            }
        }

        return $total;
    }

    protected static function generateReportsTotalBySupplier(string $table_name, Proveedor $supplier): int
    {
        $total = 0;

        // comprobamos si la tabla existe
        if (!self::db()->tableExists($table_name)) {
            return $total;
        }

        // comprobamos si ya existe el informe mensual
        $report = new Report();
        $tag = 'r-' . $table_name . '-total-supplier-' . $supplier->codproveedor;
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $report->loadFromCode('', $where)) {
            // creamos el informe
            $report->name = Tools::lang()->trans('r-' . $table_name . '-total-supplier', ['%name%' => $supplier->nombre]);
            $report->table = $table_name;
            $report->tag = $tag;
            $report->xcolumn = 'fecha';
            $report->xoperation = 'MONTHS';
            $report->ycolumn = 'total';
            $report->yoperation = 'SUM';
            if ($report->save()) {
                // añadimos el filtro
                $report->addFilter('codproveedor', '=', $supplier->codproveedor);
                $total++;

                // guardamos el informe para futuras referencias
                self::$supplier_reports[$supplier->codproveedor][] = $report;
            }
        }

        // comprobamos si ya existe el informe anual
        $reportYear = new Report();
        $tag = 'r-' . $table_name . '-total-supplier-' . $supplier->codproveedor . '-year';
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $reportYear->loadFromCode('', $where)) {
            // creamos el informe
            $reportYear->name = Tools::lang()->trans('r-' . $table_name . '-total-supplier-year', ['%name%' => $supplier->nombre]);
            $reportYear->table = $table_name;
            $reportYear->tag = $tag;
            $reportYear->type = Report::TYPE_BAR;
            $reportYear->xcolumn = 'fecha';
            $reportYear->xoperation = 'YEAR';
            $reportYear->ycolumn = 'total';
            $reportYear->yoperation = 'SUM';
            if ($reportYear->save()) {
                // añadimos el filtro
                $reportYear->addFilter('codproveedor', '=', $supplier->codproveedor);
                $total++;

                // guardamos el informe para futuras referencias
                self::$supplier_reports[$supplier->codproveedor][] = $reportYear;
            }
        }

        return $total;
    }

    protected static function generateReportsTotalByUser(string $table_name, User $user): int
    {
        $total = 0;

        // comprobamos si la tabla existe
        if (!self::db()->tableExists($table_name)) {
            return $total;
        }

        // comprobamos si ya existe el informe mensual
        $report = new Report();
        $tag = 'r-' . $table_name . '-total-user-' . $user->nick;
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $report->loadFromCode('', $where)) {
            // creamos el informe
            $report->name = Tools::lang()->trans('r-' . $table_name . '-total-user', ['%name%' => $user->nick]);
            $report->table = $table_name;
            $report->tag = $tag;
            $report->xcolumn = 'fecha';
            $report->xoperation = 'MONTHS';
            $report->ycolumn = 'total';
            $report->yoperation = 'SUM';
            if ($report->save()) {
                // añadimos el filtro
                $report->addFilter('nick', '=', $user->nick);
                $total++;

                // guardamos el informe para futuras referencias
                self::$user_reports[$user->nick][] = $report;
            }
        }

        // comprobamos si ya existe el informe anual
        $reportYear = new Report();
        $tag = 'r-' . $table_name . '-total-user-' . $user->nick . '-year';
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $reportYear->loadFromCode('', $where)) {
            // creamos el informe
            $reportYear->name = Tools::lang()->trans('r-' . $table_name . '-total-user-year', ['%name%' => $user->nick]);
            $reportYear->table = $table_name;
            $reportYear->tag = $tag;
            $reportYear->type = Report::TYPE_BAR;
            $reportYear->xcolumn = 'fecha';
            $reportYear->xoperation = 'YEAR';
            $reportYear->ycolumn = 'total';
            $reportYear->yoperation = 'SUM';
            if ($reportYear->save()) {
                // añadimos el filtro
                $reportYear->addFilter('nick', '=', $user->nick);
                $total++;

                // guardamos el informe para futuras referencias
                self::$user_reports[$user->nick][] = $reportYear;
            }
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
            // comprobamos si ya existe el informe mensual
            $report = new Report();
            $tag = 'r-' . $table_name . '-total-warehouse-' . $warehouse->codalmacen;
            $where = [new DataBaseWhere('tag', $tag)];
            if (false === $report->loadFromCode('', $where)) {
                // creamos el informe
                $report->name = Tools::lang()->trans('r-' . $table_name . '-total-warehouse', ['%name%' => $warehouse->nombre]);
                $report->table = $table_name;
                $report->tag = $tag;
                $report->xcolumn = 'fecha';
                $report->xoperation = 'MONTHS';
                $report->ycolumn = 'total';
                $report->yoperation = 'SUM';
                if ($report->save()) {
                    // añadimos el filtro
                    $report->addFilter('codalmacen', '=', $warehouse->codalmacen);
                    $total++;

                    // guardamos el informe para futuras referencias
                    self::$table_reports[$table_name][] = $report;
                }
            }

            // comprobamos si ya existe el informe anual
            $reportYear = new Report();
            $tag = 'r-' . $table_name . '-total-warehouse-' . $warehouse->codalmacen . '-year';
            $where = [new DataBaseWhere('tag', $tag)];
            if (false === $reportYear->loadFromCode('', $where)) {
                // creamos el informe
                $reportYear->name = Tools::lang()->trans('r-' . $table_name . '-total-warehouse-year', ['%name%' => $warehouse->nombre]);
                $reportYear->table = $table_name;
                $reportYear->tag = $tag;
                $reportYear->type = Report::TYPE_BAR;
                $reportYear->xcolumn = 'fecha';
                $reportYear->xoperation = 'YEAR';
                $reportYear->ycolumn = 'total';
                $reportYear->yoperation = 'SUM';
                if ($reportYear->save()) {
                    // añadimos el filtro
                    $reportYear->addFilter('codalmacen', '=', $warehouse->codalmacen);
                    $total++;

                    // guardamos el informe para futuras referencias
                    self::$table_reports[$table_name][] = $reportYear;
                }
            }
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
            // comprobamos si ya existe el informe mensual
            $report = new Report();
            $tag = 'r-' . $table_name . '-total-status-' . $status->idestado;
            $where = [new DataBaseWhere('tag', $tag)];
            if (false === $report->loadFromCode('', $where)) {
                // creamos el informe
                $report->name = Tools::lang()->trans('r-' . $table_name . '-total-status', ['%name%' => $status->nombre]);
                $report->table = $table_name;
                $report->tag = $tag;
                $report->xcolumn = 'fecha';
                $report->xoperation = 'MONTHS';
                $report->ycolumn = 'total';
                $report->yoperation = 'SUM';
                if ($report->save()) {
                    // añadimos el filtro
                    $report->addFilter('idestado', '=', $status->idestado);
                    $total++;

                    // guardamos el informe para futuras referencias
                    self::$table_reports[$table_name][] = $report;
                }
            }

            // comprobamos si ya existe el informe anual
            $reportYear = new Report();
            $tag = 'r-' . $table_name . '-total-status-' . $status->idestado . '-year';
            $where = [new DataBaseWhere('tag', $tag)];
            if (false === $reportYear->loadFromCode('', $where)) {
                // creamos el informe
                $reportYear->name = Tools::lang()->trans('r-' . $table_name . '-total-status-year', ['%name%' => $status->nombre]);
                $reportYear->table = $table_name;
                $reportYear->tag = $tag;
                $reportYear->type = Report::TYPE_BAR;
                $reportYear->xcolumn = 'fecha';
                $reportYear->xoperation = 'YEAR';
                $reportYear->ycolumn = 'total';
                $reportYear->yoperation = 'SUM';
                if ($reportYear->save()) {
                    // añadimos el filtro
                    $reportYear->addFilter('idestado', '=', $status->idestado);
                    $total++;

                    // guardamos el informe para futuras referencias
                    self::$table_reports[$table_name][] = $reportYear;
                }
            }
        }

        return $total;
    }

    protected static function generateReportsCountry(string $table_name): int
    {
        // comprobamos si ya existe el informe
        $report = new Report();
        $tag = 'r-' . $table_name . '-countries';
        $where = [new DataBaseWhere('tag', $tag)];
        if ($report->loadFromCode('', $where)) {
            return 0;
        }

        // creamos el informe
        $report->name = Tools::lang()->trans('r-' . $table_name . '-countries');
        $report->table = $table_name;
        $report->tag = $tag;
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

        // comprobamos si ya existe el informe mensual
        $reportMonths = new Report();
        $tag = 'r-' . $table_name . '-new-months';
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $reportMonths->loadFromCode('', $where)) {
            // creamos el informe mensual
            $reportMonths->name = Tools::lang()->trans('r-' . $table_name . '-new-months');
            $reportMonths->table = $table_name;
            $reportMonths->tag = $tag;
            $reportMonths->xcolumn = 'fechaalta';
            $reportMonths->xoperation = 'MONTHS';
            if ($reportMonths->save()) {
                $total++;

                // guardamos los informes para futuras referencias
                self::$table_reports[$table_name][] = $reportMonths;
            }
        }

        // comprobamos si ya existe el informe anual
        $reportYears = new Report();
        $tag = 'r-' . $table_name . '-new-years';
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $reportYears->loadFromCode('', $where)) {
            // creamos el informe anual
            $reportYears->name = Tools::lang()->trans('r-' . $table_name . '-new-years');
            $reportYears->table = $table_name;
            $reportYears->tag = 'r-' . $table_name . '-new-years';
            $reportYears->type = Report::TYPE_BAR;
            $reportYears->xcolumn = 'fechaalta';
            $reportYears->xoperation = 'YEAR';
            if ($reportYears->save()) {
                $total++;

                // guardamos los informes para futuras referencias
                self::$table_reports[$table_name][] = $reportYears;
            }
        }

        return $total;
    }

    protected static function generateReportsFechaTotal(string $table_name): int
    {
        $total = 0;

        // comprobamos si ya existe el informe mensual
        $reportMonths = new Report();
        $tag = 'r-' . $table_name . '-total-months';
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $reportMonths->loadFromCode('', $where)) {
            // creamos el informe mensual
            $reportMonths->name = Tools::lang()->trans('r-' . $table_name . '-total-months');
            $reportMonths->table = $table_name;
            $reportMonths->tag = $tag;
            $reportMonths->xcolumn = 'fecha';
            $reportMonths->xoperation = 'MONTHS';
            $reportMonths->ycolumn = 'total';
            $reportMonths->yoperation = 'SUM';
            if ($reportMonths->save()) {
                $total++;

                // guardamos los informes para futuras referencias
                self::$table_reports[$table_name][] = $reportMonths;
            }
        }

        // comprobamos si ya existe el informe anual
        $reportYears = new Report();
        $tag = 'r-' . $table_name . '-total-years';
        $where = [new DataBaseWhere('tag', $tag)];
        if (false === $reportYears->loadFromCode('', $where)) {
            // creamos el informe anual
            $reportYears->name = Tools::lang()->trans('r-' . $table_name . '-total-years');
            $reportYears->table = $table_name;
            $reportYears->tag = 'r-' . $table_name . '-total-years';
            $reportYears->type = Report::TYPE_BAR;
            $reportYears->xcolumn = 'fecha';
            $reportYears->xoperation = 'YEAR';
            $reportYears->ycolumn = 'total';
            $reportYears->yoperation = 'SUM';
            if ($reportYears->save()) {
                $total++;

                // guardamos los informes para futuras referencias
                self::$table_reports[$table_name][] = $reportYears;
            }
        }

        return $total;
    }

    protected static function generateTableReport(string $name): int
    {
        $total = 0;

        // comprobamos la tabla
        if (false === static::db()->tableExists($name)) {
            return $total;
        }
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

        if (in_array('codpais', $columns)) {
            $total += static::generateReportsCountry($name);
        }

        return $total;
    }
}
