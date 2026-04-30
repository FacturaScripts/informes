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

use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Plugins\Informes\Model\Report;

/**
 * Controlador para generar un informe de proveedores con diferentes métricas (activos, por país, acreedores, etc.)
 *
 * @author Esteban Sánchez Martínez
 */
class ReportSuppliers extends Controller
{
    /** @var array todas las empresas a listar en el formulario [idEmpresa => nombre empresa] */
    public $companies = [];

    /** @var int|string|null el idempresa sugerido por el usuario (puede ser 'all') */
    public $idempresa;

    /** @var int Total de proveedores */
    public $totalSuppliers;

    /** @var int Proveedores activos en el último año */
    public $activeSupplier;

    /** @var int Proveedores activos en el año actual */
    public $activeSuppliersYear;

    /** @var int Proveedores marcados como baja */
    public $inactiveSuppliers;

    /** @var int Nuevos proveedores en los últimos 30 días */
    public $newSuppliers30Days;

    /** @var int Proveedores con facturas pendientes de pago */
    public $suppliersWithPayables;

    /** @var array Reportes */
    public $charts = [];

    /** @var array Resultado agrupado por país */
    public $suppliersByCountry;

    /** @var string Nombre del país de la empresa */
    public $companyCountry;

    /** @var string Código del país de la empresa */
    public $companyCountryCode;

    /** @var string Año actual */
    protected $currentYear;

    /** @var string Filtros WHERE para facturasprov según empresa */
    protected $whereEmpresaFacturasProv = '';

    /** @var string Filtros WHERE para proveedores según empresa */
    protected $whereEmpresaProveedores = '';


    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'suppliers';
        $data['icon'] = 'fa-solid fa-users';
        return $data;
    }

    public function run(): void
    {
        parent::run();

        //Cargamos las empresas
        $this->loadCompanies();

        //Cargamos datos las gráficas
        $this->loadData();
        $this->loadTotalSuppliers();
        $this->loadActiveSupplier();
        $this->loadInactiveSupplier();
        $this->loadActiveSupplierYear();
        $this->loadNewSuppliers30Days();
        $this->loadSuppliersWithPayables();
        $this->loadSuppliersByCountry();
        $this->loadSuppliersByProvince();
        $this->loadNewSuppliersByMonth();
        $this->loadNewSuppliersByYear();
        $this->loadInvoicesByProvince();
        $this->loadCreditors();
        $this->loadTopCreditors();

        $this->view('ReportSuppliers.html.twig');
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
            // seleccionar todas por defecto si no hay nada
            $this->idempresa = 'all';
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

        if ($this->idempresa !== 'all') {
            $company = new Empresa();
            if ($company->load($this->idempresa) && !empty($company->codpais)) {
                $this->companyCountryCode = $company->codpais;
            }
        }

        $country = new Pais();
        if ($country->load($this->companyCountryCode)) {
            $this->companyCountry = $country->nombre;
        } else {
            $this->companyCountry = $this->companyCountryCode;
        }

        if ($this->idempresa !== 'all') {
            $this->whereEmpresaFacturasProv = " AND idempresa = " . $this->idempresa;
            $this->whereEmpresaProveedores = " WHERE codproveedor IN (SELECT codproveedor FROM facturasprov WHERE idempresa = " . $this->idempresa . ")";
        }
    }

    protected function loadTotalSuppliers(): void
    {
        $sqlTotal = "SELECT COUNT(*) as total FROM proveedores" . $this->whereEmpresaProveedores;
        $this->totalSuppliers = (int)$this->db()->select($sqlTotal)[0]['total'];
    }

    protected function loadActiveSupplier(): void
    {
        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));
        $sqlActive = "SELECT COUNT(DISTINCT codproveedor) as total FROM facturasprov  WHERE fecha >= '$oneYearAgo'" . $this->whereEmpresaFacturasProv;
        $this->activeSupplier = $this->db()->select($sqlActive)[0]['total'];
    }

    protected function loadActiveSupplierYear(): void
    {
        $sqlActiveYear = "SELECT COUNT(DISTINCT codproveedor) as total FROM facturasprov WHERE fecha >= '$this->currentYear-01-01'" . $this->whereEmpresaFacturasProv;
        $this->activeSuppliersYear = $this->db()->select($sqlActiveYear)[0]['total'];
    }

    protected function loadInactiveSupplier(): void
    {
        $sqlInactive = "SELECT COUNT(*) as total FROM proveedores";
        if ($this->idempresa !== 'all') {
            $sqlInactive .= " WHERE debaja = true AND codproveedor IN (SELECT codproveedor FROM facturasprov WHERE idempresa = " . $this->idempresa . ")";
        } else {
            $sqlInactive .= " WHERE debaja = true";
        }
        $this->inactiveSuppliers = $this->db()->select($sqlInactive)[0]['total'];
    }

    protected function loadNewSuppliers30Days(): void
    {
        $sql = "SELECT COUNT(*) as total FROM proveedores WHERE fechaalta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

        if ($this->idempresa !== 'all') {
            $sql .= " AND codproveedor IN (SELECT codproveedor FROM facturasprov WHERE idempresa = " . (int)$this->idempresa . ")";
        }
        $this->newSuppliers30Days = (int)$this->db()->select($sql)[0]['total'];
    }

    protected function loadNewSuppliersByMonth(): void
    {
        $report = new Report();
        $report->type = Report::DEFAULT_TYPE;
        $report->table = 'proveedores';
        $report->xcolumn = 'fechaalta';
        $report->ycolumn = 'codproveedor';
        $report->xoperation = 'MONTHS';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');
        $report->addCustomFilter('fechaalta', '>=', '{-1 year}');
        $report->addCustomFilter('fechaalta', '<=', '{today}');

        // aplicamos el filtro de empresa si no se están mostrando todas
        if ($this->idempresa !== 'all') {
            Report::activateAdvancedReport(true);
            $report->addCustomFilter(
                'codproveedor',
                'IN',
                'SELECT codproveedor FROM facturasprov WHERE idempresa = ' . (int)$this->idempresa
            );
        }

        $this->charts['reportTest'] = $report;
    }

    protected function loadNewSuppliersByYear(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'proveedores';
        $report->xcolumn = 'fechaalta';
        $report->ycolumn = 'codproveedor';
        $report->xoperation = 'YEAR';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');

        if ($this->idempresa !== 'all') {
            Report::activateAdvancedReport(true);
            $report->addCustomFilter(
                'codproveedor',
                'IN',
                'SELECT codproveedor FROM facturasprov WHERE idempresa = ' . (int)$this->idempresa
            );
        }

        $this->charts['newSuppliersByYear'] = $report;
    }

    protected function loadSuppliersByCountry(): void
    {
        $sqlCountries = "SELECT c.codpais, p.codiso, p.nombre, COUNT(*) as total 
                         FROM proveedores pv 
                         LEFT JOIN contactos c ON pv.idcontacto = c.idcontacto 
                         LEFT JOIN paises p ON c.codpais = p.codpais";
        if ($this->idempresa !== 'all') {
            $sqlCountries .= " WHERE pv.codproveedor IN (SELECT codproveedor FROM facturasprov WHERE idempresa = " . $this->idempresa . ")";
        }
        $sqlCountries .= " GROUP BY c.codpais, p.codiso, p.nombre ORDER BY total DESC";
        $this->suppliersByCountry = $this->db()->select($sqlCountries);
    }

    protected function loadSuppliersByProvince(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_TREE_MAP;
        $report->table = 'proveedores pv';
        $report->xcolumn = "COALESCE(NULLIF(c.provincia, ''), '" . Tools::trans('no-data') . "')";
        $report->ycolumn = '*';
        $report->yoperation = 'COUNT';

        // añadimos el JOIN con contactos para obtener provincia y país
        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN contactos c ON pv.idcontacto = c.idcontacto');

        // filtramos por el país de la empresa para mostrar solo provincias del país
        $report->addCustomFilter('c.codpais', '=', $this->companyCountryCode);

        // aplicamos el filtro de empresa si no se están mostrando todas
        if ($this->idempresa !== 'all') {
            $report->addCustomFilter(
                'pv.codproveedor',
                'IN',
                'SELECT codproveedor FROM facturasprov WHERE idempresa = ' . (int)$this->idempresa
            );
        }

        $this->charts['suppliersByProvince'] = $report;
    }

    protected function loadInvoicesByProvince(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_TREE_MAP;
        $report->table = 'facturasprov f';
        $report->xcolumn = "COALESCE(NULLIF(c.provincia, ''), '" . Tools::trans('no-data') . "')";
        $report->ycolumn = 'DISTINCT f.codproveedor';
        $report->yoperation = 'COUNT';

        // añadimos los JOINs necesarios para obtener la provincia del contacto
        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN proveedores pv ON f.codproveedor = pv.codproveedor');
        $report->addCustomJoin('LEFT JOIN contactos c ON pv.idcontacto = c.idcontacto');

        // aplicamos el filtro de empresa si no se están mostrando todas
        if ($this->idempresa !== 'all') {
            $report->addCustomFilter('f.idempresa', '=', (int)$this->idempresa);
        }

        $this->charts['invoicesByProvince'] = $report;
    }

    protected function loadSuppliersWithPayables(): void
    {
        $sql = "SELECT COUNT(DISTINCT codproveedor) as total FROM facturasprov WHERE pagada = " . $this->db()->var2str(false);

        if ($this->idempresa !== 'all') {
            $sql .= " AND idempresa = " . $this->db()->var2str((int)$this->idempresa);
        }

        $this->suppliersWithPayables = (int)$this->db()->select($sql)[0]['total'];
    }

    protected function loadCreditors(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_TREE_MAP;
        $report->table = 'facturasprov f';
        $report->xcolumn = 'f.nombre';
        $report->ycolumn = 'f.total';
        $report->yoperation = 'SUM';

        Report::activateAdvancedReport(true);
        $report->addCustomSql($this->getCreditorsSql());

        $this->charts['creditors'] = $report;
    }

    protected function loadTopCreditors(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'facturasprov f';
        $report->xcolumn = 'f.nombre';
        $report->ycolumn = 'f.total';
        $report->yoperation = 'SUM';

        Report::activateAdvancedReport(true);
        $report->addCustomSql($this->getCreditorsSql(10));

        $this->charts['topCreditors'] = $report;
    }

    protected function getCreditorsSql(int $limit = 0): string
    {
        $sql = "SELECT "
            . "f.codproveedor as codproveedor, "
            . "COALESCE(NULLIF(f.nombre, ''), f.codproveedor, '" . Tools::trans('no-data') . "') as xcol, "
            . "SUM(f.total) as ycol "
            . "FROM facturasprov f "
            . "WHERE f.pagada = " . $this->db()->var2str(false);

        if ($this->idempresa !== 'all') {
            $sql .= " AND f.idempresa = " . $this->db()->var2str((int)$this->idempresa);
        }

        $sql .= " GROUP BY f.codproveedor, xcol ORDER BY ycol DESC, xcol ASC";
        if ($limit > 0) {
            $sql .= " LIMIT " . $limit;
        }

        return $sql . ';';
    }
}
