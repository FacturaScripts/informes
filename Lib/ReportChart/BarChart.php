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
 * Description of BarChart
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class BarChart extends AreaChart
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
            . '    height: ' . $chartHeight . ','
            . '    type: "bar",'
            . '  },'
            . '  plotOptions: {'
            . '    bar: {'
            . '      borderRadius: 10,'
            . '      dataLabels: {'
            . '        position: "top",'
            . '      },'
            . '    }'
            . '  },'
            . '  dataLabels: {'
            . '    enabled: true,'
            . '    offsetY: -20,'
            . '    style: {'
            . '      fontSize: "12px",'
            . '      colors: ["#304758"]'
            . '    }'
            . '  },'
            . '  xaxis: {'
            . '    categories: ' . json_encode($data['labels']) . ','
            . '    position: "top",'
            . '    axisBorder: {'
            . '      show: false'
            . '    },'
            . '    axisTicks: {'
            . '      show: false'
            . '    },'
            . '    crosshairs: {'
            . '      fill: {'
            . '        type: "gradient",'
            . '        gradient: {'
            . '          colorFrom: "#D8E3F0",'
            . '          colorTo: "#BED1E6",'
            . '          stops: [0, 100],'
            . '          opacityFrom: 0.4,'
            . '          opacityTo: 0.5,'
            . '        }'
            . '      }'
            . '    },'
            . '    tooltip: {'
            . '      enabled: true,'
            . '    }'
            . '  },'
            . '  yaxis: {'
            . '    axisBorder: {'
            . '      show: false'
            . '    },'
            . '    axisTicks: {'
            . '      show: false,'
            . '    },'
            . '    labels: {'
            . '      show: true'
            . '    }'
            . '  },'
            . '  title: {'
            . '    text: "' . $this->report->name . '",'
            . '    floating: true,'
            . '    offsetY: ' . ($chartHeight - 20) . ','
            . '    align: "center",'
            . '    style: {'
            . '      color: "#444"'
            . '    }'
            . '  }'
            . '};'
            . 'var chart' . $num . ' = new ApexCharts(document.querySelector("#' . $chartId . '"), options' . $num . ');'
            . 'chart' . $num . '.render();'
            . '</script>';
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
                backgroundColor: 'rgba(" . $color . ", 0.2)',
                borderColor: 'rgba(" . $color . ", 1)',
                borderWidth: 1
            }";
        }

        return implode(',', $items);
    }
}
