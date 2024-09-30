<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Dinamic\Model\CodeModel;

/**
 * Description of ListReport
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ListReport extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'charts';
        $data['icon'] = 'fa-solid fa-chart-pie';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewsReport();
        $this->createViewsReportBoard();
    }

    protected function createViewsReport(string $viewName = 'ListReport'): void
    {
        $this->addView($viewName, 'Report', 'charts', 'fa-solid fa-chart-pie')
            ->addOrderBy(['name'], 'name')
            ->addSearchFields(['name', 'table', 'xcolumn', 'ycolumn']);

        // añadimos filtro de tipos
        $types = $this->codeModel->all('reports', 'type', 'type');
        $this->addFilterSelect($viewName, 'type', 'type', 'type', $types);

        // añadimos filtro de tablas
        $tables = [new CodeModel()];
        foreach ($this->dataBase->getTables() as $table) {
            $tables[] = new CodeModel(['code' => $table, 'description' => $table]);
        }
        $this->addFilterSelect($viewName, 'table', 'table', 'table', $tables);

        // añadimos filtro de columna x
        $columnX = $this->codeModel->all('reports', 'xcolumn', 'xcolumn');
        $this->addFilterSelect($viewName, 'xcolumn', 'x-column', 'xcolumn', $columnX);

        // añadimos filtro de operación x
        $operationX = $this->codeModel->all('reports', 'xoperation', 'xoperation');
        $this->addFilterSelect($viewName, 'xoperation', 'x-operation', 'xoperation', $operationX);

        // añadimos filtro de columna y
        $columnY = $this->codeModel->all('reports', 'ycolumn', 'ycolumn');
        $this->addFilterSelect($viewName, 'ycolumn', 'y-column', 'ycolumn', $columnY);

        // añadimos filtro de operación y
        $operationY = $this->codeModel->all('reports', 'yoperation', 'yoperation');
        $this->addFilterSelect($viewName, 'yoperation', 'y-operation', 'yoperation', $operationY);
    }

    protected function createViewsReportBoard(string $viewName = 'ListReportBoard'): void
    {
        $this->addView($viewName, 'ReportBoard', 'reports-board', 'fa-solid fa-chalkboard')
            ->addOrderBy(['name'], 'name')
            ->addSearchFields(['name']);
    }
}
