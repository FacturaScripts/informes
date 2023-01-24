<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelCore;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Model\CodeModel;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportTransport extends Controller
{
    /** string */
    public $codalmacen;

    /** string */
    public $codtrans;

    /** string */
    public $date;

    /** string */
    public $format;

    /** string */
    public $modelname;

    public function getFormatExport(): array
    {
        return [
            'PDF' => strtoupper($this->toolBox()->i18n()->trans('pdf')),
            'CSV' => strtoupper($this->toolBox()->i18n()->trans('csv')),
            'XLS' => strtoupper($this->toolBox()->i18n()->trans('xls'))
        ];
    }

    public function getModelType(): array
    {
        return [
            'AlbaranCliente' => $this->toolBox()->i18n()->trans('customer-delivery-notes'),
            'FacturaCliente' => $this->toolBox()->i18n()->trans('customer-invoices')
        ];
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["menu"] = "reports";
        $data["title"] = "report-transport";
        $data["icon"] = "fas fa-truck-loading";
        return $data;
    }

    public function getSelectValues($table, $code, $description, $empty = false): array
    {
        $values = $empty ? ['' => '------'] : [];
        foreach (CodeModel::all($table, $code, $description, $empty) as $row) {
            $values[$row->code] = $row->description;
        }
        return $values;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->date = $this->request->get('date', date('Y-m-d'));

        if ('export' === $this->request->request->get('action', '')) {
            $this->exportAction();
        }
    }

    protected function exportAction()
    {
        $data = $this->getReportData();
        if (empty($data)) {
            $this->toolBox()->i18nLog()->warning('no-data');
            return;
        }

        $this->setTemplate(false);
        $this->processLayout($data);
    }

    protected function getDocs(): array
    {
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->modelname;
        if (false === class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass();
        $where = [new DataBaseWhere('fecha', date('Y-m-d', strtotime($this->date)))];

        if (false === empty($this->codtrans)) {
            $where[] = new DataBaseWhere('codtrans', $this->codtrans);
        }

        if (false === empty($this->codalmacen)) {
            $where[] = new DataBaseWhere('codalmacen', $this->codalmacen);
        }

        return $model->all($where, [], 0, 0);
    }

    protected function getReportData(): array
    {
        $this->codalmacen = $this->request->request->get('codalmacen', '');
        $this->codtrans = $this->request->request->get('codtrans', '');
        $this->date = $this->request->request->get('date', '');
        $this->format = $this->request->request->get('format', '');
        $this->modelname = $this->request->request->get('modelname', '');

        if (empty($this->date) || empty($this->modelname)
            || false === array_key_exists($this->modelname, $this->getModelType())
            || false === array_key_exists($this->format, $this->getFormatExport())) {
            return [];
        }

        $docs = $this->getDocs();
        if (empty($docs)) {
            return [];
        }

        $data = [];
        foreach ($docs as $doc) {
            foreach ($doc->getLines() as $line) {
                if (isset($data[$line->referencia])) {
                    $data[$line->referencia]['cantidad'] += $line->cantidad;
                    if (false === strpos($doc->codigo, $data[$line->referencia]['codigo'])) {
                        $data[$line->referencia]['codigo'] .= ', ' . $doc->codigo;
                    }
                    continue;
                }

                $data[$line->referencia] = [
                    'cantidad' => $line->cantidad,
                    'codigo' => $doc->codigo,
                    'descripcion' => $line->descripcion,
                    'referencia' => $line->referencia
                ];
            }
        }

        return $data;
    }

    protected function processLayout(array &$lines)
    {
        $i18n = $this->toolBox()->i18n();
        $nameFile = $i18n->trans('carriers') . ' ' . $i18n->trans($this->modelname);
        $userDate = date(ModelCore::DATE_STYLE, strtotime($this->date));

        $exportManager = new ExportManager();
        $exportManager->setOrientation('landscape');
        $exportManager->newDoc($this->format, $nameFile . ' ' . $userDate);

        // si el formato es PDF, añadimos la tabla de información primero
        if ($this->format === 'PDF') {
            $exportManager->addTablePage([$i18n->trans('report'), $i18n->trans('date')], [
                [
                    $i18n->trans('report') => $nameFile,
                    $i18n->trans('date') => date($userDate),
                ]
            ]);
        }

        // añadimos las líneas de la tabla
        $headers = empty($lines) ? [] : array_keys(end($lines));
        $options = [
            'cantidad' => ['display' => 'right'],
            'codigo' => ['display' => 'left'],
            'descripcion' => ['display' => 'left'],
            'referencia' => ['display' => 'left']
        ];
        $exportManager->addTablePage($headers, $lines, $options);

        // si el formato no es PDF, añadimos la tabla de información al final
        if ($this->format != 'PDF') {
            $exportManager->addTablePage([$i18n->trans('report'), $i18n->trans('date')], [
                [
                    $i18n->trans('report') => $nameFile,
                    $i18n->trans('date') => date($userDate),
                ]
            ]);
        }

        $exportManager->show($this->response);
    }
}