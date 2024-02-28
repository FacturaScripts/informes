<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Informes\Lib\ReportChart;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Plugins\Informes\Model\Report;

/**
 * Description of AreaChart
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
abstract class Chart
{
    /** @var Report */
    protected $report;

    abstract public function render(int $height = 0): string;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    protected function getColors(int $num): array
    {
        $colors = [];
        for ($i = 0; $i < $num; $i++) {
            $option = mt_rand(0, 3);
            switch ($option) {
                case 0:
                    $colors[] = '0, ' . mt_rand(0, 255) . ', ' . mt_rand(0, 255);
                    break;

                case 1:
                    $colors[] = mt_rand(0, 255) . ', 0, ' . mt_rand(0, 255);
                    break;

                case 2:
                    $colors[] = mt_rand(0, 255) . ', ' . mt_rand(0, 255) . ', 0';
                    break;

                default:
                    $colors[] = mt_rand(0, 255) . ', ' . mt_rand(0, 255) . ', ' . mt_rand(0, 255);
                    break;
            }
        }

        return $colors;
    }

    protected function getData(): array
    {
        $sources = $this->getDataSources();
        if (empty($sources)) {
            return [];
        }

        $labels = [];

        // mix data of sources
        $mix = [];
        $num = 1;
        $countSources = count($sources);
        foreach ($sources as $source) {
            foreach ($source as $row) {
                $xcol = $row['xcol'];
                if (!isset($mix[$xcol])) {
                    $labels[] = $xcol;

                    $newItem = ['xcol' => $xcol];
                    for ($count = 1; $count <= $countSources; $count++) {
                        $newItem['ycol' . $count] = 0;
                    }
                    $mix[$xcol] = $newItem;
                }

                $mix[$xcol]['ycol' . $num] = $row['ycol'];
            }
            $num++;
        }

        sort($labels);
        ksort($mix);

        $datasets = [];
        foreach (array_keys($sources) as $pos => $label) {
            $num = 1 + $pos;
            $data = [];
            foreach ($mix as $row) {
                $data[] = round($row['ycol' . $num], 2);
            }

            $datasets[] = ['label' => $label, 'data' => $data];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }

    protected function getDataSources(): array
    {
        $dataBase = new DataBase();
        $sql = $this->getSql($this->report);
        if (empty($sql) || !$dataBase->tableExists($this->report->table)) {
            return [];
        }

        $sources = [];
        $sources[$this->report->name] = $dataBase->select($sql);

        $comparedReport = new Report();
        if (!empty($this->report->compared) && $comparedReport->loadFromCode($this->report->compared)) {
            $sources[$comparedReport->name] = $dataBase->select($this->getSql($comparedReport));
        }

        return $sources;
    }

    protected function getSql(Report $report): string
    {
        if (empty($report->table) || empty($report->xcolumn)) {
            return '';
        }

        return strtolower(FS_DB_TYPE) == 'postgresql' ?
            $this->getSqlPostgreSQL($report) :
            $this->getSqlMySQL($report);
    }

    protected function getSqlMySQL(Report $report): string
    {
        $xcol = $report->xcolumn;
        switch ($report->xoperation) {
            case 'DAY':
                $xcol = "DATE_FORMAT(" . $report->xcolumn . ", '%Y-%m-%d')";
                break;

            case 'WEEK':
                $xcol = "DATE_FORMAT(" . $report->xcolumn . ", '%Y-%u')";
                break;

            case 'MONTH':
                $xcol = "DATE_FORMAT(" . $report->xcolumn . ", '%m')";
                break;

            case 'MONTHS':
                $xcol = "DATE_FORMAT(" . $report->xcolumn . ", '%Y-%m')";
                break;

            case 'UNIXTIME_DAY':
                $xcol = "DATE_FORMAT(FROM_UNIXTIME(" . $report->xcolumn . "), '%Y-%m-%d')";
                break;

            case 'UNIXTIME_WEEK':
                $xcol = "DATE_FORMAT(FROM_UNIXTIME(" . $report->xcolumn . "), '%Y-%u')";
                break;

            case 'UNIXTIME_MONTH':
                $xcol = "DATE_FORMAT(FROM_UNIXTIME(" . $report->xcolumn . "), '%Y-%m')";
                break;

            case 'UNIXTIME_YEAR':
                $xcol = "DATE_FORMAT(FROM_UNIXTIME(" . $report->xcolumn . "), '%Y')";
                break;

            case 'YEAR':
                $xcol = "DATE_FORMAT(" . $report->xcolumn . ", '%Y')";
                break;
        }

        $ycol = empty($report->ycolumn) ? 'COUNT(*)' : 'SUM(' . $report->ycolumn . ')';

        $sql = 'SELECT ' . $xcol . ' as xcol, ' . $ycol . ' as ycol ';
        $sql .= 'FROM ' . $report->table . ' ';
        $sql .= $report->getSqlFilters();
        $sql .= 'GROUP BY xcol ORDER BY xcol ASC;';

        return $sql;
    }

    protected function getSqlPostgreSQL(Report $report): string
    {
        $xcol = $report->xcolumn;
        switch ($report->xoperation) {
            case 'DAY':
                $xcol = "to_char(" . $report->xcolumn . ", 'YY-MM-DD')";
                break;

            case 'WEEK':
                $xcol = "to_char(" . $report->xcolumn . ", 'YY-WW')";
                break;

            case 'MONTH':
                $xcol = "to_char(" . $report->xcolumn . ", 'MM')";
                break;

            case 'MONTHS':
                $xcol = "to_char(" . $report->xcolumn . ", 'YY-MM')";
                break;

            case 'UNIXTIME_DAY':
                $xcol = "to_char(FROM_UNIXTIME(" . $report->xcolumn . "), 'YY-MM-DD')";
                break;

            case 'UNIXTIME_WEEK':
                $xcol = "to_char(FROM_UNIXTIME(" . $report->xcolumn . "), 'YY-WW')";
                break;

            case 'UNIXTIME_MONTH':
                $xcol = "to_char(FROM_UNIXTIME(" . $report->xcolumn . "), 'YY-MM')";
                break;

            case 'UNIXTIME_YEAR':
                $xcol = "to_char(FROM_UNIXTIME(" . $report->xcolumn . "), 'YY')";
                break;

            case 'YEAR':
                $xcol = "to_char(" . $report->xcolumn . ", 'YY')";
                break;
        }

        $ycol = empty($report->ycolumn) ? 'COUNT(*)' : 'SUM(' . $report->ycolumn . ')';

        $sql = 'SELECT ' . $xcol . ' as xcol, ' . $ycol . ' as ycol ';
        $sql .= 'FROM ' . $report->table . ' ';
        $sql .= $report->getSqlFilters() . ' ';
        $sql .= 'GROUP BY xcol ORDER BY xcol ASC;';

        return $sql;
    }

    protected function renderDatasets(array $datasets): string
    {
        $colors = $this->getColors(count($datasets));

        $items = [];
        $num = 0;
        foreach ($datasets as $dataset) {
            $color = $colors[$num] ?? '255, 206, 86';
            $num++;

            $items[] = "{
                label: '" . $dataset['label'] . "',
                data: [" . implode(",", $dataset['data']) . "],
                backgroundColor: [
                    'rgba(" . $color . ", 0.2)'
                ],
                borderColor: [
                    'rgba(" . $color . ", 1)'
                ],
                borderWidth: 1
            }";
        }

        return implode(',', $items);
    }
}
