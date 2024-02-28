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

use FacturaScripts\Core\DataSrc\Paises;

class MapChart extends Chart
{
    public function render(int $height = 0): string
    {
        $data = $this->getData();
        if (empty($data)) {
            return '';
        }

        $num = mt_rand();
        $divId = 'treemap' . $num;
        return '<h2 class="h5 text-center pt-3 pr-3">' . $this->report->name . "</h2>\n"
            . '<div id="' . $divId . '" style="height: ' . max($height - 40, 210) . 'px;"></div>' . "\n"
            . "<script>
      google.charts.load('current', {
        'packages':['geochart'],
      });
      google.charts.setOnLoadCallback(drawRegionsMap" . $num . ");

      function drawRegionsMap" . $num . "() {
        var data = google.visualization.arrayToDataTable([
            " . $this->renderMapData($data) . "
        ]);

        var options = {};

        var chart = new google.visualization.GeoChart(document.getElementById('" . $divId . "'));

        chart.draw(data, options);
      }
    </script>";
    }

    protected function renderMapData(array $data): string
    {
        $list = "['Country', 'Popularity'],\n";
        foreach ($data['labels'] as $key => $label) {
            $list .= "['" . Paises::get($label)->codiso . "', " . $data['datasets'][0]['data'][$key] . "],\n";
        }
        return $list;
    }
}
