<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2017-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\BalanceCode as DinBalanceCode;
use FacturaScripts\Dinamic\Model\Cuenta;

/**
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class BalanceAccount extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $codcuenta;

    /** @var string */
    public $desccuenta;

    /** @var int */
    public $id;

    /** @var int */
    public $idbalance;

    public function getBalanceCode(): BalanceCode
    {
        $balanceCode = new DinBalanceCode();
        $balanceCode->load($this->idbalance);
        return $balanceCode;
    }

    public function getCuenta(?string $codejercicio = null): Cuenta
    {
        $cuenta = new Cuenta();
        $where = [new DataBaseWhere('codcuenta', $this->codcuenta)];
        if ($codejercicio) {
            $where[] = new DataBaseWhere('codejercicio', $codejercicio);
        }
        $orderBy = ['codejercicio' => 'DESC'];
        $cuenta->loadWhere($where, $orderBy);
        return $cuenta;
    }

    public function install(): string
    {
        // needed dependency
        new BalanceCode();

        return parent::install();
    }

    public static function tableName(): string
    {
        return 'balance_accounts';
    }

    public function test(): bool
    {
        if (empty($this->desccuenta)) {
            $this->desccuenta = $this->getCuenta()->descripcion;
        }

        // escapamos el html
        $this->codcuenta = Tools::noHtml($this->codcuenta);
        $this->desccuenta = Tools::noHtml($this->desccuenta);

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return $this->idbalance ?
            $this->getBalanceCode()->url($type) :
            parent::url($type, $list);
    }
}
