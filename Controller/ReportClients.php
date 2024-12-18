<?php

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Informes\Lib\Informes\SummaryReportClients;
use FacturaScripts\Plugins\Informes\Lib\Informes\ClientsBillingReport;
use FacturaScripts\Plugins\Informes\Lib\Informes\ClientsUnpaidReport;
use FacturaScripts\Core\Base\DataBase;

class ReportClients extends Controller
{

        /** @var string */
        public $codejercicio;
        private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    public function getCompanies(): array
    {
        return Empresas::all();
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["menu"] = "reports";
        $data["title"] = "clients-reports";
        $data["icon"] = "fas fa-poll";
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->loadCurrentExercise();

        $action = $this->request->get('action', '');

        switch ($action) {

            case 'load-summary':
                $this->loadSummary();
                break;

            case 'load-billing':
                $this->loadBillingLocation($action);
                break;           
                
            case 'load-unpaid':
                $this->loadUnpaidClients($action);
                break;                  

            case 'load-unpaid-details': 
                $this->loadUnpaidClientDetails(); 
                break;                
        }
    }
    
    protected function loadUnpaidClientDetails(): void
    {
        $this->setTemplate(false);        
        $details = ClientsUnpaidReport::generateClientsWithUnpaidInvocesRows($this->request->request->all());

        $content = [
            'unpaid_clients_details' => $details,
            'messages' => Tools::log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    
    protected function loadUnpaidClients(string $action): void
    {
        $this->setTemplate(false);        
        
        $key = ($action == "load-unpaid") ? "unpaid" : "";

        $content = [
            $key => ClientsUnpaidReport::render($this->request->request->all()),
            'messages' => Tools::log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
        
    }


    protected function loadBillingLocation(string $action): void
    {
        $this->setTemplate(false);        
        
        $key = ($action == "load-billing") ? "billing" : "";

        $content = [
            $key => ClientsBillingReport::render($this->request->request->all()),
            'messages' => Tools::log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
        
    }

    
    protected function loadCurrentExercise(): void
    {
        foreach ($this->user->getCompany()->getExercises() as $exercise) {
            if (date('Y', strtotime($exercise->fechafin)) === date('Y')) {
                $this->codejercicio = $exercise->codejercicio;
                break;
            }
        }
    }

    protected function loadSummary(): void
    {
        $this->setTemplate(false);
        $content = [
            'summary' => SummaryReportClients::render($this->request->request->all()),
            'charts' => SummaryReportClients::$charts,
            'messages' => Tools::log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }    

    public function getCountriesClientsCompany(): array
    {
        $dataBase = new DataBase();

        $sql = "SELECT DISTINCT co.codpais as codigo_pais, pa.nombre as nombre_pais
                FROM contactos co 
                LEFT JOIN paises pa ON co.codpais=pa.codpais
                WHERE pa.codpais <> '' AND pa.codpais IS NOT NULL ";

        $result = $dataBase->select($sql);
        return (!empty($result[0]['codigo_pais']))? $result: [];
    }
    
    
    public function getProvincesClientsCompany(): array
    {
        $dataBase = new DataBase();
        $sql = "SELECT DISTINCT provincia 
                FROM contactos 
                WHERE provincia <>'' AND provincia IS NOT NULL ";
        $result = $dataBase->select($sql);       
        return (!empty($result)) ? $result : [];
    }

}
