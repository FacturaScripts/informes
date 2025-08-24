<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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
 *
 * @author Antonio Palma <desarrolloweb@antoniojosepalma.es>
 */

namespace FacturaScripts\Plugins\Informes\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportFilter extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var int */
    public $id_report;

    /** @var string */
    public $operator;

    /** @var string */
    public $table_column;

    /** @var string */
    public $value;

    public static function getDynamicValue(string $value): string
    {
        $values = self::getDynamicValues();
        return $values[trim($value)] ?? $value;
    }

    public static function getDynamicValues(): array
    {
        return [
            '{now}' => Tools::dateTime(),
            '{-1 hour}' => Tools::dateTime('-1 hour'),
            '{-6 hours}' => Tools::dateTime('-6 hours'),
            '{-12 hours}' => Tools::dateTime('-12 hours'),
            '{-24 hours}' => Tools::dateTime('-24 hours'),
            '{today}' => Tools::date(),
            '{-1 day}' => Tools::date('-1 day'),
            '{-7 days}' => Tools::date('-7 days'),
            '{-15 days}' => Tools::date('-15 days'),
            '{-1 month}' => Tools::date('-1 month'),
            '{-3 months}' => Tools::date('-3 months'),
            '{-6 months}' => Tools::date('-6 months'),
            '{-1 year}' => Tools::date('-1 year'),
            '{-2 years}' => Tools::date('-2 years'),
        ];
    }

    public static function tableName(): string
    {
        return "reports_filters";
    }

    public function test(): bool
    {
        // escapamos el html
        $this->table_column = Tools::noHtml($this->table_column);
        $this->value = Tools::noHtml($this->value);

        return parent::test();
    }
}
