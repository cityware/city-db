<?php

namespace Cityware\Db\Adapter;

use \PDOException;

/**
 * Description of Adapter
 * @author fabricio.xavier
 */
class ExecutionPlan extends \PDO
{
    private $arrayTable = Array('Node Type', 'Startup Cost', 'Total Cost', 'Plan Rows', 'Plan Width', 'Relation Name', 'Index Name', 'Index Cond');
    /*
     private $arrayTable = Array('Node Type', 'Startup Cost', 'Total Cost', 'Plan Rows', 'Plan Width',
        'Parent Relationship', 'Relation Name', 'Sort Key', 'Join Type', 'Join Filter',
        'Scan Direction', 'Index Name', 'Index Cond', 'Filter', 'Alias', 'Recheck Cond',
        'Strategy', 'Subplan Name');
    */
    private $arrayTableValues;
    private $executiontime;

    public function __construct($param)
    {
        $dsn = "pgsql:host={$param['host']};port={$param['port']};dbname={$param['database']};user={$param['username']};password={$param['password']}";
        try {
            parent::__construct($dsn, $param['username'], $param['password'], $param['driver_options']);
        } catch (PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
        }
    }

    public function explain($query)
    {
        $return = Array();

        try {
            $this->prepare('BEGIN;')->execute();

            $start = $this->getmicrotime();
            $stmt3 = $this->prepare($query);
            $stmt3->execute();
            $end = $this->getmicrotime();
            $this->executiontime = $end - $start;

            $stmt = $this->prepare('EXPLAIN (FORMAT JSON)' . $query);
            $stmt->execute();
            $row = $stmt->fetch();

            $stmt2 = $this->prepare('EXPLAIN ' . $query);
            $stmt2->execute();
            $row2 = $stmt2->fetchAll();

            $this->prepare('ROLLBACK;')->execute();
        } catch (PDOException $exc) {
            echo $exc->getMessage();
            exit;
        }

        $string = "\n";
        foreach ($row2 as $value) {
            $string .= $value['QUERY PLAN'] . "\n";
        }

        $return['string'] = $string;
        $return['arrayObjectTree'] = (isset($row[0]) and !empty($row[0])) ? json_decode($row[0]) : array();

        $arrPlanTemp = Array((array) $return['arrayObjectTree'][0]->Plan);
        unset($arrPlanTemp[0]['Plans']);

        foreach ($this->arrayTable as $key => $value) {
            foreach ($arrPlanTemp as $key2 => $value2) {
                if (!isset($value2[$value])) {
                    $arrPlanTemp[$key2][$value] = '&nbsp;';
                } else {
                    if ($value == 'Sort Key') {
                        $arrPlanTemp[$key2][$value] = implode(', ', $value2[$value]);
                    }
                }
            }
        }

        # Passa pro proccess()
        $return['arrayObjectTable'] = $this->processDataArray($return['arrayObjectTree'][0]->Plan->Plans);

        foreach ($this->arrayTable as $key => $value) {
            foreach ($return['arrayObjectTable'] as $key2 => $value2) {
                if (!isset($value2[$value])) {
                    $return['arrayObjectTable'][$key2][$value] = '&nbsp;';
                } else {
                    if ($value == 'Sort Key') {
                        $return['arrayObjectTable'][$key2][$value] = implode(', ', $value2[$value]);
                    }
                }
            }
        }

        array_unshift($return['arrayObjectTable'], $arrPlanTemp[0]);

        $this->arrayTableValues = $return;

        return $return;
    }

    public function render()
    {
        $tableHeader = '<thead><tr>';
        foreach ($this->arrayTable as $value) {
            $tableHeader .= '<th>' . $value . '</th>'.PHP_EOL;
        }
        $tableHeader .= '</tr></thead>'.PHP_EOL;
        $tableBody = '<tbody>'.PHP_EOL;

        foreach ($this->arrayTableValues['arrayObjectTable'] as $value) {
            $tableBody .= '<tr>
                <td>' . $value['Node Type'] . '</td>
                <td>' . $value['Startup Cost'] . '</td>
                <td>' . $value['Total Cost'] . '</td>
                <td>' . $value['Plan Rows'] . '</td>
                <td>' . $value['Plan Width'] . '</td>
                <td>' . $value['Relation Name'] . '</td>
                <td>' . $value['Index Name'] . '</td>
                <td>' . $value['Index Cond'] . '</td></tr>'.PHP_EOL;
        }
        $tableBody .= '</tbody>';

        return '<table border="1" width="100%">'.$tableHeader.$tableBody."</table>";
    }

    /**
     * Função recursiva para percorrer a arvore de array
     * @param  array/object $arrInfo
     * @return array
     */
    public function processDataArray($arrInfo)
    {
        $arrData = array();
        foreach ($arrInfo as $linha) {
            if (isset($linha->Plans) && !empty($linha->Plans)) {
                $linhaTemp = (array) $linha;
                unset($linhaTemp['Plans']);
                $arrData[] = (array) ($linhaTemp);
                $arrX = ($this->processDataArray($linha->Plans));
                foreach ($arrX as $arr) {
                    $arrData[] = (array) $arr;
                }
            } else {
                $arrData[] = (array) ($linha);
            }
        }

        return ($arrData);
    }

    /**
     *
     * @param  type   $time
     * @return string
     */
    private function calcTime($time)
    {
        $stat = round($time * 100 / 10000, 3);
        $retorno = null;
        if ($stat <= 40) {
            $retorno = " Excelente ";
        } elseif (($stat > 40) && ($stat <= 70 )) {
            $retorno = " Bom ";
        } elseif (($stat > 70) && ($stat <= 98 )) {
            $retorno = " Regular ";
        } elseif ($stat > 98) {
            $retorno = " Ruim ";
        }

        return $retorno;
    }

    /**
     *
     * @return type
     */
    private function getmicrotime()
    {
        list($usec, $sec) = explode(" ", microtime());

        return ((float) $usec + (float) $sec);
    }

}
