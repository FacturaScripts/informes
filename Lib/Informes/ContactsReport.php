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
        $out['new_30_days'] = (int)$db->select("SELECT COUNT(*) as total FROM contactos WHERE fechaalta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY);")[0]['total'];
        $out['without_source'] = 0;
        if ($db->tableExists('crm_fuentes2')) {
            $out['without_source'] = (int)$db->select("SELECT COUNT(*) as total
                    FROM contactos c
                    LEFT JOIN crm_fuentes2 f ON c.idfuente = f.id
                    WHERE f.id IS NULL OR NULLIF(f.nombre, '') IS NULL;")[0]['total'];
        }
        $out['without_source_perc'] = $out['total'] > 0 ? round(($out['without_source'] * 100) / $out['total'], 2) : 0;
        $out['without_interests'] = 0;
        if ($db->tableExists('crm_intereses_contactos')) {
            $out['without_interests'] = (int)$db->select("SELECT COUNT(*) as total
                    FROM contactos c
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM crm_intereses_contactos ic
                        WHERE ic.idcontacto = c.idcontacto
                    );")[0]['total'];
        }
        $out['without_interests_perc'] = $out['total'] > 0 ? round(($out['without_interests'] * 100) / $out['total'], 2) : 0;

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
            $sql = "SELECT COALESCE(f.id, 0) AS id,
                           COALESCE(NULLIF(f.nombre, ''), " . $db->var2str(Tools::trans('no-data')) . ") AS nombre,
                           COUNT(c.idcontacto) AS total
                    FROM contactos c
                    LEFT JOIN crm_fuentes2 f ON c.idfuente = f.id
                    WHERE c.fechaalta >= DATE_SUB(CURDATE(), INTERVAL " . (int)$days . " DAY)
                    GROUP BY COALESCE(f.id, 0), COALESCE(NULLIF(f.nombre, ''), " . $db->var2str(Tools::trans('no-data')) . ")
                    ORDER BY total DESC;";
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
            $sql = "SELECT COALESCE(i.id, 0) AS id,
                           COALESCE(NULLIF(i.nombre, ''), " . $db->var2str(Tools::trans('no-data')) . ") AS nombre,
                           COUNT(c.idcontacto) AS total
                    FROM contactos c
                    LEFT JOIN crm_intereses_contactos ic ON ic.idcontacto = c.idcontacto
                    LEFT JOIN crm_intereses i ON ic.idinteres = i.id
                    WHERE c.fechaalta >= DATE_SUB(CURDATE(), INTERVAL " . (int)$days . " DAY)
                    GROUP BY COALESCE(i.id, 0), COALESCE(NULLIF(i.nombre, ''), " . $db->var2str(Tools::trans('no-data')) . ")
                    ORDER BY total DESC;";
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
