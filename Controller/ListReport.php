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
    /** variable para pasar la info a la vista twig */
    public array $twigData = [];

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
            'action' => 'generate-boards',
            'confirm' => true,
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'label' => 'generate',
        ]);

        // boton del asistente
        $this->addButton($viewName, [
            'action' => $this->url() . '?action=custom-board-assistant',
            'confirm' => false,
            'icon' => 'fa-solid fa-hat-wizard',
            'label' => 'generate-time-boards',
            'type' => 'link'
        ]);
    }

    protected function execPreviousAction($action)
    {

        switch($action) {
            case 'generate-boards':
                return $this->generateBoardsAction();
            case 'custom-board-assistant':
                return $this->showCustomBoardAssistant();
            case 'process-custom-board':
                return $this->processCustomBoardAction();
            default:
                return parent::execPreviousAction($action);
        }
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

    /**
     * Devuelve una lista con las tablas que tienen almenos una columna de tipo date o timestamp y array con las columnas
     * Ej:
     *     return ['nomTabla' => ['colA', 'colB', 'colC'], ...] 
    */
    private function getTablesWithDate(): array
    {
        $tablesWithDate = [];
        $tables = $this->dataBase->getTables();
        foreach ($tables as $table) {
            $colsWithDate = [];
            $cols = $this->dataBase->getColumns($table);
            foreach ($cols as $colName => $colData) {
                $type = strtolower($colData['type']);

                /**
                 * En la búsqueda mirando en los tipos que devuelve getColumns para todas las tablas he visto que;
                 * En mariadb (similar a sql) devuelve:
                 *      - date
                 *      - time
                 *      - timestamp
                 * En postgresql es diferente:
                 *      - date
                 *      - timestamp without time zone
                 *      - time without time zone
                 */
                if (in_array($type, ['date', 'timestamp', 'timestamp without time zone'])) {
                    $colsWithDate[] = $colName;
                }
            }

            if (count($colsWithDate) > 0) {
                $tablesWithDate[$table] = $colsWithDate;
            }
        }

        return $tablesWithDate;
    }

    protected function processCustomBoardAction(): bool
    {
        $table = $this->request->queryOrInput('selectedTable', '');
        $column = $this->request->queryOrInput('selectedColumn', '');

        if (empty($table) || empty($column)) {
            Tools::log()->error('missing-parameters');
            return false;
        }

        if (false === $this->dataBase->tableExists($table)) {
            Tools::log()->error('table-not-found', ['%tableName%' => $table]);
            return false;
        }

        // revisar que exista la columna
        $tableCols = $this->dataBase->getColumns($table);
        if (false === array_key_exists($column, $tableCols)) {
            Tools::log()->error('column-not-found', ['%columnName%' => $column, '%tableName%' => $table]);
            return false;
        }

        // revisar que sea tipo date la columna
        if (false === in_array(strtolower($tableCols[$column]['type']), ['date', 'timestamp', 'timestamp without time zone'])) {
            Tools::log()->error('column-not-date', ['%columnName%' => $column, '%tableName%' => $table]);
            return false;
        }

        // Logic to process the selection would go here
        // TODO: Realizar acción después de elegir
        Tools::log()->notice('Procesando tabla: ' . $table . ', columna: ' . $column);

        return true;
    }

    /**
     * Muestra el asistente para escoger columnas date y timestamp de las tablas
     * 
     * 2 fases:
     *  1. Tabla a escoger
     *  2. Columna de la tabla
     */
    protected function showCustomBoardAssistant()
    {
        // preparar el formulario
        $tablesWithDate = $this->getTablesWithDate();

        // tabla de tablas
        $tables = array_keys($tablesWithDate); // recoger solo claves
        $this->twigData['tables'] = array_combine($tables, $tables); // combine para que sean mismo key/value

        $selectedTable = $this->request->queryOrInput('selectedTable', '');
        if (!empty($selectedTable)) {
            // comprobar que la tabla existe
            if (false === $this->dataBase->tableExists($selectedTable)) {
                Tools::log()->error('table-not-found', ['%tableName%' => $selectedTable]);
                return false;
            }

            // asignar en el twig tabla seleccionada
            $this->twigData['selectedTable'] = $selectedTable;
            
            // mostrar columnas que son date o datetime
            $columns = $tablesWithDate[$selectedTable];
            $this->twigData['columns'] = array_combine($columns, $columns); // combine para que sean mismo key/value
        }

        $this->setTemplate('CustomBoardAssistant');
    }
}
