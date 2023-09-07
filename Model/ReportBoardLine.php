<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportBoardLine extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $columns;

    /** @var int */
    public $height;

    /** @var int */
    public $id;

    /** @var int */
    public $idreport;

    /** @var int */
    public $idreportboard;

    /** @var int */
    public $sort;

    public function clear()
    {
        parent::clear();
        $this->columns = 6;
        $this->height = 250;
        $this->sort = $this->count() + 1;
    }

    public function getBoard(): ReportBoard
    {
        $board = new ReportBoard();
        $board->loadFromCode($this->idreportboard);
        return $board;
    }

    public function getReport(): Report
    {
        $report = new Report();
        $report->loadFromCode($this->idreport);
        return $report;
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public static function tableName(): string
    {
        return "reports_boards_lines";
    }
}
