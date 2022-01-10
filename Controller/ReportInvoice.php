<?php
/**
 * Copyright (C) 2019-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Proveedor;

/**
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportInvoice extends Controller
{
    public function getPageData() {
        $data = parent::getPageData();
        $data["menu"] = "reports";
        $data["title"] = "invoice-report";
        $data["icon"] = "fas fa-file-invoice";
        return $data;
    }

    public function loadAgents()
    {
        $html = '';
        foreach (CodeModel::all('agentes', 'codagente', 'nombre', true) as $row) {
            $html .= '<option value="' . $row->code . '">' . $row->description . '</option>';
        }
        return $html;
    }

    public function loadCountries()
    {
        $html = '';
        foreach (CodeModel::all('paises', 'codpais', 'nombre', true) as $row) {
            $html .= '<option value="' . $row->code . '">' . $row->description . '</option>';
        }
        return $html;
    }

    public function loadDivisas()
    {
        $html = '';
        foreach (CodeModel::all('divisas', 'coddivisa', 'descripcion', false) as $row) {
            $html .= '<option value="' . $row->code . '">' . $row->description . '</option>';
        }
        return $html;
    }

    public function loadPayments()
    {
        $html = '';
        foreach (CodeModel::all('formaspago', 'codpago', 'descripcion', true) as $row) {
            $html .= '<option value="' . $row->code . '">' . $row->description . '</option>';
        }
        return $html;
    }

    public function loadSeries()
    {
        $html = '';
        foreach (CodeModel::all('series', 'codserie', 'descripcion', true) as $row) {
            $html .= '<option value="' . $row->code . '">' . $row->description . '</option>';
        }
        return $html;
    }

    public function loadWarehouses()
    {
        $html = '';
        foreach (CodeModel::all('almacenes', 'codalmacen', 'nombre', true) as $row) {
            $html .= '<option value="' . $row->code . '">' . $row->description . '</option>';
        }
        return $html;
    }
    
    public function privateCore(&$response, $user, $permissions) {
        parent::privateCore($response, $user, $permissions);
        $this->execPreviousAction($this->request->get('action', ''));
    }

    protected function autocompleteCustomerAction()
    {
        $this->setTemplate(false);

        $list = [];
        $cliente = new Cliente();
        $query = $this->request->get('query');
        foreach ($cliente->codeModelSearch($query, 'codcliente') as $value) {
            $list[] = [
                'key' => $this->toolBox()->utils()->fixHtml($value->code),
                'value' => $this->toolBox()->utils()->fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => $this->toolBox()->i18n()->trans('no-data')];
        }

        $this->response->setContent(\json_encode($list));
    }

    protected function autocompleteSupplierAction()
    {
        $this->setTemplate(false);

        $list = [];
        $proveedor = new Proveedor();
        $query = $this->request->get('query');
        foreach ($proveedor->codeModelSearch($query, 'codproveedor') as $value) {
            $list[] = [
                'key' => $this->toolBox()->utils()->fixHtml($value->code),
                'value' => $this->toolBox()->utils()->fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => $this->toolBox()->i18n()->trans('no-data')];
        }

        $this->response->setContent(\json_encode($list));
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'autocomplete-customer':
                return $this->autocompleteCustomerAction();

            case 'autocomplete-supplier':
                return $this->autocompleteSupplierAction();

            case 'load-provincies':
                return $this->loadProvincies();
        }
    }

    protected function loadProvincies()
    {
        $this->setTemplate(false);

        $html = '';
        $codpais = $this->request->get('codpais', '');
        if (!empty($codpais)) {
            $where = [new DataBaseWhere('codpais', $codpais)];
            foreach (CodeModel::all('provincias', 'idprovincia', 'provincia', true, $where) as $row) {
                $html .= '<option value="' . $row->code . '">' . $row->description . '</option>';
            }
        }

        if (empty($html)) {
            $html = '<option value="">------</option>';
        }

        $this->response->setContent(json_encode($html));
    }
}
