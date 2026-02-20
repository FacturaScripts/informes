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
        $chartId = 'chart' . $num;
        $chartHeight = $height > 0 ? $height : 350;

        $series = [];
        foreach ($data['datasets'] as $dataset) {
            $series[] = [
                'name' => $dataset['label'],
                'data' => $dataset['data']
            ];
        }

        return '<div id="' . $chartId . '"></div>'
            . '<script>'
            . 'var options' . $num . ' = {'
            . '  series: ' . json_encode($series) . ','
            . '  chart: {'
            . '    type: "area",'
            . '    stacked: false,'
            . '    height: ' . $chartHeight . ','
            . '    zoom: {'
            . '      type: "x",'
            . '      enabled: true,'
            . '      autoScaleYaxis: true'
            . '    },'
            . '    toolbar: {'
            . '      autoSelected: "zoom"'
            . '    }'
            . '  },'
            . '  dataLabels: {'
            . '    enabled: false'
            . '  },'
            . '  stroke: {' // más info: https://apexcharts.com/docs/options/stroke/
            . '    curve: "straight"' // straight, smooth, monotoneCubic, stepline, linestep
            . '  },'
            . '  markers: {'
            . '    size: 0,'
            . '  },'
            . '  title: {'
            . '    text: "' . $this->report->name . '",'
            . '    align: "left"'
            . '  },'
            . '  fill: {'
            . '    type: "gradient",'
            . '    gradient: {'
            . '      shadeIntensity: 1,'
            . '      inverseColors: false,'
            . '      opacityFrom: 0.5,'
            . '      opacityTo: 0,'
            . '      stops: [0, 90, 100]'
            . '    },'
            . '  },'
            . '  yaxis: {'
            . '    title: {'
            . '      text: "' . $this->report->ycolumn . '"'
            . '    },'
            . '  },'
            . '  xaxis: {'
            . '    categories: ' . json_encode($data['labels']) . ','
            . '  }'
            . '};'
            . 'var chart' . $num . ' = new ApexCharts(document.querySelector("#' . $chartId . '"), options' . $num . ');'
            . 'chart' . $num . '.render();'
            . '</script>';
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
