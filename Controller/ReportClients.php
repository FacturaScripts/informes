<?php
namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Lib\AssetManager;
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
    public $customersByCountry;
    public $customersByGroup;
    public $customersByProvince;
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

        $sqlActive = "SELECT COUNT(*) as total FROM clientes";
        if ($this->idempresa !== 'all') {
            $sqlActive .= " WHERE debaja = false AND codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        } else {
            $sqlActive .= " WHERE debaja = false";
        }
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
        $sqlCountries = "SELECT c.codpais, p.nombre, COUNT(*) as total 
                         FROM clientes cl 
                         LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto 
                         LEFT JOIN paises p ON c.codpais = p.codpais";
        if ($this->idempresa !== 'all') {
            $sqlCountries .= " WHERE cl.codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ")";
        }
        $sqlCountries .= " GROUP BY c.codpais, p.nombre ORDER BY total DESC";
        $this->customersByCountry = $db->select($sqlCountries);

        // 3. By Province (of company country)
        $sqlProvinces = "SELECT c.provincia, COUNT(*) as total 
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

        // 5. New customers per month
        $sqlNew = "SELECT fechaalta FROM clientes";
        if ($this->idempresa !== 'all') {
            $sqlNew .= " WHERE codcliente IN (SELECT codcliente FROM facturascli WHERE idempresa = " . $this->idempresa . ") AND fechaalta >= '" . ($currentYear - 1) . "-01-01'";
        } else {
            $sqlNew .= " WHERE fechaalta >= '" . ($currentYear - 1) . "-01-01'";
        }
        $sqlNew .= " ORDER BY fechaalta DESC";
        $dates = $db->select($sqlNew);
        $newByMonth = [];
        foreach ($dates as $row) {
            $month = substr($row['fechaalta'], 0, 7); // YYYY-MM
            if (!isset($newByMonth[$month])) {
                $newByMonth[$month] = 0;
            }
            $newByMonth[$month]++;
        }
        $this->newCustomersByMonth = $newByMonth;

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
