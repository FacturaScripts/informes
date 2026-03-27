<?php
namespace FacturaScripts\Plugins\Informes\Lib\Informes;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Informes\Model\Report;

class ContactsReport
{
    protected static function db(): ?DataBase
    {
        return new DataBase();
    }

    public static function summary(): array
    {
        $db = self::db();
        if (null === $db) {
            return [];
        }

        $out = [];
        $out['total'] = (int)$db->select("SELECT COUNT(*) as total FROM contactos;")[0]['total'];
        $out['verified'] = (int)$db->select("SELECT COUNT(*) as total FROM contactos WHERE verificado = " . $db->var2str(true) . ";")[0]['total'];
        $out['verified_perc'] = $out['total'] > 0 ? round(($out['verified'] * 100) / $out['total'], 2) : 0;
        $out['marketing'] = (int)$db->select("SELECT COUNT(*) as total FROM contactos WHERE admitemarketing = " . $db->var2str(true) . ";")[0]['total'];
        $out['marketing_perc'] = $out['total'] > 0 ? round(($out['marketing'] * 100) / $out['total'], 2) : 0;

        return $out;
    }

    public static function historyByMonths(int $months = 12): array
    {
        $db = self::db();
        if (null === $db) {
            return [];
        }

        $sql = "SELECT DATE_FORMAT(fechaalta, '%Y-%m') as ym, COUNT(*) as total
                FROM contactos
                WHERE fechaalta >= DATE_SUB(CURDATE(), INTERVAL " . (int)$months . " MONTH)
                GROUP BY ym ORDER BY ym ASC;";

        return $db->select($sql);
    }

    public static function historyByYears(): array
    {
        $db = self::db();
        if (null === $db) {
            return [];
        }

        $sql = "SELECT DATE_FORMAT(fechaalta, '%Y') as y, COUNT(*) as total
                FROM contactos
                GROUP BY y ORDER BY y ASC;";

        return $db->select($sql);
    }

    public static function comparison12vsPrevious12(): array
    {
        $db = self::db();
        if (null === $db) {
            return [];
        }

        $last12 = self::historyByMonths(12);

        $sqlPrev = "SELECT DATE_FORMAT(fechaalta, '%Y-%m') as ym, COUNT(*) as total
                FROM contactos
                WHERE fechaalta >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)
                  AND fechaalta < DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY ym ORDER BY ym ASC;";

        $prev12 = $db->select($sqlPrev);

        return ['last12' => $last12, 'prev12' => $prev12];
    }

    public static function sourcesAnalysis(): array
    {
        $db = self::db();
        if (null === $db) {
            return [];
        }

        $periods = [30, 90, 365];
        $result = [];

        foreach ($periods as $days) {
            $sql = "SELECT f.id, f.nombre, COUNT(c.idcontacto) AS total
                    FROM crm_fuentes2 f
                    LEFT JOIN contactos c ON c.idfuente = f.id AND c.fechaalta >= DATE_SUB(CURDATE(), INTERVAL " . (int)$days . " DAY)
                    GROUP BY f.id, f.nombre ORDER BY total DESC;";
            $result[$days] = $db->select($sql);
        }

        return $result;
    }

    public static function interestsAnalysis(): array
    {
        $db = self::db();
        if (null === $db) {
            return [];
        }

        $periods = [30, 90, 365];
        $result = [];

        foreach ($periods as $days) {
            $sql = "SELECT i.id, i.nombre, COUNT(ic.id) AS total
                    FROM crm_intereses i
                    LEFT JOIN crm_intereses_contactos ic ON ic.idinteres = i.id AND ic.fecha >= DATE_SUB(CURDATE(), INTERVAL " . (int)$days . " DAY)
                    GROUP BY i.id, i.nombre ORDER BY total DESC;";
            $result[$days] = $db->select($sql);
        }

        return $result;
    }

    public static function geoDistribution(): array
    {
        $db = self::db();
        if (null === $db) {
            return [];
        }

        $sql = "SELECT c.codpais, p.codiso, p.nombre, COUNT(*) as total
                FROM contactos c
                LEFT JOIN paises p ON c.codpais = p.codpais
                GROUP BY c.codpais, p.codiso, p.nombre ORDER BY total DESC;";

        return $db->select($sql);
    }
}
