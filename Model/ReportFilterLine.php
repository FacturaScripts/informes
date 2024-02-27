<?php declare(strict_types=1);
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
 * 
 * @author Antonio Palma <desarrolloweb@antoniojosepalma.es>
 */

namespace FacturaScripts\Plugins\Informes\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportFilterLine extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $tablecolumn;

    /** @var string */
    public $operator;

    /** @var string */
    public $value;

    /** @var int */
    public $idreport;

    public static function primaryColumn(): string
    {
        return "id";
    }

    public static function tableName(): string
    {
        return "reports_filters_lines";
    }
}
