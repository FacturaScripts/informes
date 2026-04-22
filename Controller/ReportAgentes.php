<?php

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Plugins\Informes\Model\Report;

class ReportAgentes extends Controller
{
    /** @var array lista de agentes [codagente => nombre] */
    public $agents = [];

    /** @var bool indica si el plugin Comisiones está activo */
    public $comisionesEnabled = false;

    /** @var Report gráfica de facturas por agente */
    public $facturas;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'agents-report';
        $data['icon'] = 'fa-solid fa-user-tie';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->comisionesEnabled = Plugins::isEnabled('Comisiones');

        $this->loadAgentes();
        $this->loadFacturas();
    }

    protected function loadAgentes(): void
    {
        $rows = $this->dataBase->select("SELECT codagente, nombre FROM agentes WHERE debaja = false ORDER BY nombre ASC");
        foreach ($rows as $row) {
            $this->agents[$row['codagente']] = $row['nombre'];
        }
    }

    protected function loadFacturas(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'facturascli f';
        $report->xcolumn = 'COALESCE(a.nombre, f.codagente)';
        $report->ycolumn = '*';
        $report->yoperation = 'COUNT';

        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN agentes a ON f.codagente = a.codagente');
        $report->addCustomFilter('f.codagente', 'IS NOT NULL', '');

        $this->facturas = $report;
    }
}