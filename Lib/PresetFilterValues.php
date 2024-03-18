<?php declare(strict_types=1);

namespace FacturaScripts\Plugins\Informes\Lib;

use FacturaScripts\Dinamic\Model\Base\ModelCore;

class PresetFilterValues
{
    private $presets;

    public function __construct()
    {
        $this->presets = [
            'Fecha actual' => $this->date('now'),
            'Fecha de ayer' => $this->date('-1 day'),
            'Fecha de hace 7 días' => $this->date('-7 day'),
            'Fecha de hace 15 días' => $this->date('-15 day'),
            'Fecha de hace 1 mes' => $this->date('-1 month'),
            'Fecha de hace 3 meses' => $this->date('-3 months'),
            'Fecha de hace 6 meses' => $this->date('-6 months'),
            'Fecha de hace 1 año' => $this->date('-1 year'),
            'Fecha de hace 2 años' => $this->date('-2 years'),
            'Fecha y hora actual' => $this->dateTime('now'),
            'Fecha y hora de ayer' => $this->dateTime('-1 day'),
            'Fecha y hora de hace 7 días' => $this->dateTime('-7 day'),
            'Fecha y hora de hace 15 días' => $this->dateTime('-15 day'),
            'Fecha y hora de hace 1 mes' => $this->dateTime('-1 month'),
            'Fecha y hora de hace 3 meses' => $this->dateTime('-3 months'),
            'Fecha y hora de hace 6 meses' => $this->dateTime('-6 months'),
            'Fecha y hora de hace 1 año' => $this->dateTime('-1 year'),
            'Fecha y hora de hace 2 años' => $this->dateTime('-2 years'),
            'Hora actual' => $this->dateTime('now'),
            'Hace una hora' => $this->dateTime('-1 hour'),
        ];
    }

    public function all()
    {
        return array_keys($this->presets);
    }

    public function getValue(string $value)
    {
        return $this->presets[$value];
    }

    protected function date(string $time)
    {
        return date(ModelCore::DATE_STYLE, strtotime($time));
    }

    protected function dateTime(string $time)
    {
        return date(ModelCore::DATETIME_STYLE, strtotime($time));
    }
}
