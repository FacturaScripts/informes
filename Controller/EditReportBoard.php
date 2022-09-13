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

    public function getLines(): array
    {
        $model = $this->getModel();
        return $model->getLines();
    }

    public function getModelClassName(): string
    {
        return 'ReportBoard';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'reports-board';
        $data['icon'] = 'fas fa-chalkboard';
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        // desactivamos el botón de imprimir
        $this->setSettings($this->getMainViewName(), 'btnPrint', false);

        // añadimos las pestañas
        $this->createViewsBoard();
        $this->createViewsReport();
    }

    protected function createViewsBoard(string $viewName = 'ReportBoard')
    {
        $this->addHtmlView($viewName, $viewName, 'ReportBoard', 'reports-board', 'fas fa-chalkboard');
    }

    protected function createViewsReport(string $viewName = 'EditListReport')
    {
        $this->addEditListView($viewName, 'ReportBoardLine', 'charts', 'fas fa-chart-pie');

        // ponemos la vista compacta
        $this->views[$viewName]->setInLine(true);
    }

    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        switch ($viewName) {
            case 'EditListReport':
                $code = $this->getViewModelValue($mvn, 'id');
                $where = [new DataBaseWhere('idreportboard', $code)];
                $orderBy = ['sort' => 'ASC'];
                $view->loadData('', $where, $orderBy);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
