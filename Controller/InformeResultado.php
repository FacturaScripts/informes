<?php
/**
 * Copyright (C) 2019-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Plugins\Informes\Lib\Informes\SummaryResultResultReport;
use FacturaScripts\Plugins\Informes\Lib\Informes\SalesResultResultReport;
use FacturaScripts\Plugins\Informes\Lib\Informes\PurchasesResultResultReport;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;

/**
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class InformeResultado extends Controller
{
    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    public function getPageData()
    {
        $data = parent::getPageData();
        $data["menu"] = "reports";
        $data["title"] = "result-report";
        $data["icon"] = "fas fa-poll";
        return $data;
    }

    public function loadYears(): string
    {
        $html = '';
        $modelEjerc = new Ejercicio();
        foreach ($modelEjerc->all([], ['fechainicio' => 'desc'], 0, 0) as $row) {
            $emp = new Empresa();
            $emp->loadFromCode($row->idempresa);
            $html .= '<option value="' . $row->codejercicio . '">'
                . $row->nombre . ' | ' . $emp->nombrecorto
                . '</option>';
        }
        return $html;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->execPreviousAction($this->request->get('action'));
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'load-summary':
                $this->loadSummary();
                break;
            case 'load-sales':
                $this->loadSales();
                break;
            case 'load-purchases':
                $this->loadPurchases();
                break;
        }
    }

    protected function loadPurchases()
    {
        $this->setTemplate(false);
        $content = [
            'purchases' => PurchasesResultResultReport::render($this->request->request->all()),
            'messages' => $this->toolBox()->log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function loadSales()
    {
        $this->setTemplate(false);
        $content = [
            'sales' => SalesResultResultReport::render($this->request->request->all()),
            'messages' => $this->toolBox()->log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function loadSummary()
    {
        $this->setTemplate(false);
        $content = [
            'summary' => SummaryResultResultReport::render($this->request->request->all()),
            'messages' => $this->toolBox()->log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }
}