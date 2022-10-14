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

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class BalanceCode extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $codbalance;

    /** @var string */
    public $description1;

    /** @var string */
    public $description2;

    /** @var string */
    public $description3;

    /** @var string */
    public $description4;

    /** @var int */
    public $id;

    /** @var string */
    public $level1;

    /** @var string */
    public $level2;

    /** @var string */
    public $level3;

    /** @var string */
    public $level4;

    /** @var string */
    public $nature;

    /** @var string */
    public $subtype;

    public function clear()
    {
        parent::clear();
        $this->nature = 'A';
        $this->subtype = 'normal';
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'balance_codes';
    }

    public function test(): bool
    {
        // escapamos el html
        $this->codbalance = self::toolBox()::utils()::noHtml($this->codbalance);
        $this->description1 = self::toolBox()::utils()::noHtml($this->description1);
        $this->description2 = self::toolBox()::utils()::noHtml($this->description2);
        $this->description3 = self::toolBox()::utils()::noHtml($this->description3);
        $this->description4 = self::toolBox()::utils()::noHtml($this->description4);
        $this->level1 = self::toolBox()::utils()::noHtml($this->level1);
        $this->level2 = self::toolBox()::utils()::noHtml($this->level2);
        $this->level3 = self::toolBox()::utils()::noHtml($this->level3);
        $this->level4 = self::toolBox()::utils()::noHtml($this->level4);
        $this->nature = self::toolBox()::utils()::noHtml($this->nature);
        $this->subtype = self::toolBox()::utils()::noHtml($this->subtype);

        // comprobamos que tenga un código válido
        if (empty($this->codbalance) || 1 !== preg_match('/^[A-Z0-9_\+\.\-]{1,15}$/i', $this->codbalance)) {
            $this->toolBox()->i18nLog()->error(
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
