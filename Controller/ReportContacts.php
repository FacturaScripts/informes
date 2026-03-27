<?php
namespace FacturaScripts\Plugins\Informes\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Informes\Model\Report;
use FacturaScripts\Plugins\Informes\Lib\Informes\ContactsReport;
use FacturaScripts\Dinamic\Model\Pais;

class ReportContacts extends Controller
{
    public $charts = [];
    public $totals = [];
    public $countries = [];

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

        // cargar datos
        $this->loadData();
        $this->totals = ContactsReport::summary();
        $this->countries = ContactsReport::geoDistribution();

        // charts
        $this->loadNewContactsByMonth();
        $this->loadNewContactsByYear();
        $this->loadSourcesChart();
        $this->loadInterestsChart();

        // period breakdowns and comparison for view tables
        $this->sources_periods = ContactsReport::sourcesAnalysis();
        $this->interests_periods = ContactsReport::interestsAnalysis();
        $this->comparison = ContactsReport::comparison12vsPrevious12();
        $this->months_history = ContactsReport::historyByMonths(12);
        $this->years_history = ContactsReport::historyByYears();
    }

    protected function loadData(): void
    {
        // nothing special for now
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

    protected function loadSourcesChart(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_DOUGHNUT;
        $report->table = 'crm_fuentes2 f';
        $report->xcolumn = "COALESCE(f.nombre, '" . Tools::trans('no-data') . "')";
        $report->ycolumn = 'c.idcontacto';
        $report->yoperation = 'COUNT';

        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN contactos c ON c.idfuente = f.id');

        $this->charts['sources'] = $report;
    }

    protected function loadInterestsChart(): void
    {
        $report = new Report();
        $report->type = Report::TYPE_DOUGHNUT;
        $report->table = 'crm_intereses i';
        $report->xcolumn = "COALESCE(i.nombre, '" . Tools::trans('no-data') . "')";
        $report->ycolumn = 'ic.id';
        $report->yoperation = 'COUNT';

        Report::activateAdvancedReport(true);
        $report->addCustomJoin('LEFT JOIN crm_intereses_contactos ic ON ic.idinteres = i.id');

        $this->charts['interests'] = $report;
    }
}
