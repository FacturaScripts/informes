<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2017-2024 Carlos García Gómez <carlos@facturascripts.com>
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
 * Modelo que define un código de balance y su estructura
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class BalanceCode extends ModelClass
{
    use ModelTrait;

    /** @var string modo de cálculo del saldo: positive, negative o según la naturaleza */
    public $calculation;

    /** @var string código del balance */
    public $codbalance;

    /** @var string descripción del nivel 1 */
    public $description1;

    /** @var string descripción del nivel 2 */
    public $description2;

    /** @var string descripción del nivel 3 */
    public $description3;

    /** @var string descripción del nivel 4 */
    public $description4;

    /** @var int clave primaria */
    public $id;

    /** @var string código del nivel 1 */
    public $level1;

    /** @var string código del nivel 2 */
    public $level2;

    /** @var string código del nivel 3 */
    public $level3;

    /** @var string código del nivel 4 */
    public $level4;

    /** @var string naturaleza del balance: A (activo) o P (pasivo) */
    public $nature;

    /** @var string subtipo de balance: normal, abbreviated o pymes */
    public $subtype;

    public function calculate(float $debe, float $haber): float
    {
        switch ($this->calculation) {
            case 'positive':
                return $debe - $haber;

            case 'negative':
                return $haber - $debe;

            default:
                return $this->nature === 'A' ?
                    $debe - $haber :
                    $haber - $debe;
        }
    }

    public function clear(): void
    {
        parent::clear();
        $this->nature = 'A';
        $this->subtype = 'normal';
    }

    public static function tableName(): string
    {
        return 'balance_codes';
    }

    public function test(): bool
    {
        // escapamos el html
        $this->calculation = Tools::noHtml($this->calculation);
        $this->codbalance = Tools::noHtml($this->codbalance);
        $this->description1 = Tools::noHtml($this->description1);
        $this->description2 = Tools::noHtml($this->description2);
        $this->description3 = Tools::noHtml($this->description3);
        $this->description4 = Tools::noHtml($this->description4);
        $this->level1 = Tools::noHtml($this->level1);
        $this->level2 = Tools::noHtml($this->level2);
        $this->level3 = Tools::noHtml($this->level3);
        $this->level4 = Tools::noHtml($this->level4);
        $this->nature = Tools::noHtml($this->nature);
        $this->subtype = Tools::noHtml($this->subtype);

        // comprobamos que tenga un código válido
        if (empty($this->codbalance) || 1 !== preg_match('/^[A-Z0-9_\+\.\-]{1,15}$/i', $this->codbalance)) {
            Tools::log()->error(
                'invalid-alphanumeric-code',
                ['%value%' => $this->codbalance, '%column%' => 'codbalance', '%min%' => '1', '%max%' => '15']
            );
            return false;
        }

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListReportAccounting?activetab=List'): string
    {
        return parent::url($type, $list);
    }
}
