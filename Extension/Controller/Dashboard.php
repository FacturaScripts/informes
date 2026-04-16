<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2026 Carlos García Gómez <carlos@facturascripts.com>
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
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\ReportBoardUser;

/**
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Dashboard
{
    public function getReportsBoardsUser(): Closure
    {
        return function() {
            $where = [Where::eq('user_nick', $this->user->nick)];
            return ReportBoardUser::all($where);
        };
    }
}
