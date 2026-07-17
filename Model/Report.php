<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2025 Carlos García Gómez <carlos@facturascripts.com>
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

/**
 * Description of Report
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class Report extends ModelClass
{
    use ModelTrait;

    const DEFAULT_TYPE = 'area';
    const TYPE_BAR = 'bar';
    const TYPE_DOUGHNUT = 'doughnut';
    const TYPE_LINE = 'area';
    const TYPE_MAP = 'map';
    const TYPE_PIE = 'pie';
    const TYPE_TREE_MAP = 'treemap';

    /** @var int id del informe con el que se compara este */
    public $compared;

    /** @var string fecha y hora de creación */
    public $creationdate;

    /** @var int clave primaria */
    public $id;

    /** @var string nombre del informe */
    public $name;

    /** @var string tabla de origen de los datos del informe */
    public $table;

    /** @var string etiqueta usada para agrupar informes */
    public $tag;

    /** @var string tipo de gráfico: bar, doughnut, area, map, pie o treemap */
    public $type;

    /** @var string columna o expresión SQL usada para los valores del eje X */
    public $xcolumn;

    /** @var string operación de transformación aplicada a la columna del eje X (p. ej. MONTHS, YEAR) */
    public $xoperation;

    /** @var string columna o expresión SQL usada para los valores del eje Y */
    public $ycolumn;

    /** @var string operación de agregación aplicada a la columna del eje Y (SUM, COUNT, AVERAGE...) */
    public $yoperation;

    /** @var bool indica si están activadas las funciones avanzadas del informe (SQL crudo, joins personalizados, filtros IN/NOT IN) */
    private static $advancedReport = false;

    /** @var array filtros añadidos en tiempo de ejecución mediante addCustomFilter(), no se persisten en base de datos */
    private $customFilters = [];

    /** @var array cláusulas JOIN en SQL crudo añadidas en tiempo de ejecución mediante addCustomJoin() */
    private $customJoins = [];

    /** @var string fragmento de SQL crudo añadido mediante addCustomSql() cuando el modo avanzado está activo */
    private $customSql = '';

    /** @var string alias usado para la columna X en los resultados, sobrescrito mediante addFieldXName() */
    private $fieldXName;

    public function addFieldXName(string $name): void
    {
        $this->fieldXName = $name;
    }

    public function addFilter(string $table_column, string $operator, string $value): bool
    {
        $filter = new ReportFilter();
        $filter->id_report = $this->id;
        $filter->operator = $operator;
        $filter->table_column = $table_column;
        $filter->value = $value;
        return $filter->save();
    }

    public function addCustomFilter(string $table_column, string $operator, string $value): bool
    {
        $filter = new ReportFilter();
        $filter->operator = $operator;
        $filter->table_column = $table_column;
        $filter->value = $value;

        if ($filter->testIN()) {
            $this->customFilters[] = $filter;
            return true;
        }

        return false;
    }

    public function addCustomJoin(string $joinClause): bool
    {
        if (static::getAdvancedReport()) {
            $this->customJoins[] = $joinClause;
            return true;
        }

        return false;
    }

    public function addCustomSql(string $sql): bool
    {
        if (static::getAdvancedReport()) {
            $this->customSql = $sql;
            return true;
        }

        return false;
    }

    public static function activateAdvancedReport(bool $status): void
    {
        static::$advancedReport = $status;
    }

    public static function getAdvancedReport(): bool
    {
        return static::$advancedReport;
    }

    public function getJoins(): array
    {
        return $this->customJoins;
    }

    public function getCustomSql(): string
    {
        return $this->customSql;
    }

    public function clear(): void
    {
        parent::clear();
        $this->creationdate = Tools::dateTime();
        $this->type = self::DEFAULT_TYPE;
    }

    public function getChart()
    {
        $className = 'FacturaScripts\\Dinamic\\Lib\\ReportChart\\' . ucfirst($this->type) . 'Chart';
        if (empty($this->type) || false === class_exists($className)) {
            return '';
        }

        return new $className($this);
    }

    public function getFiledXName(): string
    {
        return $this->fieldXName ?: $this->xcolumn;
    }

    public function getFilters(): array
    {
        $where = [Where::eq('id_report', $this->id)];
        $filters = array_merge($this->customFilters, ReportFilter::all($where));

        if (empty($filters)) {
            return [];
        }

        // ordenar por la tabla
        usort($filters, function (ReportFilter $a, ReportFilter $b) {
            return strcmp($a->table_column, $b->table_column);
        });

        return $filters;
    }

    public function getSqlFilters(): string
    {
        $filters = $this->getFilters();
        if (count($filters) === 0) {
            return '';
        }

        $where = [];
        foreach ($filters as $filter) {
            if ($filter->operator === 'IS NULL') {
                $where[] = Where::isNull($filter->table_column);
                continue;
            }

            if ($filter->operator === 'IS NOT NULL') {
                $where[] = Where::isNotNull($filter->table_column);
                continue;
            }

            if (static::getAdvancedReport()) {
                if (in_array($filter->operator, ['IN', 'NOT IN'])) {
                    $where[] = Where::column($filter->table_column, $filter->value, $filter->operator);
                    continue;
                }
            }

            // convertimos los valores dinámicos
            $filter->value = ReportFilter::getDynamicValue($filter->value);

            $where[] = Where::column($filter->table_column, $filter->value, $filter->operator);
        }

        return ' WHERE ' . Where::multiSql($where);
    }

    public function getTable(): string
    {
        $data = explode(' ', $this->table);
        return $data[0] ?? '';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'name';
    }

    public static function tableName(): string
    {
        return 'reports';
    }

    public function test(): bool
    {
        $this->name = Tools::noHtml($this->name);
        $this->table = Tools::noHtml($this->table);
        $this->tag = Tools::noHtml($this->tag);
        $this->type = Tools::noHtml($this->type);
        $this->xcolumn = Tools::noHtml($this->xcolumn);
        $this->xoperation = Tools::noHtml($this->xoperation);
        $this->ycolumn = Tools::noHtml($this->ycolumn);
        $this->yoperation = Tools::noHtml($this->yoperation);
        return parent::test();
    }
}
