<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Lib\ReportChart\AreaChart;
use FacturaScripts\Plugins\Informes\Model\Report;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditReportBoard extends EditController
{

    public function getChart(Report $report): AreaChart
    {
        return new AreaChart($report);
    }

    public function getLines()
    {
        $model = $this->getModel();
        return $model->getLines();
    }

    public function getModelClassName(): string {
        return "ReportBoard";
    }

    public function getPageData(): array {
        $data = parent::getPageData();
        $data["title"] = "reports-board";
        $data["icon"] = "fas fa-project-diagram";
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
        $this->createViewsBoard();
        $this->createViewsReport();

        // disable print button
        $this->setSettings($this->getMainViewName(), 'btnPrint', false);
    }

    protected function createViewsBoard(string $viewName = 'ReportBoard')
    {
        $this->addHtmlView($viewName, $viewName, 'ReportBoard', 'reports-board', 'fas fa-project-diagram');
    }

    protected function createViewsReport(string $viewName = 'EditListReport')
    {
        $this->addEditListView($viewName, 'ReportBoardLine', 'reports', 'fas fa-chart-pie');
    }

    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        switch ($viewName) {
            case 'EditListReport':
                $idreportboard = $this->getViewModelValue($mvn, 'id');
                $where = [new DataBaseWhere('idreportboard', $idreportboard)];
                $order = ['sort' => 'ASC'];
                $view->loadData('', $where, $order);
                $view->idreportboard = $idreportboard;
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
