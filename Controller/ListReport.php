<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Lib\Informes\ReportGenerator;

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
            ->addOrderBy(['id', 'creationdate'], 'creation-date', 2)
            ->addSearchFields(['name', 'table', 'tag', 'xcolumn', 'ycolumn']);

        $types = $this->codeModel->all('reports', 'type', 'type');

        $tables = [new CodeModel()];
        foreach ($this->dataBase->getTables() as $table) {
            $tables[] = new CodeModel(['code' => $table, 'description' => $table]);
        }

        $columnX = $this->codeModel->all('reports', 'xcolumn', 'xcolumn');
        $operationX = $this->codeModel->all('reports', 'xoperation', 'xoperation');
        $columnY = $this->codeModel->all('reports', 'ycolumn', 'ycolumn');
        $operationY = $this->codeModel->all('reports', 'yoperation', 'yoperation');

        // filtros
        $this->listView($viewName)
            ->addFilterSelect('type', 'type', 'type', $types)
            ->addFilterSelect('table', 'table', 'table', $tables)
            ->addFilterSelect('xcolumn', 'x-column', 'xcolumn', $columnX)
            ->addFilterSelect('xoperation', 'x-operation', 'xoperation', $operationX)
            ->addFilterSelect('ycolumn', 'y-column', 'ycolumn', $columnY)
            ->addFilterSelect('yoperation', 'y-operation', 'yoperation', $operationY);

        // botones
        $this->addButton($viewName, [
            'action' => 'generate-boards',
            'confirm' => true,
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'label' => 'generate',
        ]);
    }

    protected function createViewsReportBoard(string $viewName = 'ListReportBoard'): void
    {
        $this->addView($viewName, 'ReportBoard', 'reports-board', 'fa-solid fa-chalkboard')
            ->addOrderBy(['featured', 'name'], 'name')
            ->addOrderBy(['featured', 'creationdate'], 'creation-date', 2)
            ->addSearchFields(['name', 'tag']);

        // botones
        $this->addButton($viewName, [
            'action' => 'EditReports',
            'icon' => 'fa-solid fa-plus',
            'label' => 'new-reports',
            'type' => 'link'
        ]);

        $this->addButton($viewName, [
            'action' => 'generate-boards',
            'confirm' => true,
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'label' => 'generate',
        ]);
    }

    protected function execPreviousAction($action)
    {
        if ('generate-boards' === $action) {
            return $this->generateBoardsAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function generateBoardsAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('permission-denied');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $total = ReportGenerator::generate();

        Tools::log()->notice('items-added-correctly', ['%num%' => $total]);
        return true;
    }
}
