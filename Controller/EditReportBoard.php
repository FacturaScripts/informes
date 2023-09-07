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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

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
        $data['menu'] = 'reports';
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
        $this->createViewsLines();
    }

    protected function createViewsBoard(string $viewName = 'ReportBoard'): void
    {
        $this->addHtmlView($viewName, $viewName, 'ReportBoard', 'reports-board', 'fas fa-chalkboard');
    }

    protected function createViewsLines(string $viewName = 'EditReportBoardLine'): void
    {
        $this->addEditListView($viewName, 'ReportBoardLine', 'charts', 'fas fa-chart-pie');

        // ponemos la vista compacta
        $this->views[$viewName]->setInLine(true);
    }

    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        switch ($viewName) {
            case 'EditReportBoardLine':
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
