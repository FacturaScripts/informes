<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2023-2026 Carlos García Gómez <carlos@facturascripts.com>
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

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PieChart extends Chart
{
    public function render(array $dataChart = []): string
    {
        $data = $this->getData();
        if (empty($data)) {
            return '';
        }

        $num = mt_rand();
        $chartId = 'chart' . $num;
        $chartHeight = isset($dataChart['height']) && $dataChart['height'] > 0 ? $dataChart['height'] : 350;

        return '<div id="' . $chartId . '"></div>'
            . '<script>'
            . 'var options' . $num . ' = {'
            . '  series: ' . json_encode($data['datasets'][0]['data']) . ','
            . '  colors: ' . json_encode($this->getColors(count($data['labels']))) . ','
            . '  chart: {'
            . '    type: "pie",'
            . '    height: ' . $chartHeight
            . '  },'
            . '  labels: ' . json_encode($data['labels']) . ','
            . '  responsive: [{'
            . '    breakpoint: 480,'
            . '    options: {'
            . '      chart: {'
            . '        width: 200'
            . '      },'
            . '      legend: {'
            . '        position: "bottom"'
            . '      }'
            . '    }'
            . '  }],'
            . '  title: {'
            . '    text: "' . $this->report->name . '",'
            . '    align: "center"'
            . '  }'
            . '};'
            . 'var chart' . $num . ' = new ApexCharts(document.querySelector("#' . $chartId . '"), options' . $num . ');'
            . 'chart' . $num . '.render();'
            . '</script>';
    }

    protected function getColors(int $count): array
    {
        $colors = [
            '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd',
            '#8c564b', '#e377c2', '#7f7f7f', '#bcbd22', '#17becf',
            '#4e79a7', '#f28e2b', '#59a14f', '#e15759', '#edc948',
            '#b07aa1', '#76b7b2', '#ff9da7', '#9c755f', '#bab0ab',
        ];

        if ($count <= count($colors)) {
            return array_slice($colors, 0, $count);
        }

        for ($index = count($colors); $index < $count; $index++) {
            $hue = ($index * 47) % 360;
            $colors[] = sprintf('hsl(%d, 65%%, 55%%)', $hue);
        }

        return $colors;
    }

    protected function getData(): array
    {
        // obtenemos las distintas fuentes de datos
        $sources = $this->getDataSources();
        if (empty($sources)) {
            return [];
        }

        // agrupamos las etiquetas
        $labels = [];
        foreach ($sources as $source) {
            foreach ($source as $row) {
                if (!in_array($row['xcol'], $labels)) {
                    $labels[] = $row['xcol'];
                }
            }
        }
        sort($labels);

        // ahora agrupamos los datos
        $mix = [];
        $countSources = count($sources);
        foreach ($labels as $label) {

            // la etiqueta es la columna x
            $newItem = ['xcol' => $label];

            // rellenamos con ceros las columnas y
            for ($count = 1; $count <= $countSources; $count++) {
                $newItem['ycol' . $count] = 0;
            }

            // ahora consultamos las fuentes de datos
            $num = 1;
            foreach ($sources as $source) {
                foreach ($source as $row) {
                    if ($row['xcol'] === $label) {
                        $newItem['ycol' . $num] = $row['ycol'];
                    }
                }
                $num++;
            }

            $mix[$label] = $newItem;
        }

        // ahora preparamos los datos para el gráfico
        $datasets = [];
        foreach (array_keys($sources) as $pos => $label) {
            $num = 1 + $pos;
            $data = [];
            foreach ($mix as $row) {
                $data[] = is_numeric($row['ycol' . $num]) ?
                    round($row['ycol' . $num], 2) :
                    $row['ycol' . $num];
            }

            $datasets[] = ['label' => $label, 'data' => $data];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }
}
