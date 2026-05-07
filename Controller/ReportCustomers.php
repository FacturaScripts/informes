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

use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Plugins\Informes\Model\Report;

/**
 * Controlador para generar un informe de clientes con diferentes métricas (activos, por país, por grupo, etc.)
 *
 * @author Abderrahim Darghal Belkacemi
 */
class ReportCustomers extends Controller
{
    /** @var int */
    public $activeCustomers;

    /** @var int */
    public $activeCustomersYear;

    /** @var array */
    public $charts = [];

    /** @var int */
    public $customersWithDebt;

    /** @var array todas las empresas a listar en el formulario [idEmpresa => nombre empresa] */
    public $companies = [];

    /** @var string */
    public $companyCountry;

    /** @var string */
    public $companyCountryCode;

    /** @var array */
    public $customersByCountry;

    /** @var int empresa seleccionada (obligatoria) */
    public $idempresa;

    /** @var int */
    public $inactiveCustomers;

    /** @var int */
    public $newCustomers30Days;

    /** @var int */
    public $totalCustomers;

    /** @var string */
    protected $currentYear;

    /** @var string */
    protected $whereEmpresaClientes = '';

    /** @var string */
    protected $whereEmpresaFacturas = '';

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'clients';
        $data['icon'] = 'fa-solid fa-users';
        return $data;
    }

    public function run(): void
    {
        parent::run();

        // cargar empresas
        $this->loadCompanies();

        // cargar datos de las gráficas
        $this->loadData();
        $this->loadTotalCustomers();
        $this->loadActiveCustomers();
        $this->loadInactiveCustomers();
        $this->loadActiveCustomersByYear();
        $this->loadActiveCustomersYear();
        $this->loadNewCustomers30Days();
        $this->loadCustomersWithDebt();
        $this->loadCustomersByCountry();
        $this->loadCustomersByProvince();
        $this->loadCustomersByGroup();
        $this->loadNewCustomersByMonth();
        $this->loadNewCustomersByYear();
        $this->loadInvoicesByProvince();
        $this->loadTopDebtors();
        $this->loadDebtors();

        $this->view('ReportCustomers.html.twig');
    }

    protected function loadActiveCustomers(): void
    {
        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));
        $sqlActive = "SELECT COUNT(DISTINCT codcliente) as total FROM facturascli WHERE fecha >= '$oneYearAgo'" . $this->whereEmpresaFacturas;
        $this->activeCustomers = $this->db()->select($sqlActive)[0]['total'];
    }

    protected function loadActiveCustomersByYear(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'facturascli';
        $report->xcolumn = 'fecha';
        $report->ycolumn = 'codcliente';
        $report->xoperation = 'YEAR';
        $report->yoperation = 'COUNT_DISTINCT';
        $report->addFieldXName('');
        $report->addCustomFilter('idempresa', '=', (int)$this->idempresa);

        $this->charts['activeCustomersByYear'] = $report;
    }

    protected function loadActiveCustomersYear(): void
    {
        $sqlActiveYear = "SELECT COUNT(DISTINCT codcliente) as total FROM facturascli WHERE fecha >= '$this->currentYear-01-01'" . $this->whereEmpresaFacturas;
        $this->activeCustomersYear = $this->db()->select($sqlActiveYear)[0]['total'];
    }

    protected function loadCustomersByCountry(): void
    {
        $sqlCountries = "SELECT c.codpais, p.codiso, p.nombre, COUNT(*) as total
                         FROM clientes cl
                         LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto
                         LEFT JOIN paises p ON c.codpais = p.codpais
                         WHERE cl.codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . (int)$this->idempresa . ")
                         GROUP BY c.codpais, p.codiso, p.nombre ORDER BY total DESC";
        $this->customersByCountry = $this->db()->select($sqlCountries);
    }

    protected function loadCustomersByGroup(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_DOUGHNUT;
        $report->table = 'clientes c';
        $report->xcolumn = "COALESCE(g.nombre, '" . Tools::trans('customers-without-group') . "')";
        $report->ycolumn = '*';
        $report->yoperation = 'COUNT';

        // añadimos el JOIN con gruposclientes para obtener el nombre del grupo
        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN gruposclientes g ON c.codgrupo = g.codgrupo');

        $report->addCustomFilter(
            'c.codcliente',
            'IN',
            'SELECT codcliente FROM facturascli WHERE idempresa = ' . (int)$this->idempresa
        );

        $this->charts['customersByGroup'] = $report;
    }

    protected function loadCustomersByProvince(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_TREE_MAP;
        $report->table = 'clientes cl';
        $report->xcolumn = "COALESCE(NULLIF(c.provincia, ''), '" . Tools::trans('no-data') . "')";
        $report->ycolumn = '*';
        $report->yoperation = 'COUNT';

        // añadimos el JOIN con contactos para obtener provincia y país
        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto');

        // filtramos por el país de la empresa para mostrar solo provincias del país
        $report->addCustomFilter('c.codpais', '=', $this->companyCountryCode);

        $report->addCustomFilter(
            'cl.codcliente',
            'IN',
            'SELECT codcliente FROM facturascli WHERE idempresa = ' . (int)$this->idempresa
        );

        $this->charts['customersByProvince'] = $report;
    }

    protected function loadCompanies(): void
    {
        foreach (Empresas::all() as $company) {
            $this->companies[$company->idempresa] = $company->nombrecorto;
        }

        $requested = $this->request()->queryOrInput('idempresa', null);
        $this->idempresa = $requested === null ? Empresas::default()->idempresa : (int)$requested;

        if (!isset($this->companies[$this->idempresa])) {
            $this->idempresa = Empresas::default()->idempresa;
        }
    }

    protected function loadData(): void
    {
        $this->currentYear = date('Y');
        $this->companyCountryCode = Tools::settings('default', 'codpais');

        $company = new Empresa();
        if ($company->load($this->idempresa) && !empty($company->codpais)) {
            $this->companyCountryCode = $company->codpais;
        }

        $country = new Pais();
        if ($country->load($this->companyCountryCode)) {
            $this->companyCountry = $country->nombre;
        } else {
            $this->companyCountry = $this->companyCountryCode;
        }

        $this->whereEmpresaFacturas = " AND idempresa = " . (int)$this->idempresa;
        $this->whereEmpresaClientes = " WHERE codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . (int)$this->idempresa . ")";
    }

    protected function loadDebtors(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_TREE_MAP;
        $report->table = 'facturascli f';
        $report->xcolumn = 'f.nombrecliente';
        $report->ycolumn = 'f.total';
        $report->yoperation = 'SUM';

        Report::activateAdvancedReport(true);
        $report->addCustomSql($this->getDebtorsSql());

        $this->charts['debtors'] = $report;
    }

    protected function loadInactiveCustomers(): void
    {
        $sqlInactive = "SELECT COUNT(*) as total FROM clientes WHERE debaja = true"
            . " AND codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . (int)$this->idempresa . ")";
        $this->inactiveCustomers = $this->db()->select($sqlInactive)[0]['total'];
    }

    protected function loadInvoicesByProvince(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_TREE_MAP;
        $report->table = 'facturascli f';
        $report->xcolumn = "COALESCE(NULLIF(f.provincia, ''), c.provincia, '" . Tools::trans('no-data') . "')";
        $report->ycolumn = 'DISTINCT f.codcliente';
        $report->yoperation = 'COUNT';

        // añadimos los JOINs necesarios para obtener la provincia del contacto
        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN clientes cl ON f.codcliente = cl.codcliente');
        $report->addCustomJoin('LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto');

        $report->addCustomFilter('f.idempresa', '=', (int)$this->idempresa);

        $this->charts['invoicesByProvince'] = $report;
    }

    protected function loadNewCustomers30Days(): void
    {
        $sql = "SELECT COUNT(*) as total FROM clientes WHERE fechaalta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
            . " AND codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . (int)$this->idempresa . ")";

        $this->newCustomers30Days = (int)$this->db()->select($sql)[0]['total'];
    }

    protected function loadNewCustomersByMonth(): void
    {
        $report = new Report();
        $report->type = Report::DEFAULT_TYPE;
        $report->table = 'clientes';
        $report->xcolumn = 'fechaalta';
        $report->ycolumn = 'codcliente';
        $report->xoperation = 'MONTHS';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');
        $report->addCustomFilter('fechaalta', '>=', '{-1 year}');
        $report->addCustomFilter('fechaalta', '<=', '{today}');

        Report::activateAdvancedReport(true);
        $report->addCustomFilter(
            'codcliente',
            'IN',
            'SELECT codcliente FROM facturascli WHERE idempresa = ' . (int)$this->idempresa
        );

        $this->charts['reportTest'] = $report;
    }

    protected function loadNewCustomersByYear(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'clientes';
        $report->xcolumn = 'fechaalta';
        $report->ycolumn = 'codcliente';
        $report->xoperation = 'YEAR';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');

        Report::activateAdvancedReport(true);
        $report->addCustomFilter(
            'codcliente',
            'IN',
            'SELECT codcliente FROM facturascli WHERE idempresa = ' . (int)$this->idempresa
        );

        $this->charts['newCustomersByYear'] = $report;
    }

    protected function loadTopDebtors(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'facturascli f';
        $report->xcolumn = 'f.nombrecliente';
        $report->ycolumn = 'f.total';
        $report->yoperation = 'SUM';

        Report::activateAdvancedReport(true);
        $report->addCustomSql($this->getDebtorsSql(10));

        $this->charts['topDebtors'] = $report;
    }

    protected function loadCustomersWithDebt(): void
    {
        $sql = "SELECT COUNT(DISTINCT codcliente) as total FROM facturascli WHERE pagada = " . $this->db()->var2str(false)
            . " AND idempresa = " . $this->db()->var2str((int)$this->idempresa);

        $this->customersWithDebt = (int)$this->db()->select($sql)[0]['total'];
    }

    protected function getDebtorsSql(int $limit = 0): string
    {
        $sql = "SELECT "
            . "f.codcliente as codcliente, "
            . "COALESCE(NULLIF(MAX(f.nombrecliente), ''), f.codcliente, '" . Tools::trans('no-data') . "') as xcol, "
            . "SUM(f.total) as ycol "
            . "FROM facturascli f "
            . "WHERE f.pagada = " . $this->db()->var2str(false)
            . " AND f.idempresa = " . $this->db()->var2str((int)$this->idempresa);

        $sql .= " GROUP BY f.codcliente HAVING SUM(f.total) <> 0 ORDER BY ycol DESC, xcol ASC";
        if ($limit > 0) {
            $sql .= " LIMIT " . $limit;
        }

        return $sql . ';';
    }

    protected function loadTotalCustomers(): void
    {
        $sqlTotal = "SELECT COUNT(*) as total FROM clientes" . $this->whereEmpresaClientes;
        $this->totalCustomers = $this->db()->select($sqlTotal)[0]['total'];
    }
}
