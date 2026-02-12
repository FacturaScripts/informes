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
 */

namespace FacturaScripts\Plugins\Informes\Lib\ReportChart;

class TreemapChart extends Chart
{
    public function render(int $height = 0): string
    {
        $sources = $this->getDataSources();
        if (empty($sources)) {
            return '';
        }

        $num = mt_rand();
        $chartId = 'chart' . $num;
        $chartHeight = $height > 0 ? $height : 350;

        $series = [];
        foreach ($sources as $name => $source) {
            $data = [];
            foreach ($source as $row) {
                $data[] = [
                    'x' => $row['xcol'],
                    'y' => round($row['ycol'], 2)
                ];
            }
            $series[] = [
                'name' => $name,
                'data' => $data
            ];
        }

        return '<div id="' . $chartId . '"></div>'
            . '<script>'
            . 'var options' . $num . ' = {'
            . '  series: ' . json_encode($series) . ','
            . '  legend: {'
            . '    show: false'
            . '  },'
            . '  chart: {'
            . '    height: ' . $chartHeight . ','
            . '    type: "treemap"'
            . '  },'
            . '  title: {'
            . '    text: "' . $this->report->name . '",'
            . '    align: "center"'
            . '  }'
            . '};'
            . 'var chart' . $num . ' = new ApexCharts(document.querySelector("#' . $chartId . '"), options' . $num . ');'
            . 'chart' . $num . '.render();'
            . '</script>';
    }

    protected function getData(): array
    {
        foreach ($this->getDataSources() as $source) {
            return $source;
        }

        return [];
    }
}
