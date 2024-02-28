<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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
        $colors = $this->getColors(count($datasets[0]['data']));

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
