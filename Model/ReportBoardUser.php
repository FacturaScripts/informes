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

namespace FacturaScripts\Plugins\Informes\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Session;
use FacturaScripts\Dinamic\Model\ReportBoard as DinReportBoard;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportBoardUser extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $creation_date;

    /** @var int */
    public $id;

    /** @var int */
    public $id_report_board;

    /** @var string */
    public $last_nick;

    /** @var string */
    public $last_update;

    /** @var string */
    public $nick;

    /** @var string */
    public $user_nick;

    public function getReportBoard(): DinReportBoard
    {
        $reportBoard = new DinReportBoard();
        $reportBoard->load($this->id_report_board);
        return $reportBoard;
    }

    public function getUser(): User
    {
        $user = new User();
        $user->load($this->user_nick);
        return $user;
    }

    public function install(): string
    {
        new User();
        new ReportBoard();
        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'reports_boards_users';
    }

    public function test(): bool
    {
        $this->creation_date = $this->creation_date ?? Tools::dateTime();
        $this->nick = $this->nick ?? Session::user()->nick;
        return parent::test();
    }

    protected function saveUpdate(): bool
    {
        $this->last_nick = Session::user()->nick;
        $this->last_update = Tools::dateTime();
        return parent::saveUpdate();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        if ($type === 'auto') {
            return $this->getReportBoard()->url();
        }

        return parent::url($type, $list);
    }
}
