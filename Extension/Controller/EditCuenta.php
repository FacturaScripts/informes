<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class EditCuenta
{
    public function createViews(): Closure
    {
        return function () {
            $viewName = 'ListBalanceAccount';
            $this->addListView($viewName, 'BalanceAccount', 'balances', 'fas fa-book');

            // desactivamos los botones nuevo y eliminar
            $this->setSettings($viewName, 'btnNew', false);
            $this->setSettings($viewName, 'btnDelete', false);
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName != 'ListBalanceAccount') {
                return;
            }

            $code = $this->getViewModelValue('EditCuenta', 'codcuenta');
            $where = [new DataBaseWhere('codcuenta', $code)];
            $view->loadData('', $where);
        };
    }
}
