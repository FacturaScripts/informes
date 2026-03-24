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

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Informes\ReportGenerator;
use FacturaScripts\Dinamic\Model\CodeModel;
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

    protected function createViews(): void
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

        // botón del asistente
        $this->addButton($viewName, [
            'action' => $this->url() . '?action=custom-board-assistant',
            'confirm' => false,
            'icon' => 'fa-solid fa-hat-wizard',
            'label' => 'wizard',
            'type' => 'link'
        ]);
    }

    protected function execPreviousAction($action): bool
    {
        switch ($action) {
            case 'custom-board-assistant':
                return $this->showCustomBoardAssistant();

            case 'generate-boards':
                return $this->generateBoardsAction();

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
     * Devuelve las tablas que tienen al menos una columna de tipo date o timestamp.
     */
    protected function getTablesWithDate(): array
    {
        $tablesWithDate = [];
        foreach ($this->dataBase->getTables() as $table) {
            if (!empty($this->getDateColumns($table))) {
                $tablesWithDate[] = $table;
            }
        }

        return $tablesWithDate;
    }

    /**
     * Devuelve las columnas date/timestamp de una tabla concreta.
     */
    protected function getDateColumns(string $table): array
    {
        $colsWithDate = [];
        foreach ($this->dataBase->getColumns($table) as $colName => $colData) {
            $type = strtolower($colData['type']);

            /**
             * En mariadb:
             *  - date
             *  - timestamp
             * En postgresql:
             *  - date
             *  - timestamp without time zone
             */
            if (in_array($type, ['date', 'timestamp', 'timestamp without time zone'])) {
                $colsWithDate[] = $colName;
            }
        }

        return $colsWithDate;
    }

    /**
     * Crea una pizarra con gráficas temporales para una tabla y una columna de fecha válidas.
     *
     * La pizarra generada incluye informes por semana, mes y año; y también por hora
     * cuando la columna es de tipo timestamp.
     *
     * Devuelve la pizarra creada o null si falla. La transacción debe gestionarse desde fuera.
     */
    protected function createDateBoard(string $table, string $column): ?ReportBoard
    {
        $board = new ReportBoard();
        $board->name = Tools::trans('report-board-title-date', ['%column%' => $column, '%table%' => $table]);
        if (false === $board->save()) {
            Tools::log()->error('error-creating-report-board');
            return null;
        }

        $reportsToCreate = [];

        // revisar si es timestamp para añadir la hora
        $cols = $this->db()->getColumns($table);
        $colType = strtolower($cols[$column]['type'] ?? '');
        if (in_array($colType, ['timestamp', 'timestamp without time zone'])) {
            $reportsToCreate['HOUR'] = Tools::trans('report-by-hour', ['%column%' => $column, '%table%' => $table]);
        }

        // añadir las semanas, meses y años
        $reportsToCreate['WEEK'] = Tools::trans('report-by-week', ['%column%' => $column, '%table%' => $table]);
        $reportsToCreate['MONTHS'] = Tools::trans('report-by-month', ['%column%' => $column, '%table%' => $table]);
        $reportsToCreate['YEAR'] = Tools::trans('report-by-year', ['%column%' => $column, '%table%' => $table]);

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
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('permission-denied');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $table = $this->request->queryOrInput('selectedTable', '');
        $column = $this->request->queryOrInput('selectedColumn', '');

        if (empty($table) || empty($column)) {
            Tools::log()->error('missing-parameters');
            return false;
        }

        if (false === $this->db()->tableExists($table)) {
            Tools::log()->error('table-not-found', ['%tableName%' => $table]);
            return false;
        }

        // revisar que exista la columna
        $tableCols = $this->db()->getColumns($table);
        if (false === array_key_exists($column, $tableCols)) {
            Tools::log()->error('column-not-found', ['%columnName%' => $column, '%tableName%' => $table]);
            return false;
        }

        // revisar que sea tipo date la columna
        if (false === in_array(strtolower($tableCols[$column]['type']), ['date', 'timestamp', 'timestamp without time zone'])) {
            Tools::log()->error('column-not-date', ['%columnName%' => $column, '%tableName%' => $table]);
            return false;
        }

        // Está todo ok, procesar la petición con los datos recibidos.
        $this->db()->beginTransaction();

        $newBoard = $this->createDateBoard($table, $column);
        if (null === $newBoard) {
            $this->db()->rollback();
            Tools::log()->error('error-creating-report-board');
            return false;
        }

        // aceptar la transacción y redirigir al panel
        $this->db()->commit();

        $this->redirect($newBoard->url('edit'));

        return true;
    }

    /**
     * Muestra el asistente para generar pizarras predeterminadas o crear una pizarra temporal.
     *
     * Si se ha seleccionado una tabla, carga sus columnas de fecha para el segundo paso.
     */
    protected function showCustomBoardAssistant(): bool
    {
        // preparar el formulario
        $tables = $this->getTablesWithDate();

        // tabla de tablas
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
            $columns = $this->getDateColumns($selectedTable);
            $this->twigData['columns'] = array_combine($columns, $columns); // combine para que sean mismo key/value
        }

        $this->setTemplate('CustomBoardAssistant');

        return true;
    }
}
