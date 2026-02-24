<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Report;
use FacturaScripts\Dinamic\Model\ReportFilter;

/**
 * Description of EditReport
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditReport extends EditController
{
    /** @var array */
    private static $aliasesTables = [];

    public static function addAliasTable(string $table, string $alias): void
    {
        static::$aliasesTables[$table] = $alias;
    }

    public function getModelClassName(): string
    {
        return 'Report';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'report';
        $data['icon'] = 'fa-solid fa-chart-pie';
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
        if (false === $original->load($code)) {
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
        $this->addEditListView($viewName, 'ReportFilter', 'filters', 'fa-solid fa-filter')
            ->setInLine(true);
    }

    protected function createViewsRelatedReports(string $viewName = 'ListReport'): void
    {
        $this->addListView($viewName, 'Report', 'related', 'fa-solid fa-chart-pie')
            ->addOrderBy(['name'], 'name', 1)
            ->addSearchFields(['name'])
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false);
    }

    protected function execPreviousAction($action)
    {
        if ($action === 'copy') {
            return $this->copyReportAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function getTables(): array
    {
        // comprobamos si cada tabla tiene alias, si no añadimos la tabla sin alias
        foreach ($this->dataBase->getTables() as $table) {
            // si ya tenemos una tabla con ese alias, no la añadimos
            if (in_array($table, array_keys(static::$aliasesTables))) {
                continue;
            }

            // añadimos la tabla con el mismo alias que el nombre de la tabla
            static::addAliasTable($table, $table);
        }

        // ordenamos las tablas por el valor
        uasort(static::$aliasesTables, function ($a, $b) {
            return strcmp($a, $b);
        });

        return static::$aliasesTables;
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view): void
    {
        $mvn = $this->getMainViewName();
        $id = $this->getViewModelValue($mvn, 'id');

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

                $where = [Where::eq('id_report', $id)];
                $orderBy = ['table_column' => 'ASC'];
                $view->loadData('', $where, $orderBy);
                break;

            case 'ListReport':
                $table = $this->getViewModelValue($mvn, 'table');
                $where = [
                    Where::eq('table', $table),
                    Where::notEq('id', $id),
                ];
                $view->loadData('', $where);
                break;

            case $mvn:
                $this->loadAliasTables();
                parent::loadData($viewName, $view);
                $this->loadWidgetValues($viewName);

                // si existe, añadimos un botón para copiar el informe
                if ($view->model->exists()) {
                    $this->addButton($viewName, [
                        'action' => $view->model->url() . '&action=copy&multireqtoken=' . $this->multiRequestProtection->newToken(),
                        'icon' => 'fa-solid fa-scissors',
                        'label' => 'copy',
                        'type' => 'link',
                    ]);
                    break;
                }

                // no existe, ocultamos algunas columnas
                $view->disableColumn('x-column')
                    ->disableColumn('x-operation')
                    ->disableColumn('y-column')
                    ->disableColumn('y-operation');
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    protected function loadAliasTables(): void
    {
        static::addAliasTable('agenciastrans', Tools::trans('carriers'));
        static::addAliasTable('agentes', Tools::trans('agents'));
        static::addAliasTable('albaranescli', Tools::trans('customer-delivery-notes'));
        static::addAliasTable('albaranesprov', Tools::trans('supplier-delivery-notes'));
        static::addAliasTable('almacenes', Tools::trans('warehouses'));
        static::addAliasTable('asientos', Tools::trans('accounting-entries'));
        static::addAliasTable('atributos', Tools::trans('attributes'));
        static::addAliasTable('atributos_valores', Tools::trans('attribute-values'));
        static::addAliasTable('ciudades', Tools::trans('cities'));
        static::addAliasTable('clientes', Tools::trans('customers'));
        static::addAliasTable('codigos_postales', Tools::trans('zip-codes'));
        static::addAliasTable('contactos', Tools::trans('contacts'));
        static::addAliasTable('cuentas', Tools::trans('accounting-accounts'));
        static::addAliasTable('cuentasesp', Tools::trans('special-accounts'));
        static::addAliasTable('diarios', Tools::trans('journals'));
        static::addAliasTable('ejercicios', Tools::trans('exercises'));
        static::addAliasTable('emails_sent', Tools::trans('emails-sent'));
        static::addAliasTable('empresas', Tools::trans('companies'));
        static::addAliasTable('fabricantes', Tools::trans('manufacturers'));
        static::addAliasTable('facturascli', Tools::trans('customer-invoices'));
        static::addAliasTable('facturasprov', Tools::trans('supplier-invoices'));
        static::addAliasTable('familias', Tools::trans('families'));
        static::addAliasTable('formaspago', Tools::trans('payment-methods'));
        static::addAliasTable('gruposclientes', Tools::trans('customer-groups'));
        static::addAliasTable('impuestos', Tools::trans('taxes'));
        static::addAliasTable('pages', Tools::trans('pages'));
        static::addAliasTable('paises', Tools::trans('countries'));
        static::addAliasTable('partidas', Tools::trans('accounting-items'));
        static::addAliasTable('pedidoscli', Tools::trans('customer-orders'));
        static::addAliasTable('pedidosprov', Tools::trans('supplier-orders'));
        static::addAliasTable('presupuestoscli', Tools::trans('customer-estimation'));
        static::addAliasTable('presupuestosprov', Tools::trans('supplier-estimations'));
        static::addAliasTable('productos', Tools::trans('products'));
        static::addAliasTable('productosprov', Tools::trans('supplier-products'));
        static::addAliasTable('proveedores', Tools::trans('suppliers'));
        static::addAliasTable('provincias', Tools::trans('provinces'));
        static::addAliasTable('puntos_interes_ciudades', Tools::trans('points-of-interest'));
        static::addAliasTable('regularizacionimpuestos', Tools::trans('vat-regularization'));
        static::addAliasTable('retenciones', Tools::trans('retentions'));
        static::addAliasTable('series', Tools::trans('series'));
        static::addAliasTable('stocks', Tools::trans('stocks'));
        static::addAliasTable('subcuentas', Tools::trans('subaccounts'));
        static::addAliasTable('tarifas', Tools::trans('rates'));
        static::addAliasTable('users', Tools::trans('users'));
        static::addAliasTable('variantes', Tools::trans('variants'));
        static::addAliasTable('work_events', Tools::trans('running-work-queue'));
    }

    protected function loadWidgetValues(string $viewName): void
    {
        // añadimos valores al campo de tabla
        $columnTable = $this->views[$viewName]->columnForField('table');
        if ($columnTable && $columnTable->widget->getType() === 'select') {
            $tables = [];
            foreach ($this->getTables() as $table => $alias) {
                $tables[] = ['value' => $table, 'title' => $alias];
            }
            $columnTable->widget->setValuesFromArray($tables);
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
