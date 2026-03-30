<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2025-2026 Carlos García Gómez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Informes\Extension\Controller;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\Informes\Lib\Informes\ReportGenerator;
use FacturaScripts\Plugins\Informes\Model\ReportBoard;

class EditUser
{
    public function createViews(): Closure
    {
        return function() {
            $this->addListView('ListReportBoardUser', 'ReportBoardUser', 'reports-board', 'fa-solid fa-chalkboard')
                ->disableColumn('user-nick')
                ->setSettings('checkBoxes', false)
                ->setSettings('btnNew', false)
                ->setSettings('btnDelete', false);
        };
    }

    public function execAfterAction(): Closure
    {
        return function ($action) {
            if ('show-report' !== $action) {
                return;
            }

            // generemos los informes
            $code = $this->getModel()->primaryColumnValue();
            if (empty($code)) {
                Tools::log()->warning('no-user-code');
                return;
            }

            $new = ReportGenerator::generateForUser($code);
            Tools::log()->notice('items-added-correctly', ['%num%' => $new]);

            // buscamos la pizarra de este agente
            $tag = 'b-user-' . $code;
            $where = [Where::eq('tag', $tag)];
            foreach (ReportBoard::all($where) as $board) {
                $this->redirect($board->url());
                return;
            }

            // si no existe la pizarra, mostramos un mensaje
            Tools::log()->warning('report-not-found');
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            $mvn = $this->getMainViewName();

            switch ($viewName) {
                case 'ListReportBoardUser':
                    $code = $this->getViewModelValue($mvn, 'nick');
                    $where = [Where::eq('user_nick', $code)];
                    $view->loadData('', $where);
                    break;

                case $mvn:
                    // comprobamos si el agente existe
                    if ($view->model->exists()) {
                        // añadimos un botón para ver su informe
                        $this->addButton($mvn, [
                            'action' => 'show-report',
                            'icon' => 'fa-solid fa-chart-line',
                            'label' => 'report',
                        ]);
                    }
                    break;
            }
        };
    }
}
