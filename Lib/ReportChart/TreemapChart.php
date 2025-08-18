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
        $data = $this->getData();
        if (empty($data)) {
            return '';
        }

        $num = mt_rand();
        $divId = 'treemap' . $num;
        return '<h2 class="h5 text-end pt-3 pe-3">' . $this->report->name . "</h2>\n"
            . '<div id="' . $divId . '" style="height: ' . max($height - 40, 210) . 'px;"></div>' . "\n"
            . "<script>
      google.charts.load('current', {'packages':['treemap']});
      google.charts.setOnLoadCallback(drawTreemap" . $num . ");
      function drawTreemap" . $num . "() {
        var data = google.visualization.arrayToDataTable([
          " . $this->renderTreemapData($data) . "
        ]);

        tree = new google.visualization.TreeMap(document.getElementById('" . $divId . "'));

        tree.draw(data, {
          minColor: '#009688',
          midColor: '#f7f7f7',
          maxColor: '#ee8100',
          headerHeight: 15,
          fontColor: 'black',
          showScale: true
        });

      }
    </script>";
    }

    protected function getData(): array
    {
        foreach($this->getDataSources() as $source) {
            return $source;
        }

        return [];
    }

    protected function renderTreemapData(array $data): string
    {
        $list = "['Column', 'Parent', 'Value', 'Color'],\n"
            . "['" . $this->report->xcolumn . "', null, 0, 0],\n";

        foreach ($data as $row) {
            $label = $row['xcol'];
            $value = round($row['ycol'], 2);
            $list .= "['" . $label . " (" . $value . ")', '" . $this->report->xcolumn
                . "', " . $value
                . ", " . $value . "],\n";
        }

        return $list;
    }
}
