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

    /** @var array todas las empresas a listar en el formulario [idEmpresa => nombre empresa] */
    public $companies = [];

    /** @var string */
    public $companyCountry;

    /** @var string */
    public $companyCountryCode;

    /** @var array */
    public $customersByCountry;

    /** @var array */
    public $customersByGroup;

    /** @var array */
    public $customersByProvince;

    /** @var array */
    public $debtors;

    /** @var int|string|null el idempesa sugerido por el usuario (puede ser 'all') */
    public $idempresa;

    /** @var int */
    public $inactiveCustomers;

    /** @var array */
    public $invoicesByProvince;

    /** @var array */
    public $newCustomersByMonth;

    /** @var int */
    public $totalCustomers;

    /** @var float */
    public $totalDebt;

    /** @var int */
    public $totalDebtors;

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
        $sqlGroups = "SELECT g.nombre, COUNT(*) as total 
                      FROM clientes c 
                      LEFT JOIN gruposclientes g ON c.codgrupo = g.codgrupo";
        if ($this->idempresa !== 'all') {
            $sqlGroups .= " WHERE c.codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        }
        $sqlGroups .= " GROUP BY g.nombre ORDER BY total DESC";
        $this->customersByGroup = $this->dataBase->select($sqlGroups);
        foreach ($this->customersByGroup as $key => $row) {
            if (empty($row['nombre'])) {
                $this->customersByGroup[$key]['nombre'] = Tools::trans('customers-without-group');
            }
        }
    }

    protected function loadCustomersByProvince(): void
    {
        $sqlProvinces = "SELECT c.provincia as nomprovincia, COUNT(*) as total 
                         FROM clientes cl 
                         LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto 
                         WHERE c.codpais = " . $this->dataBase->var2str($this->companyCountryCode);
        if ($this->idempresa !== 'all') {
            $sqlProvinces .= " AND cl.codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        }
        $sqlProvinces .= " GROUP BY c.provincia ORDER BY total DESC";
        $this->customersByProvince = $this->dataBase->select($sqlProvinces);
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
        $sqlDebtors = "SELECT f.codcliente, f.nombrecliente, SUM(f.total) as deuda 
                       FROM facturascli f 
                       WHERE f.pagada = false" . $this->whereEmpresaFacturas . "
                       GROUP BY f.codcliente, f.nombrecliente 
                       HAVING deuda > 0 
                       ORDER BY deuda DESC";
        $this->debtors = $this->dataBase->select($sqlDebtors);
        $this->totalDebtors = count($this->debtors);
        $this->totalDebt = array_sum(array_column($this->debtors, 'deuda'));
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
        $sqlInvProvince = "SELECT COALESCE(NULLIF(f.provincia, ''), c.provincia) as provincia, COUNT(DISTINCT f.codcliente) as total
                           FROM facturascli f
                           LEFT JOIN clientes cl ON f.codcliente = cl.codcliente
                           LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto";

        if ($this->idempresa !== 'all') {
            $sqlInvProvince .= " WHERE f.idempresa = " . $this->idempresa;
        }

        $sqlInvProvince .= " GROUP BY provincia ORDER BY total DESC";
        $this->invoicesByProvince = $this->dataBase->select($sqlInvProvince);
        foreach ($this->invoicesByProvince as $key => $row) {
            if (empty($row['provincia'])) {
                $this->invoicesByProvince[$key]['provincia'] = Tools::trans('no-data');
            }
        }
    }

    protected function loadNewCustomersByMonth(): void
    {
        $newByMonth = [];
        for ($i = 11; $i >= 0; $i--) {
            $newByMonth[date('Y-m', strtotime("first day of -$i months"))] = 0;
        }

        $startMonth = date('Y-m-01', strtotime("first day of -11 months"));
        $sqlNew = "SELECT SUBSTR(fechaalta, 1, 7) as month, COUNT(*) as total FROM clientes";
        $where = " WHERE fechaalta >= '$startMonth'";

        if ($this->idempresa !== 'all') {
            $where .= " AND codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        }

        $sqlNew .= $where . " GROUP BY month ORDER BY month ASC";

        $results = $this->dataBase->select($sqlNew);
        foreach ($results as $row) {
            if (isset($newByMonth[$row['month']])) {
                $newByMonth[$row['month']] = (int)$row['total'];
            }
        }
        $this->newCustomersByMonth = $newByMonth;
    }

    protected function loadTotalCustomers(): void
    {
        $sqlTotal = "SELECT COUNT(*) as total FROM clientes" . $this->whereEmpresaClientes;
        $this->totalCustomers = $this->dataBase->select($sqlTotal)[0]['total'];
    }
}
