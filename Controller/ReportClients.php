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
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Plugins\Informes\Lib\ReportChart\AreaChart;
use FacturaScripts\Plugins\Informes\Model\Report;
use FacturaScripts\Plugins\Informes\Model\ReportFilter;

/**
 * Controlador para generar un informe de clientes con diferentes métricas (activos, por país, por grupo, etc.)
 *
 * @author Abderrahim Darghal Belkacemi
 */
class ReportClients extends Controller
{
    /** @var int */
    public $activeCustomers;

    /** @var int */
    public $activeCustomersYear;

    /** @var array */
    public $charts = [];

    /** @var array todas las empresas a listar en el formulario [idEmpresa => nombre empresa] */
    public $companies = [];

    /** @var string */
    public $companyCountry;

    /** @var string */
    public $companyCountryCode;

    /** @var array */
    public $customersByCountry;

    /** @var int|string|null el idempesa sugerido por el usuario (puede ser 'all') */
    public $idempresa;

    /** @var int */
    public $inactiveCustomers;

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

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        // cargar empresas
        $this->loadCompanies();

        // cargar datos de las gráficas
        $this->loadData();
        $this->loadTotalCustomers();
        $this->loadActiveCustomers();
        $this->loadInactiveCustomers();
        $this->loadActiveCustomersYear();
        $this->loadCustomersByCountry();
        $this->loadCustomersByProvince();
        $this->loadCustomersByGroup();
        $this->loadNewCustomersByMonth();
        $this->loadInvoicesByProvince();
        $this->loadDebtors();
    }

    protected function loadActiveCustomers(): void
    {
        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));
        $sqlActive = "SELECT COUNT(DISTINCT codcliente) as total FROM facturascli WHERE fecha >= '$oneYearAgo'" . $this->whereEmpresaFacturas;
        $this->activeCustomers = $this->dataBase->select($sqlActive)[0]['total'];
    }

    protected function loadActiveCustomersYear(): void
    {
        $sqlActiveYear = "SELECT COUNT(DISTINCT codcliente) as total FROM facturascli WHERE fecha >= '$this->currentYear-01-01'" . $this->whereEmpresaFacturas;
        $this->activeCustomersYear = $this->dataBase->select($sqlActiveYear)[0]['total'];
    }

    protected function loadCustomersByCountry(): void
    {
        $sqlCountries = "SELECT c.codpais, p.codiso, p.nombre, COUNT(*) as total 
                         FROM clientes cl 
                         LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto 
                         LEFT JOIN paises p ON c.codpais = p.codpais";
        if ($this->idempresa !== 'all') {
            $sqlCountries .= " WHERE cl.codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        }
        $sqlCountries .= " GROUP BY c.codpais, p.codiso, p.nombre ORDER BY total DESC";
        $this->customersByCountry = $this->dataBase->select($sqlCountries);
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

        // aplicamos el filtro de empresa si no se están mostrando todas
        if ($this->idempresa !== 'all') {
            $report->addCustomFilter(
                'c.codcliente',
                'IN',
                'SELECT codcliente FROM facturascli WHERE idempresa = ' . (int)$this->idempresa
            );
        }

        $this->charts['customersByGroup'] = $report;
    }

    protected function loadCustomersByProvince(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'clientes cl';
        $report->xcolumn = "COALESCE(NULLIF(c.provincia, ''), '" . Tools::trans('no-data') . "')";
        $report->ycolumn = '*';
        $report->yoperation = 'COUNT';

        // añadimos el JOIN con contactos para obtener provincia y país
        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto');

        // filtramos por el país de la empresa para mostrar solo provincias del país
        $report->addCustomFilter('c.codpais', '=', $this->companyCountryCode);

        // aplicamos el filtro de empresa si no se están mostrando todas
        if ($this->idempresa !== 'all') {
            $report->addCustomFilter(
                'cl.codcliente',
                'IN',
                'SELECT codcliente FROM facturascli WHERE idempresa = ' . (int)$this->idempresa
            );
        }

        $this->charts['customersByProvince'] = $report;
    }

    protected function loadCompanies(): void
    {
        $this->companies = ['all' => Tools::trans('all-companies')];
        foreach (Empresa::all() as $company) {
            $this->companies[$company->idempresa] = $company->nombrecorto;
        }

        // empresa seleccionada
        $this->idempresa = $this->request()->queryOrInput('idempresa', null);
        if (null === $this->idempresa) {
            // seleccionar por defecto si no hay nada
            $this->idempresa = (int)Tools::settings('default', 'idempresa');
        } elseif ($this->idempresa === 'all') {
            // seleccionar todas
            $this->idempresa = 'all';
        } else {
            // seleccionar sugerida
            $this->idempresa = (int)$this->idempresa;
        }
    }

    protected function loadData(): void
    {
        $this->currentYear = date('Y');
        $this->companyCountryCode = Tools::settings('default', 'codpais');

        $country = new Pais();
        if ($country->load($this->companyCountryCode)) {
            $this->companyCountry = $country->nombre;
        } else {
            $this->companyCountry = $this->companyCountryCode;
        }

        if ($this->idempresa !== 'all') {
            $this->whereEmpresaFacturas = " AND idempresa = " . $this->idempresa;
            $this->whereEmpresaClientes = " WHERE codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        }
    }

    protected function loadDebtors(): void
    {
        // replicamos la misma SQL usando la clase Report para el gráfico
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'facturascli f';
        $report->xcolumn = 'f.nombrecliente';
        $report->ycolumn = 'f.total';
        $report->yoperation = 'SUM';

        // activamos el modo avanzado para poder usar filtros personalizados
        Report::activateAdvancedReport(true);

        // filtramos por facturas no pagadas
        $report->addCustomFilter('f.pagada', '=', '0');

        // aplicamos el filtro de empresa si no se están mostrando todas
        if ($this->idempresa !== 'all') {
            $report->addCustomFilter('f.idempresa', '=', (string)(int)$this->idempresa);
        }

        $this->charts['debtors'] = $report;
    }

    protected function loadInactiveCustomers(): void
    {
        $sqlInactive = "SELECT COUNT(*) as total FROM clientes";
        if ($this->idempresa !== 'all') {
            $sqlInactive .= " WHERE debaja = true AND codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        } else {
            $sqlInactive .= " WHERE debaja = true";
        }
        $this->inactiveCustomers = $this->dataBase->select($sqlInactive)[0]['total'];
    }

    protected function loadInvoicesByProvince(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'facturascli f';
        $report->xcolumn = "COALESCE(NULLIF(f.provincia, ''), c.provincia, '" . Tools::trans('no-data') . "')";
        $report->ycolumn = 'DISTINCT f.codcliente';
        $report->yoperation = 'COUNT';

        // añadimos los JOINs necesarios para obtener la provincia del contacto
        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN clientes cl ON f.codcliente = cl.codcliente');
        $report->addCustomJoin('LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto');

        // aplicamos el filtro de empresa si no se están mostrando todas
        if ($this->idempresa !== 'all') {
            $report->addCustomFilter('f.idempresa', '=', (int)$this->idempresa);
        }

        $this->charts['invoicesByProvince'] = $report;
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

        // aplicamos el filtro de empresa si no se están mostrando todas
        if ($this->idempresa !== 'all') {
            Report::activateAdvancedReport(true);
            $report->addCustomFilter(
                'codcliente',
                'IN',
                'SELECT codcliente FROM facturascli WHERE idempresa = ' . (int)$this->idempresa
            );
        }

        $this->charts['reportTest'] = $report;
    }

    protected function loadTotalCustomers(): void
    {
        $sqlTotal = "SELECT COUNT(*) as total FROM clientes" . $this->whereEmpresaClientes;
        $this->totalCustomers = $this->dataBase->select($sqlTotal)[0]['total'];
    }
}
