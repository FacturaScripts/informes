<?php declare(strict_types=1);
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
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Description of EditReport
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditReport extends EditController
{

    public function getModelClassName(): string
    {
        return 'Report';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'report';
        $data['icon'] = 'fas fa-chart-pie';
        return $data;
    }

    protected function createViews(): void
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
        $this->addHtmlView('chart', 'Master/htmlChart', 'Report', 'chart');
        $this->createViewFilterLines();

        // disable print button
        $this->setSettings($this->getMainViewName(), 'btnPrint', false);
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view): void
    {
        $mainViewName = $this->getMainViewName();

        switch ($viewName) {
            case 'EditReportFilterLine':
                $tableName = $this->views[$this->getMainViewName()]->model->table;
                $columns = empty($tableName) || !$this->dataBase->tableExists($tableName) ? [] : array_keys($this->dataBase->getColumns($tableName));
                sort($columns);

                $columnTable = $this->views[$viewName]->columnForField('tablecolumn');
                if ($columnTable && $columnTable->widget->getType() === 'select') {
                    $columnTable->widget->setValuesFromArray($columns);
                }

                $code = $this->getViewModelValue($mainViewName, 'id');
                $where = [new DataBaseWhere('idreport', $code)];
                $orderBy = ['tablecolumn' => 'ASC'];
                $view->loadData('', $where, $orderBy);
                break;

            default:
                parent::loadData($viewName, $view);
                $this->loadWidgetValues($viewName);
                break;
        }
    }

    protected function loadWidgetValues(string $viewName): void
    {
        $columnTable = $this->views[$viewName]->columnForField('table');
        if ($columnTable && $columnTable->widget->getType() === 'select') {
            $columnTable->widget->setValuesFromArray($this->dataBase->getTables());
        }

        $tableName = $this->views[$viewName]->model->table;
        $columns = empty($tableName) || !$this->dataBase->tableExists($tableName) ? [] : array_keys($this->dataBase->getColumns($tableName));
        sort($columns);

        $columnX = $this->views[$viewName]->columnForField('xcolumn');
        if ($columnX && count($columns) > 0 && $columnX->widget->getType() === 'select') {
            $columnX->widget->setValuesFromArray($columns);
        }

        $columnY = $this->views[$viewName]->columnForField('ycolumn');
        if ($columnY && count($columns) > 0 && $columnY->widget->getType() === 'select') {
            $columnY->widget->setValuesFromArray($columns, false, true);
        }
    }

    protected function createViewFilterLines(string $viewName = 'EditReportFilterLine'): void
    {
        $this->addEditListView($viewName, 'ReportFilterLine', 'filters', 'fas fa-filter');

        // ponemos la vista compacta
        $this->views[$viewName]->setInLine(true);
    }

    protected function execAfterAction($action): void
    {
        // Activamos la vista del grafico siempre, para que se carge correctamente el gráfico
        // y no haya que recargar la página para que se muestren los datos en el gráfico.
        $this->active = 'chart';

        parent::execAfterAction($action);
    }
}
