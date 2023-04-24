<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Informes\Lib\Informes\AccountResultReport;
use FacturaScripts\Plugins\Informes\Lib\Informes\FamilyResultReport;
use FacturaScripts\Plugins\Informes\Lib\Informes\PurchasesResultReport;
use FacturaScripts\Plugins\Informes\Lib\Informes\SalesResultReport;
use FacturaScripts\Plugins\Informes\Lib\Informes\SummaryResultReport;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportResult extends Controller
{
    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["menu"] = "reports";
        $data["title"] = "result-report";
        $data["icon"] = "fas fa-poll";
        return $data;
    }

    public function getYears(): string
    {
        $html = '';
        $model = new Ejercicio();
        foreach ($model->all([], ['fechainicio' => 'desc'], 0, 0) as $row) {
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
        $this->execPreviousAction($this->request->get('action', ''));
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'load-account':
                $this->loadAccount();
                break;

            case 'load-family':
                $this->loadFamily();
                break;

            case 'load-purchases':
                $this->loadPurchases();
                break;

            case 'load-sales':
                $this->loadSales();
                break;

            case 'load-summary':
                $this->loadSummary();
                break;
        }
    }

    protected function loadAccount()
    {
        $this->setTemplate(false);
        $content = [
            'codcuenta' => $this->request->request->get('parent_codcuenta'),
            'account' => AccountResultReport::render($this->request->request->all()),
            'messages' => $this->toolBox()->log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function loadFamily()
    {
        $this->setTemplate(false);
        $content = [
            'codfamilia' => $this->request->request->get('parent_codfamilia'),
            'family' => FamilyResultReport::render($this->request->request->all()),
            'messages' => $this->toolBox()->log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function loadPurchases()
    {
        $this->setTemplate(false);
        $content = [
            'purchases' => PurchasesResultReport::render($this->request->request->all()),
            'messages' => $this->toolBox()->log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function loadSales()
    {
        $this->setTemplate(false);
        $content = [
            'sales' => SalesResultReport::render($this->request->request->all()),
            'messages' => $this->toolBox()->log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function loadSummary()
    {
        $this->setTemplate(false);
        $content = [
            'summary' => SummaryResultReport::render($this->request->request->all()),
            'charts' => SummaryResultReport::$charts,
            'messages' => $this->toolBox()->log()->read('', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }
}