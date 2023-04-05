<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2017-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * Model for balances reports
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class ReportBalance extends ModelClass
{
    use ModelTrait;

    const TYPE_SHEET = 'balance-sheet';
    const TYPE_PROFIT = 'profit-and-loss';
    const TYPE_INCOME = 'income-and-expenses';
    const SUBTYPE_ABBREVIATED = 'abbreviated';
    const SUBTYPE_NORMAL = 'normal';
    const SUBTYPE_PYMES = 'pymes';

    /** @var int */
    public $channel;

    /** @var bool */
    public $comparative;

    /** @var string */
    public $enddate;

    /** @var int */
    public $id;

    /** @var int */
    public $idcompany;

    /** @var string */
    public $name;

    /** @var string */
    public $startdate;

    /** @var string */
    public $type;

    /** @var string */
    public $subtype;

    public function clear()
    {
        parent::clear();
        $this->comparative = true;
        $this->enddate = date('31-12-Y');
        $this->idcompany = $this->toolBox()->appSettings()->get('default', 'idempresa');
        $this->type = self::TYPE_SHEET;
        $this->startdate = date('01-01-Y');

        // si la empresa es persona física, el tipo de informe es abreviado, de lo contrario es PYMES
        $this->subtype = Empresas::get($this->idcompany)->personafisica ?
            self::SUBTYPE_ABBREVIATED :
            self::SUBTYPE_PYMES;
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
        return 'reports_balance';
    }

    public function test(): bool
    {
        $this->name = $this->toolBox()->utils()->noHtml($this->name);

        if (empty($this->idcompany)) {
            $this->toolBox()->i18nLog()->warning(
                'field-can-not-be-null',
                ['%fieldName%' => 'idempresa', '%tableName%' => static::tableName()]
            );
            return false;
        }

        if (strtotime($this->startdate) > strtotime($this->enddate)) {
            $params = ['%endDate%' => $this->startdate, '%startDate%' => $this->enddate];
            $this->toolBox()->i18nLog()->warning('start-date-later-end-date', $params);
            return false;
        }

        if (strtotime($this->startdate) < 1) {
            $this->toolBox()->i18nLog()->warning('date-invalid');
            return false;
        }

        return parent::test();
    }

    public static function typeList(): array
    {
        $i18n = self::toolBox()->i18n();
        return [
            ['value' => self::TYPE_SHEET, 'title' => $i18n->trans(self::TYPE_SHEET)],
            ['value' => self::TYPE_PROFIT, 'title' => $i18n->trans(self::TYPE_PROFIT)],
            ['value' => self::TYPE_INCOME, 'title' => $i18n->trans(self::TYPE_INCOME)]
        ];
    }

    public static function subtypeList(): array
    {
        $i18n = self::toolBox()->i18n();
        return [
            ['value' => self::SUBTYPE_ABBREVIATED, 'title' => $i18n->trans(self::SUBTYPE_ABBREVIATED)],
            ['value' => self::SUBTYPE_NORMAL, 'title' => $i18n->trans(self::SUBTYPE_NORMAL)]
        ];
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return parent::url($type, 'ListReportAccounting?activetab=' . $list);
    }
}
