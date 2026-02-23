<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2024-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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
            '{now}' => date('Y-m-d H:i:s'),
            '{-1 hour}' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            '{-6 hours}' => date('Y-m-d H:i:s', strtotime('-6 hours')),
            '{-12 hours}' => date('Y-m-d H:i:s', strtotime('-12 hours')),
            '{-24 hours}' => date('Y-m-d H:i:s', strtotime('-24 hours')),
            '{today}' => date('Y-m-d'),
            '{-1 day}' => date('Y-m-d', strtotime('-1 day')),
            '{-7 days}' => date('Y-m-d', strtotime('-7 days')),
            '{-15 days}' => date('Y-m-d', strtotime('-15 days')),
            '{-1 month}' => date('Y-m-d', strtotime('-1 month')),
            '{-3 months}' => date('Y-m-d', strtotime('-3 months')),
            '{-6 months}' => date('Y-m-d', strtotime('-6 months')),
            '{-1 year}' => date('Y-m-d', strtotime('-1 year')),
            '{-2 years}' => date('Y-m-d', strtotime('-2 years')),
            '{first day of this month}' => date('Y-m-01'),
            '{first day of last month}' => date('Y-m-01', strtotime('-1 month')),
            '{first day of this year}' => date('Y') . '-01-01',
            '{first day of last year}' => date('Y', strtotime('-1 year')) . '-01-01',
            '{last day of this month}' => date('Y-m-t'),
            '{last day of last month}' => date('Y-m-t', strtotime('-1 month')),
            '{last day of this year}' => date('Y') . '-12-31',
            '{last day of last year}' => date('Y', strtotime('-1 year')) . '-12-31',
        ];
    }

    public function install(): string
    {
        new Report();
        return parent::install();
    }

    public static function tableName(): string
    {
        return "reports_filters";
    }

    public function test(): bool
    {
        $this->table_column = Tools::noHtml($this->table_column);
        $this->value = Tools::noHtml($this->value);
        return $this->testIN() && parent::test();
    }

    public function testIN(): bool
    {
        // si el tipo de operador es IN o NOT IN, debe estar activo el flag activeIN
        // si no, no se permitirá guardar el filtro
        if (in_array($this->operator, ['IN', 'NOT IN']) && !Report::getAdvancedReport()) {
            return false;
        }

        return true;
    }
}
