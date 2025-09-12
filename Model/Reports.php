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

namespace FacturaScripts\Plugins\Informes\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * Description of Reports
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Reports extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $creationdate;

    /** @var int */
    public $id;

    /** @var string */
    public $table;

    /** @var string */
    public $column;

    public function clear(): void
    {
        parent::clear();
        $this->creationdate = Tools::dateTime();
    }

    public static function tableName(): string
    {
        return 'report';
    }

    public function test(): bool
    {
        $this->table = Tools::noHtml($this->table);
        $this->column = Tools::noHtml($this->column);

        return parent::test();
    }
}