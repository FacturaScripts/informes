<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2017-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Informes\Model;

use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Model\Base\ReportAccounting;

/**
 * Model for ledger report
 *
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class ReportLedger extends ReportAccounting
{
    use ModelTrait;

    /**
     * @var string
     */
    public $endcodsubaccount;

    /**
     * @var int
     */
    public $endentry;

    /**
     * @var bool
     */
    public $groupingtype;

    /**
     * @var string
     */
    public $startcodsubaccount;

    /**
     * @var int
     */
    public $startentry;

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'reportsledger';
    }
}
