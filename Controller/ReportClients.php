<?php
namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Pais;

/**
 * Vista del reporte de clientes, tiene unas propiedades que son las que se exponen a la vista
 */
class ReportClients extends Controller
{
    // propiedades que usa la vista twig
    public $activeCustomers;
    public $activeCustomersYear;
    public $companyCountry;
    public $companyCountryCode;
    public $customersByCountry;
    public $customersByGroup;
    public $customersByProvince;
    public $invoicesByProvince;
    public $debtors;
    public $inactiveCustomers;
    public $newCustomersByMonth;
    public $totalCustomers;
    public $totalDebt;
    public $totalDebtors;
    
    /** @var int|string|null el idempesa sugerido por el usuario (puede ser 'all') */
    public $idempresa;

    /** @var Array todas las empresas a listar en el formulario [idEmpresa => nombre empresa] */
    public $companies = [];

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
        $this->loadReportData();
    }

    private function loadCompanies()
    {
        $empresaModel = new Empresa();
        $rows = $empresaModel->all();

        $this->companies = ['all' => Tools::trans('all-companies')];
        foreach ($rows as $company) {
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

    /**
     * Crea los reportes y guarda el resultado en las propiedades de la clase.
     */
    private function loadReportData()
    {
        $db = new DataBase();
        $currentYear = date('Y');
        $companyCountryCode = Tools::settings('default', 'codpais');
        $this->companyCountryCode = $companyCountryCode;

        $country = new Pais();
        if ($country->load($companyCountryCode)) {
            $companyCountryName = $country->nombre;
        } else {
            $companyCountryName = $companyCountryCode;
        }
        $this->companyCountry = $companyCountryName;

        $whereEmpresaFacturas = "";
        $whereEmpresaClientes = "";
        if ($this->idempresa !== 'all') {
            $whereEmpresaFacturas = " AND idempresa = " . $this->idempresa;
            $whereEmpresaClientes = " WHERE codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        }

        // 1. Customer counts
        $sqlTotal = "SELECT COUNT(*) as total FROM clientes" . $whereEmpresaClientes;
        $this->totalCustomers = $db->select($sqlTotal)[0]['total'];

        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));
        $sqlActive = "SELECT COUNT(DISTINCT codcliente) as total FROM facturascli WHERE fecha >= '$oneYearAgo'" . $whereEmpresaFacturas;
        $this->activeCustomers = $db->select($sqlActive)[0]['total'];

        $sqlInactive = "SELECT COUNT(*) as total FROM clientes";
        if ($this->idempresa !== 'all') {
            $sqlInactive .= " WHERE debaja = true AND codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        } else {
            $sqlInactive .= " WHERE debaja = true";
        }
        $this->inactiveCustomers = $db->select($sqlInactive)[0]['total'];
        
        // Active this year (having invoices)
        $sqlActiveYear = "SELECT COUNT(DISTINCT codcliente) as total FROM facturascli WHERE fecha >= '$currentYear-01-01'" . $whereEmpresaFacturas;
        $this->activeCustomersYear = $db->select($sqlActiveYear)[0]['total'];

        // 2. By Country
        $sqlCountries = "SELECT c.codpais, p.codiso, p.nombre, COUNT(*) as total 
                         FROM clientes cl 
                         LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto 
                         LEFT JOIN paises p ON c.codpais = p.codpais";
        if ($this->idempresa !== 'all') {
            $sqlCountries .= " WHERE cl.codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        }
        $sqlCountries .= " GROUP BY c.codpais, p.codiso, p.nombre ORDER BY total DESC";
        $this->customersByCountry = $db->select($sqlCountries);

        // 3. By Province (of company country)
        $sqlProvinces = "SELECT c.provincia as nomprovincia, COUNT(*) as total 
                         FROM clientes cl 
                         LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto 
                         WHERE c.codpais = " . $db->var2str($companyCountryCode);
        if ($this->idempresa !== 'all') {
            $sqlProvinces .= " AND cl.codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        }
        $sqlProvinces .= " GROUP BY c.provincia ORDER BY total DESC";
        $this->customersByProvince = $db->select($sqlProvinces);

        // 4. By Group
        $sqlGroups = "SELECT g.nombre, COUNT(*) as total 
                      FROM clientes c 
                      LEFT JOIN gruposclientes g ON c.codgrupo = g.codgrupo";
        if ($this->idempresa !== 'all') {
            $sqlGroups .= " WHERE c.codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        }
        $sqlGroups .= " GROUP BY g.nombre ORDER BY total DESC";
        $this->customersByGroup = $db->select($sqlGroups);
        foreach ($this->customersByGroup as $key => $row) {
            if (empty($row['nombre'])) {
                $this->customersByGroup[$key]['nombre'] = Tools::trans('customers-without-group');
            }
        }

        // 5. New customers per month
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

        $results = $db->select($sqlNew);
        foreach ($results as $row) {
            if (isset($newByMonth[$row['month']])) {
                $newByMonth[$row['month']] = (int)$row['total'];
            }
        }
        $this->newCustomersByMonth = $newByMonth;

        // 5.5 Invoices by Province
        $sqlInvProvince = "SELECT COALESCE(NULLIF(f.provincia, ''), c.provincia) as provincia, COUNT(DISTINCT f.codcliente) as total
                           FROM facturascli f
                           LEFT JOIN clientes cl ON f.codcliente = cl.codcliente
                           LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto";

        if ($this->idempresa !== 'all') {
            $sqlInvProvince .= " WHERE f.idempresa = " . $this->idempresa;
        }

        $sqlInvProvince .= " GROUP BY provincia ORDER BY total DESC";
        $this->invoicesByProvince = $db->select($sqlInvProvince);
        foreach ($this->invoicesByProvince as $key => $row) {
            if (empty($row['provincia'])) {
                $this->invoicesByProvince[$key]['provincia'] = Tools::trans('no-data');
            }
        }

        // 6. Debtors
        $sqlDebtors = "SELECT f.codcliente, f.nombrecliente, SUM(f.total) as deuda 
                       FROM facturascli f 
                       WHERE f.pagada = false" . $whereEmpresaFacturas . "
                       GROUP BY f.codcliente, f.nombrecliente 
                       HAVING deuda > 0 
                       ORDER BY deuda DESC";
        $debtors = $db->select($sqlDebtors);
        $this->debtors = $debtors;
        $this->totalDebtors = count($debtors);
        $this->totalDebt = array_sum(array_column($debtors, 'deuda'));
    }
}
