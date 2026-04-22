<?php

namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Plugins;

class ReportAgentes extends Controller
{
    /** @var array lista de agentes [codagente => nombre] */
    public $agents = [];

    /** @var bool indica si el plugin Comisiones está activo */
    public $comisionesEnabled = false;

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
    }
}