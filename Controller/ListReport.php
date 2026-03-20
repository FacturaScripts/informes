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
use FacturaScripts\Dinamic\Model\Report;
use FacturaScripts\Dinamic\Model\ReportBoard;

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

    /**
     * Crea una pizarra con varias gráficas relacionadas con un campo tipo fecha o timestamp.
     * Se tiene que pasar una tabla y fecha válidos.
     * Devuelve el código del tablero si está ok o false.
     * Se debe usar una transaction desde fuera (por si no se crea correctamente).
     * 
     * El nombre de la pizarra es "Tablero de $column sobre el campo de fecha $table."
     * Se crearán Informes con altura 250 y ancho 6 en la pizarra con los siguientes nombres:
     *  - "$tabla, $campo / hora" (Si es timestamp)
     *  - "$tabla, $campo / semana"
     *  - "$tabla, $campo / mese"
     *  - "$tabla, $campo / año"
     */
    public function createDateBoard(string $table, string $column): bool|ReportBoard
    {
        $board = new ReportBoard();
        $board->name = Tools::lang()->trans('report-board-title-date', ['%column%' => $column, '%table%' => $table]);
        if (false === $board->save()) {
            Tools::log()->error('error-creating-report-board');
            return false;
        }

        $reportsToCreate = [];

        // revisar si es timestamp para añadir la hora
        $cols = $this->dataBase->getColumns($table);
        $colType = strtolower($cols[$column]['type'] ?? '');
        if (in_array($colType, ['timestamp', 'timestamp without time zone'])) {
            $reportsToCreate['HOUR'] = Tools::lang()->trans('report-by-hour', ['%column%' => $column, '%table%' => $table]);
        }

        // añadir las semanas, meses y años
        $reportsToCreate['WEEK'] = Tools::lang()->trans('report-by-week', ['%column%' => $column, '%table%' => $table]);
        $reportsToCreate['MONTHS'] = Tools::lang()->trans('report-by-month', ['%column%' => $column, '%table%' => $table]);
        $reportsToCreate['YEAR'] = Tools::lang()->trans('report-by-year', ['%column%' => $column, '%table%' => $table]);

        $pos = 1;
        foreach ($reportsToCreate as $xOp => $name) {
            $report = new Report();
            $report->name = $name;
            $report->table = $table;
            $report->xcolumn = $column;
            $report->xoperation = $xOp;
            $report->ycolumn = '';
            $report->yoperation = '';
            $report->type = Report::TYPE_BAR;

            if ($report->save()) {
                $board->addLine($report, $pos++);
            }
        }

        Tools::log()->notice('report-board-title-date-created', ['%column%' => $column, '%table%' => $table]);
        return $board;
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

        // Está todo ok, procesar la petición con los tados recibidos:
        $db = $this->db();
        $db->beginTransaction();
        $newBoard = $this->createDateBoard($table, $column);
        if (false === $newBoard) {
            $db->rollback();
            Tools::log()->error('error-creating-report-board');
            return false;
        }

        // aceptar la transacción y redirigir al panel
        $db->commit();
        $this->redirect($newBoard->url('edit'));

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
