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
 * Model for amounts balance
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class ReportAmount extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $channel;

    /** @var string */
    public $endcodsubaccount;

    /** @var string */
    public $enddate;

    /** @var int */
    public $id;

    /** @var int */
    public $idcompany;

    /** @var bool */
    public $ignoreclosure;

    /** @var bool */
    public $ignoreregularization;

    /** @var int */
    public $level;

    /** @var string */
    public $name;

    /** @var string */
    public $startcodsubaccount;

    /** @var string */
    public $startdate;

    public function clear()
    {
        parent::clear();
        $this->enddate = date('31-12-Y');
        $this->idcompany = $this->toolBox()->appSettings()->get('default', 'idempresa');
        $this->ignoreclosure = true;
        $this->ignoreregularization = true;
        $this->level = 0;
        $this->startdate = date('01-01-Y');
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
        return 'reports_amounts';
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

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return parent::url($type, 'ListReportAccounting?activetab=' . $list);
    }
}
