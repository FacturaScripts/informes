<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2017-2026 Carlos García Gómez <carlos@facturascripts.com>
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
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\BalanceCode as DinBalanceCode;
use FacturaScripts\Dinamic\Model\Cuenta;

/**
 * Modelo que relaciona una cuenta contable con un código de balance
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class BalanceAccount extends ModelClass
{
    use ModelTrait;

    const RESTRICTION_CREDIT = 'haber';

    const RESTRICTION_DEBIT = 'debe';

    /** @var string código de la cuenta contable */
    public $codcuenta;

    /** @var string descripción de la cuenta contable */
    public $desccuenta;

    /** @var int clave primaria */
    public $id;

    /** @var int id del código de balance al que pertenece esta cuenta */
    public $idbalance;

    /** @var string restringe la cuenta a saldo deudor (RESTRICTION_DEBIT) o acreedor (RESTRICTION_CREDIT) */
    public $restriction;

    public function getBalanceCode(): BalanceCode
    {
        $balanceCode = new DinBalanceCode();
        $balanceCode->load($this->idbalance);
        return $balanceCode;
    }

    public function getCuenta(?string $codejercicio = null): Cuenta
    {
        $cuenta = new Cuenta();
        $where = [Where::eq('codcuenta', $this->codcuenta)];
        if ($codejercicio) {
            $where[] = Where::eq('codejercicio', $codejercicio);
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

    /**
     * Indica si la cuenta debe computar en este código de balance según el signo de su saldo.
     * Las cuentas de doble saldo (551, 5523, 5524, 5525) están asignadas a la vez a un código
     * de Activo y a uno de Pasivo, y solo deben sumar en el lado que corresponde a su signo.
     */
    public function matchesRestriction(float $debe, float $haber): bool
    {
        switch ($this->restriction) {
            case self::RESTRICTION_DEBIT:
                return $debe > $haber;

            case self::RESTRICTION_CREDIT:
                return $haber > $debe;

            default:
                return true;
        }
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
        $this->restriction = Tools::noHtml($this->restriction);

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return $this->idbalance ?
            $this->getBalanceCode()->url($type) :
            parent::url($type, $list);
    }
}
