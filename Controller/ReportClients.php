<?php
namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
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

        // cargar datos de las gráficas
        $this->loadReportData();
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

        // 1. Customer counts
        $this->totalCustomers = $db->select('SELECT COUNT(*) as total FROM clientes;')[0]['total'];
        $this->activeCustomers = $db->select("SELECT COUNT(*) as total FROM clientes WHERE debaja = false;")[0]['total'];
        $this->inactiveCustomers = $db->select("SELECT COUNT(*) as total FROM clientes WHERE debaja = true;")[0]['total'];
        
        // Active this year (having invoices)
        $sqlActiveYear = "SELECT COUNT(DISTINCT codcliente) as total FROM facturascli WHERE fecha >= '$currentYear-01-01'";
        $this->activeCustomersYear = $db->select($sqlActiveYear)[0]['total'];

        // 2. By Country
        $sqlCountries = "SELECT c.codpais, p.nombre, COUNT(*) as total 
                         FROM clientes cl 
                         LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto 
                         LEFT JOIN paises p ON c.codpais = p.codpais
                         GROUP BY c.codpais, p.nombre 
                         ORDER BY total DESC";
        $this->customersByCountry = $db->select($sqlCountries);

        // 3. By Province (of company country)
        $sqlProvinces = "SELECT c.provincia, COUNT(*) as total 
                         FROM clientes cl 
                         LEFT JOIN contactos c ON cl.idcontactofact = c.idcontacto 
                         WHERE c.codpais = " . $db->var2str($companyCountryCode) . "
                         GROUP BY c.provincia 
                         ORDER BY total DESC";
        $this->customersByProvince = $db->select($sqlProvinces);

        // 4. By Group
        $sqlGroups = "SELECT g.nombre, COUNT(*) as total 
                      FROM clientes c 
                      LEFT JOIN gruposclientes g ON c.codgrupo = g.codgrupo 
                      GROUP BY g.nombre 
                      ORDER BY total DESC";
        $this->customersByGroup = $db->select($sqlGroups);

        // 5. New customers per month
        $sqlNew = "SELECT fechaalta FROM clientes WHERE fechaalta >= '" . ($currentYear - 1) . "-01-01' ORDER BY fechaalta DESC";
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
                       WHERE f.pagada = false 
                       GROUP BY f.codcliente, f.nombrecliente 
                       HAVING deuda > 0 
                       ORDER BY deuda DESC";
        $debtors = $db->select($sqlDebtors);
        $this->debtors = $debtors;
        $this->totalDebtors = count($debtors);
        $this->totalDebt = array_sum(array_column($debtors, 'deuda'));
    }
}
