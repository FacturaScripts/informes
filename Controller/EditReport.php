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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Informes\Model\Report;
use FacturaScripts\Plugins\Informes\Model\ReportFilter;

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

    protected function copyReportAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-update');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        // cargamos el informe original
        $original = new Report();
        $code = $this->request->get('code');
        if (false === $original->loadFromCode($code)) {
            Tools::log()->warning('no-data-found');
            return true;
        }

        // creamos el nuevo informe
        $newReport = new Report();
        $newReport->name = $original->name . ' (copy)';
        $newReport->table = $original->table;
        $newReport->type = $original->type;
        $newReport->xcolumn = $original->xcolumn;
        $newReport->xoperation = $original->xoperation;
        $newReport->ycolumn = $original->ycolumn;
        $newReport->yoperation = $original->yoperation;
        if (false === $newReport->save()) {
            Tools::log()->warning('record-save-error');
            return true;
        }

        // copiamos los filtros
        foreach ($original->getFilters() as $filter) {
            $newFilter = clone $filter;
            $newFilter->id = null;
            $newFilter->id_report = $newReport->id;
            if (false === $newFilter->save()) {
                Tools::log()->warning('record-save-error');
                return true;
            }
        }

        $this->redirect($newReport->url() . '&action=save-ok');
        return true;
    }

    protected function createViews(): void
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        // pestaña de gráfico
        $this->addHtmlView('chart', 'Master/htmlChart', 'Report', 'chart', 'fa-solid fa-chart-line');

        // desactivamos los botones de imprimir
        $this->setSettings($this->getMainViewName(), 'btnPrint', false);

        // añadimos la pestaña de filtros
        $this->createViewsFilterLines();

        // añadimos la pestaña de informes relacionados
        $this->createViewsRelatedReports();
    }

    protected function createViewsFilterLines(string $viewName = 'EditReportFilter'): void
    {
        $this->addEditListView($viewName, 'ReportFilter', 'filters', 'fas fa-filter')
            ->setInLine(true);
    }

    protected function createViewsRelatedReports(string $viewName = 'ListReport'): void
    {
        $this->addListView($viewName, 'Report', 'related', 'fas fa-chart-pie')
            ->addOrderBy(['name'], 'name', 1)
            ->addSearchFields(['name']);

        // desactivamos los botones de añadir y eliminar
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
    }

    protected function execPreviousAction($action)
    {
        if ($action === 'copy') {
            return $this->copyReportAction();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view): void
    {
        $mainViewName = $this->getMainViewName();
        $id = $this->getViewModelValue($mainViewName, 'id');

        switch ($viewName) {
            case 'EditReportFilter':
                $tableName = $this->views[$this->getMainViewName()]->model->table;
                $columns = empty($tableName) || !$this->dataBase->tableExists($tableName) ? [] : array_keys($this->dataBase->getColumns($tableName));
                sort($columns);

                $columnTable = $view->columnForField('table_column');
                if ($columnTable && $columnTable->widget->getType() === 'select') {
                    $columnTable->widget->setValuesFromArray($columns);
                }

                /** AGREGAMOS OPCIONES AL DATALIST DE VALORES */
                $column = $view->columnForName('value');
                if ($column && $column->widget->getType() === 'datalist') {
                    $customValues = [];
                    foreach (ReportFilter::getDynamicValues() as $key => $valor) {
                        $customValues[] = ['value' => $key, 'title' => $key];
                    }
                    $column->widget->setValuesFromArray($customValues);
                }

                $where = [new DataBaseWhere('id_report', $id)];
                $orderBy = ['table_column' => 'ASC'];
                $view->loadData('', $where, $orderBy);
                break;

            case 'ListReport':
                $table = $this->getViewModelValue($mainViewName, 'table');
                $where = [
                    new DataBaseWhere('table', $table),
                    new DataBaseWhere('id', $id, '!='),
                ];
                $view->loadData('', $where);
                break;

            case $mainViewName:
                parent::loadData($viewName, $view);
                $this->loadWidgetValues($viewName);
                if ($view->model->exists()) {
                    $this->addButton($viewName, [
                        'action' => $view->model->url() . '&action=copy&multireqtoken=' . $this->multiRequestProtection->newToken(),
                        'icon' => 'fa-solid fa-scissors',
                        'label' => 'copy',
                        'type' => 'link',
                    ]);
                }
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    protected function loadWidgetValues(string $viewName): void
    {
        // añadimos valores al campo de tabla
        $columnTable = $this->views[$viewName]->columnForField('table');
        if ($columnTable && $columnTable->widget->getType() === 'select') {
            $columnTable->widget->setValuesFromArray($this->dataBase->getTables());
        }

        // añadimos valores a los campos de columnas
        $tableName = $this->views[$viewName]->model->table;
        $columns = empty($tableName) || !$this->dataBase->tableExists($tableName) ? [] : array_keys($this->dataBase->getColumns($tableName));
        sort($columns);

        // añadimos valores a los campos de columna X
        $columnX = $this->views[$viewName]->columnForField('xcolumn');
        if ($columnX && count($columns) > 0 && $columnX->widget->getType() === 'select') {
            $columnX->widget->setValuesFromArray($columns);
        }

        // añadimos valores a los campos de columna Y
        $columnY = $this->views[$viewName]->columnForField('ycolumn');
        if ($columnY && count($columns) > 0 && $columnY->widget->getType() === 'select') {
            $columnY->widget->setValuesFromArray($columns, false, true);
        }
    }
}
