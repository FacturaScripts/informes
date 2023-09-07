<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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
    public function render(): string
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
    type: 'pie',
    data: {
        labels: ['" . implode("','", $data['labels']) . "'],
        datasets: [" . $this->renderDatasets($data['datasets']) . "]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: {
            position: 'top',
        },
        title: {
            display: true,
            text: '" . $data['datasets'][0]['label'] . "'
        }
    }
});</script>";
    }

    protected function renderDatasets(array $datasets): string
    {
        $colors = [
            '255, 99, 132', '54, 162, 235', '255, 206, 86', '75, 192, 192', '153, 102, 255', '255, 159, 64',
            '230, 25, 75', '60, 180, 75', '255, 225, 25', '0, 130, 200', '245, 130, 48', '145, 30, 180',
            '70, 240, 240', '240, 50, 230', '210, 245, 60', '250, 190, 190', '0, 128, 128', '230, 190, 255',
            '170, 110, 40', '255, 250, 200', '128, 0, 0', '170, 255, 195', '128, 128, 0', '255, 215, 180',
        ];
        shuffle($colors);

        $items = [];
        $num = 0;
        foreach ($datasets as $dataset) {
            $backgroundColor = [];
            $borderColor = [];
            foreach ($dataset['data'] as $data) {
                $color = $colors[$num] ?? '255, 206, 86';
                $num++;

                $backgroundColor[] = "'rgb(" . $color . ")'";
                $borderColor[] = "'rgb(" . $color . ")'";
            }

            $items[] = "{
                label: '" . $dataset['label'] . "',
                data: [" . implode(",", $dataset['data']) . "],
                backgroundColor: [" . implode(", ", $backgroundColor) . "],
                borderColor: [" . implode(", ", $borderColor) . "],
                borderWidth: 1
            }";
        }

        return implode(',', $items);
    }
}