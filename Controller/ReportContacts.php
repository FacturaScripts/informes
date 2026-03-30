<?php
/**
 * This file is part of Informes plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Plugins\Informes\Model\Report;
use FacturaScripts\Plugins\Informes\Lib\Informes\ContactsReport;

class ReportContacts extends Controller
{
    public $isEnabledCRM = false;

    public $charts = [];
    public $totals = [];
    public $countries = [];
    public $companyCountry = '';
    public $companyCountryCode = '';

    // extra data for view
    public $sources_periods = [];
    public $interests_periods = [];
    public $comparison = [];
    public $months_history = [];
    public $years_history = [];

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'contacts';
        $data['icon'] = 'fa-solid fa-address-book';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->isEnabledCRM = Plugins::isEnabled('CRM');

        // cargar datos
        $this->loadData();
        $this->totals = ContactsReport::summary();
        $this->countries = ContactsReport::geoDistribution();

        // charts
        $this->loadNewContactsByMonth();
        $this->loadNewContactsByYear();
        $this->loadContactsByProvince();

        if ($this->isEnabledCRM) {
            $this->loadSourcesChart();
            $this->loadInterestsChart();

            $this->sources_periods = ContactsReport::sourcesAnalysis();
            $this->interests_periods = ContactsReport::interestsAnalysis();
        }
    
        $this->comparison = ContactsReport::comparison12vsPrevious12();
        $this->months_history = ContactsReport::historyByMonths(12);
        $this->years_history = ContactsReport::historyByYears();
    }

    protected function loadData(): void
    {
        $this->companyCountryCode = Tools::settings('default', 'codpais', 'ESP');

        $country = new Pais();
        if ($country->load($this->companyCountryCode)) {
            $this->companyCountry = $country->nombre;
        } else {
            $this->companyCountry = $this->companyCountryCode;
        }
    }

    protected function loadNewContactsByMonth(): void
    {
        $report = new Report();
        $report->type = Report::DEFAULT_TYPE;
        $report->table = 'contactos';
        $report->xcolumn = 'fechaalta';
        $report->ycolumn = 'idcontacto';
        $report->xoperation = 'MONTHS';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');
        $report->addCustomFilter('fechaalta', '>=', '{-1 year}');
        $report->addCustomFilter('fechaalta', '<=', '{today}');

        $this->charts['newByMonths'] = $report;
    }

    protected function loadNewContactsByYear(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_BAR;
        $report->table = 'contactos';
        $report->xcolumn = 'fechaalta';
        $report->ycolumn = 'idcontacto';
        $report->xoperation = 'YEAR';
        $report->yoperation = 'COUNT';
        $report->addFieldXName('');

        $this->charts['newByYears'] = $report;
    }

    protected function loadContactsByProvince(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_TREE_MAP;
        $report->table = 'contactos c';
        $report->xcolumn = "COALESCE(NULLIF(c.provincia, ''), '" . Tools::trans('no-data') . "')";
        $report->ycolumn = 'c.idcontacto';
        $report->yoperation = 'COUNT';

        Report::activateAdvancedReport(true);
        $report->addCustomFilter('c.codpais', '=', $this->companyCountryCode);

        $this->charts['contactsByProvince'] = $report;
    }

    protected function loadSourcesChart(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_DOUGHNUT;
        $report->table = 'contactos c';
        $report->xcolumn = "COALESCE(NULLIF(f.nombre, ''), '" . Tools::trans('no-data') . "')";
        $report->ycolumn = 'c.idcontacto';
        $report->yoperation = 'COUNT';

        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN crm_fuentes2 f ON c.idfuente = f.id');

        $this->charts['sources'] = $report;
    }

    protected function loadInterestsChart(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_DOUGHNUT;
        $report->table = 'contactos c';
        $report->xcolumn = "COALESCE(NULLIF(i.nombre, ''), '" . Tools::trans('no-data') . "')";
        $report->ycolumn = 'c.idcontacto';
        $report->yoperation = 'COUNT';

        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN crm_intereses_contactos ic ON ic.idcontacto = c.idcontacto');
        $report->addCustomJoin('LEFT JOIN crm_intereses i ON ic.idinteres = i.id');

        $this->charts['interests'] = $report;
    }
}
