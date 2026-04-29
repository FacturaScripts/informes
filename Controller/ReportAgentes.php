<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Plugins\Informes\Model\Report;

class ReportAgentes extends Controller
{
    /** @var array lista de agentes [codagente => nombre] */
    public $agents = [];

    /** @var Report gráfica de albaranes por agente */
    public $albaranes;

    /** @var Report gráfica de comisiones por mes */
    public $comisionesByMonth;

    /** @var Report gráfica de comisiones por año */
    public $comisionesByYear;

    /** @var bool indica si el plugin Comisiones está activo */
    public $comisionesEnabled = false;

    /** @var Report gráfica de liquidaciones por mes */
    public $liquidacionesByMonth;

    /** @var Report gráfica de liquidaciones por año */
    public $liquidacionesByYear;

    /** @var Report gráfica de albaranes por mes (último año) */
    public $albaranesByMonth;

    /** @var Report gráfica de albaranes por año */
    public $albaranesByYear;

    /** @var Report gráfica de facturas por agente */
    public $facturas;

    /** @var Report gráfica de facturas por mes (último año) */
    public $facturasByMonth;

    /** @var Report gráfica de facturas por año */
    public $facturasByYear;

    /** @var Report gráfica de pedidos por agente */
    public $pedidos;

    /** @var Report gráfica de pedidos por mes (último año) */
    public $pedidosByMonth;

    /** @var Report gráfica de pedidos por año */
    public $pedidosByYear;

    /** @var Report gráfica de presupuestos por agente */
    public $presupuestos;

    /** @var Report gráfica de presupuestos por mes (último año) */
    public $presupuestosByMonth;

    /** @var Report gráfica de presupuestos por año */
    public $presupuestosByYear;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'agents-report';
        $data['icon'] = 'fa-solid fa-user-tie';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->comisionesEnabled = Plugins::isEnabled('Comisiones');

        $this->loadAgentes();
        $this->loadAlbaranes();
        $this->loadAlbaranesByMonth();
        $this->loadAlbaranesByYear();
        $this->loadFacturas();
        $this->loadFacturasByMonth();
        $this->loadFacturasByYear();
        $this->loadPedidos();
        $this->loadPedidosByMonth();
        $this->loadPedidosByYear();
        $this->loadPresupuestos();
        $this->loadPresupuestosByMonth();
        $this->loadPresupuestosByYear();

        if ($this->comisionesEnabled) {
            $this->loadComisionesByMonth();
            $this->loadComisionesByYear();
            $this->loadLiquidacionesByMonth();
            $this->loadLiquidacionesByYear();
        }
    }

    protected function loadAgentes(): void
    {
        $rows = $this->dataBase->select("SELECT codagente, nombre FROM agentes WHERE debaja = false ORDER BY nombre ASC");
        foreach ($rows as $row) {
            $this->agents[$row['codagente']] = $row['nombre'];
        }
    }

    protected function loadAlbaranes(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_PIE;
        $report->table = 'albaranescli f';
        $report->xcolumn = 'COALESCE(a.nombre, f.codagente)';
        $report->ycolumn = '*';
        $report->yoperation = 'COUNT';

        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN agentes a ON f.codagente = a.codagente');
        $report->addCustomFilter('f.codagente', 'IS NOT NULL', '');

        $this->albaranes = $report;
    }

    protected function loadAlbaranesByMonth(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'albaranescli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'idalbaran';
        $report->xoperation = 'MONTHS';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');
        $report->addCustomFilter('fecha', '>=', '{-1 year}');
        $report->addCustomFilter('fecha', '<=', '{today}');

        $this->albaranesByMonth = $report;
    }

    protected function loadAlbaranesByYear(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'albaranescli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'idalbaran';
        $report->xoperation = 'YEAR';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');

        $this->albaranesByYear = $report;
    }

    protected function loadComisionesByMonth(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'facturascli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'totalcomision';
        $report->xoperation = 'MONTHS';
        $report->yoperation = 'SUM';
        $report->addFieldXName('');
        $report->addCustomFilter('fecha', '>=', '{-1 year}');
        $report->addCustomFilter('fecha', '<=', '{today}');
        $report->addCustomFilter('codagente', 'IS NOT NULL', '');

        $this->comisionesByMonth = $report;
    }

    protected function loadComisionesByYear(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'facturascli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'totalcomision';
        $report->xoperation = 'YEAR';
        $report->yoperation = 'SUM';
        $report->addFieldXName('');
        $report->addCustomFilter('codagente', 'IS NOT NULL', '');

        $this->comisionesByYear = $report;
    }

    protected function loadLiquidacionesByMonth(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'liquidacionescomisiones';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'total';
        $report->xoperation = 'MONTHS';
        $report->yoperation = 'SUM';
        $report->addFieldXName('');
        $report->addCustomFilter('fecha', '>=', '{-1 year}');
        $report->addCustomFilter('fecha', '<=', '{today}');

        $this->liquidacionesByMonth = $report;
    }

    protected function loadLiquidacionesByYear(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'liquidacionescomisiones';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'total';
        $report->xoperation = 'YEAR';
        $report->yoperation = 'SUM';
        $report->addFieldXName('');

        $this->liquidacionesByYear = $report;
    }

    protected function loadFacturas(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_PIE;
        $report->table = 'facturascli f';
        $report->xcolumn = 'COALESCE(a.nombre, f.codagente)';
        $report->ycolumn = '*';
        $report->yoperation = 'COUNT';

        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN agentes a ON f.codagente = a.codagente');
        $report->addCustomFilter('f.codagente', 'IS NOT NULL', '');

        $this->facturas = $report;
    }

    protected function loadFacturasByMonth(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'facturascli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'idfactura';
        $report->xoperation = 'MONTHS';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');
        $report->addCustomFilter('fecha', '>=', '{-1 year}');
        $report->addCustomFilter('fecha', '<=', '{today}');

        $this->facturasByMonth = $report;
    }

    protected function loadFacturasByYear(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'facturascli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'idfactura';
        $report->xoperation = 'YEAR';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');

        $this->facturasByYear = $report;
    }

    protected function loadPedidos(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_PIE;
        $report->table = 'pedidoscli f';
        $report->xcolumn = 'COALESCE(a.nombre, f.codagente)';
        $report->ycolumn = '*';
        $report->yoperation = 'COUNT';

        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN agentes a ON f.codagente = a.codagente');
        $report->addCustomFilter('f.codagente', 'IS NOT NULL', '');

        $this->pedidos = $report;
    }

    protected function loadPedidosByMonth(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'pedidoscli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'idpedido';
        $report->xoperation = 'MONTHS';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');
        $report->addCustomFilter('fecha', '>=', '{-1 year}');
        $report->addCustomFilter('fecha', '<=', '{today}');

        $this->pedidosByMonth = $report;
    }

    protected function loadPedidosByYear(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'pedidoscli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'idpedido';
        $report->xoperation = 'YEAR';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');

        $this->pedidosByYear = $report;
    }

    protected function loadPresupuestos(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_PIE;
        $report->table = 'presupuestoscli f';
        $report->xcolumn = 'COALESCE(a.nombre, f.codagente)';
        $report->ycolumn = '*';
        $report->yoperation = 'COUNT';

        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN agentes a ON f.codagente = a.codagente');
        $report->addCustomFilter('f.codagente', 'IS NOT NULL', '');

        $this->presupuestos = $report;
    }

    protected function loadPresupuestosByMonth(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'presupuestoscli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'idpresupuesto';
        $report->xoperation = 'MONTHS';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');
        $report->addCustomFilter('fecha', '>=', '{-1 year}');
        $report->addCustomFilter('fecha', '<=', '{today}');

        $this->presupuestosByMonth = $report;
    }

    protected function loadPresupuestosByYear(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'presupuestoscli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'idpresupuesto';
        $report->xoperation = 'YEAR';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');

        $this->presupuestosByYear = $report;
    }
}
