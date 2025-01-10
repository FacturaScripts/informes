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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportBoard extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $creationdate;

    /** @var bool */
    public $featured;

    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /** @var string */
    public $tag;

    public function addLine(Report $report, int $pos = 1): bool
    {
        // comprobamos si ya existe la linea
        $line = new ReportBoardLine();
        $where = [
            new DataBaseWhere('idreportboard', $this->id),
            new DataBaseWhere('idreport', $report->id),
        ];
        if ($line->loadFromCode('', $where)) {
            return false;
        }

        // la añadimos
        $line->idreport = $report->id;
        $line->idreportboard = $this->id;
        $line->sort = $pos;
        return $line->save();
    }

    public function clear()
    {
        parent::clear();
        $this->creationdate = Tools::dateTime();
        $this->featured = false;
    }

    public function getLines(): array
    {
        $where = [new DataBaseWhere('idreportboard', $this->id)];
        $orderBy = ['sort' => 'ASC'];
        return ReportBoardLine::all($where, $orderBy, 0, 0);
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'name';
    }

    public static function tableName(): string
    {
        return 'reports_boards';
    }

    public function test(): bool
    {
        // escapamos el html
        $this->name = Tools::noHtml($this->name);
        $this->tag = Tools::noHtml($this->tag);

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListReport?activetab=List'): string
    {
        return parent::url($type, $list);
    }
}
