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

/**
 * Description of AreaChart
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class AreaChart extends Chart
{
    public function render(int $height = 0): string
    {
        $data = $this->getData();
        if (empty($data)) {
            return '';
        }

        $num = mt_rand();
        $canvasId = 'chart' . $num;
        return '<canvas id="' . $canvasId . '"/>'
            . "<script>let ctx" . $num . " = document.getElementById('" . $canvasId . "').getContext('2d');"
            . "let myChart" . $num . " = new Chart(ctx" . $num . ", {
    type: 'line',
    data: {
        labels: ['" . implode("','", $data['labels']) . "'],
        datasets: [" . $this->renderDatasets($data['datasets']) . "]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});</script>";
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
                $xCol = $row['xcol'];
                if (!isset($mix[$xCol])) {
                    $labels[] = $xCol;

                    $newItem = ['xcol' => $xCol];
                    for ($count = 1; $count <= $countSources; $count++) {
                        $newItem['ycol' . $count] = 0;
                    }
                    $mix[$xCol] = $newItem;
                }

                $mix[$xCol]['ycol' . $num] = $row['ycol'];
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
