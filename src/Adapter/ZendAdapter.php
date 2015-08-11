<?php

namespace Cityware\Db\Adapter;

use Zend\Db\Sql\Sql;
use Zend\Db\Sql\TableIdentifier;
use Zend\Db\Sql\Expression;
use Zend\Db\ResultSet\ResultSet;
use Zend\Config\Factory AS ZendConfigFile;
use Zend\Db\Metadata\Metadata AS zendMetadata;
use Zend\Session\Container as SessionContainer;
use Exception;

/**
 * Description of Adapter
 *
 * @author fabricio.xavier
 */
class ZendAdapter extends AdapterAbstract implements AdapterInterface {

    private $connAdapter = Array(), $varExecuteLog = false, $aSession = Array(), $varStatusTransaction = false,
            $varConfigAdapter = null;
    protected static $session, $varDebug, $varExplan, $serviceLocator, $resultSetPrototype;
    protected static $varSqlSelect = Array(),
            $varSqlSelectFromColumns = Array(),
            $varSqlSelectJoinColumns = Array(),
            $varSqlUpdate = Array(),
            $varSqlInsert = Array(),
            $varSqlFrom = Array(),
            $varSqlJoinUsing = Array(),
            $varSqlWhere = Array(),
            $varSqlGroupBy = Array(),
            $varSqlHaving = Array(),
            $varSqlOrderBy = Array(),
            $varSqlLimit = "",
            $varSqlOffset = "",
            $varSqlSchema = null,
            $varCacheKey = null,
            $varSqlDistinct = false,
            $varSqlSequence = null,
            $varReturnInsertId = true;

    public function __construct() {
        $sessionRoute = new \Zend\Session\Container('globalRoute');
        $sessionRoute->setExpirationSeconds(3600);
        $this->aSession = $sessionRoute->getArrayCopy();
    }

    /**
     * Conexão padrão
     * @return \Zend\Db\Adapter\Adapter
     */
    public function getAdapter($adapterName = null) {


        self::$resultSetPrototype = new ResultSet();
        $config = ZendConfigFile::fromFile(GLOBAL_CONFIG_PATH . 'global.php');

        if (empty($adapterName)) {
            foreach ($config['db']['adapters'] as $keyAdapter => $valueAdapter) {
                if ($valueAdapter['default']) {
                    $adapterName = $keyAdapter;
                }
            }
        }

        if (isset($this->connAdapter[$adapterName]) and ! empty($this->connAdapter[$adapterName]) and $this->connAdapter[$adapterName]->getDriver()->getConnection()->isConnected()) {
            return $this->connAdapter[$adapterName];
        } else {
            /* Verifica se tem multiplos adaptadores de conexão e instancia os mesmos */
            if (isset($config['db']['adapters']) and ! empty($config['db']['adapters'])) {
                foreach ($config['db']['adapters'] as $key => $value) {
                    ${$key} = new \Zend\Db\Adapter\Adapter($value);
                    ${$key}->getDriver()->getConnection()->connect();
                    $this->connAdapter[$key] = ${$key};
                }
            } else {
                throw new \Exception('Dados de configuração não encontrados!');
            }
            return $this->connAdapter[$adapterName];
        }
    }

    /**
     * Retorna o profiler do adaptador
     * @return object
     */
    public function getProfiler() {
        return $this->getAdapter($this->varConfigAdapter)->getProfiler();
    }

    /**
     * Função que define a exibição do debug
     * @param boolean $debug
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function setDebug($debug = false) {
        self::$varDebug = $debug;
        return $this;
    }

    public function setLog($log = false) {
        $this->varExecuteLog = $log;
        return $this;
    }

    public static function setSession($session) {
        self::$session = $session;
    }

    public function setReturnInsertId($varReturnInsertId) {
        self::$varReturnInsertId = $varReturnInsertId;
    }

    /**
     * Função que define o adaptador de conexção a ser utilizado
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function setAdapter($adapterName = null) {
        $this->varConfigAdapter = $adapterName;
        return $this;
    }

    /**
     * Função que define a exibição do debug
     * @param boolean $explan
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function setExplan($explan = false) {
        self::$varExplan = $explan;
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE A CHAVE DE VERIFICAÇÃO DE CACHE
     * @param string $cacheKey NOME DA CHAVE
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function cacheKey($cacheKey) {
        self::$varCacheKey = $cacheKey;
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO SELECT
     * @param string $varSqlSelect NOME DO CAMPO PARA REALIZAR O SELECT
     * @param string $varSqlSelectAlias
     * @param string $isExpression
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function select($varSqlSelect, $varSqlSelectAlias = null, $isExpression = false) {
        $varArraySelect = Array('col' => $varSqlSelect, 'alias' => $varSqlSelectAlias, 'expression' => $isExpression);
        array_push(self::$varSqlSelect, $varArraySelect);
        return $this;
    }

    /**
     * Função de preparação das colunas de SELECT
     * @param type $columnTable
     * @param type $alias
     */
    private function prepareSelectColumns($columnTable, $alias = null, $isExpression = false) {

        $tableOrAliasTable = $column = $aColumn = null;

        // Check for columns
        if (preg_match('/\(.*\)/', $columnTable) OR ! preg_match('/(.+)\.(.+)/', $columnTable)) {
            $column = ($isExpression) ? new Expression($columnTable) : $columnTable;
            $tableOrAliasTable = null;
        } elseif (preg_match('/(.+)\.(.+)/', $columnTable, $aColumn)) {
            if ($isExpression) {
                $column = new Expression($aColumn[0]);
                $tableOrAliasTable = null;
            } else {
                $column = $aColumn[2];
                $tableOrAliasTable = $aColumn[1];
            }
        }

        if (!empty(self::$varSqlJoinUsing)) {
            /* Verifica se a coluna pertence a algum join */
            foreach (self::$varSqlJoinUsing as $valueTableJoin) {
                if ($valueTableJoin['tableName'] === $tableOrAliasTable OR $valueTableJoin['alias'] === $tableOrAliasTable) {
                    if (!empty($alias)) {
                        self::$varSqlSelectJoinColumns[$valueTableJoin['alias'] . '.' . $valueTableJoin['tableName']][$alias] = $column;
                    } else {
                        self::$varSqlSelectJoinColumns[$valueTableJoin['alias'] . '.' . $valueTableJoin['tableName']][] = $column;
                    }
                }
            }

            /* Verifica se a coluna pertence a algum from */
            foreach (self::$varSqlFrom as $valueTableFrom) {
                if ($tableOrAliasTable === null OR $valueTableFrom['tableName'] === $tableOrAliasTable OR ( isset($valueTableFrom['alias']) and $valueTableFrom['alias'] === $tableOrAliasTable)) {
                    if (!empty($alias)) {
                        self::$varSqlSelectFromColumns[$alias] = $column;
                    } else {
                        self::$varSqlSelectFromColumns[] = $column;
                    }
                }
            }
        } else {
            /* Verifica se a coluna pertence a algum from */
            foreach (self::$varSqlFrom as $valueTableFrom) {
                if ($tableOrAliasTable === null OR $valueTableFrom['tableName'] === $tableOrAliasTable OR ( isset($valueTableFrom['alias']) and $valueTableFrom['alias'] === $tableOrAliasTable)) {
                    if (!empty($alias)) {
                        self::$varSqlSelectFromColumns[$alias] = $column;
                    } else {
                        self::$varSqlSelectFromColumns[] = $column;
                    }
                } else {
                    if (!empty($alias)) {
                        self::$varSqlSelectFromColumns[$alias] = $column;
                    } else {
                        self::$varSqlSelectFromColumns[] = $column;
                    }
                }
            }
        }
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO DISTINCT
     * Sempre pegará a pimeira coluna de seleção da query
     * 
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function distinct() {
        self::$varSqlDistinct = true;
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO UPDATE
     * @param string $varSqlUpdateCol
     * @param mixed $varSqlUpdateValue
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function update($varSqlUpdateCol, $varSqlUpdateValue) {
        self::$varSqlUpdate[$varSqlUpdateCol] = $varSqlUpdateValue;
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO INSERT
     * @param string $varSqlInsertCol
     * @param mixed $varSqlInsertValue
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function insert($varSqlInsertCol, $varSqlInsertValue) {
        self::$varSqlInsert[$varSqlInsertCol] = $varSqlInsertValue;
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO SCHEMA INSERT
     * @param string $varSqlSchema
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function schema($varSqlSchema) {
        self::$varSqlSchema = $varSqlSchema;
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO SEQUENCE
     * @param string $varSqlSequence
     * @param string $schema
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function sequence($varSqlSequence, $schema = null) {
        self::$varSqlSequence = (!empty($schema)) ? $schema . '.' . $varSqlSequence : $varSqlSequence;
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO FROM
     * @param string $varSqlFrom
     * @param string $alias
     * @param string $schema
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function from($varSqlFrom, $alias = null, $schema = null) {
        if (!empty($alias)) {
            array_push(self::$varSqlFrom, Array('table' => new TableIdentifier($varSqlFrom, $schema), 'alias' => $alias, 'tableName' => $varSqlFrom));
        } else {
            array_push(self::$varSqlFrom, Array('table' => new TableIdentifier($varSqlFrom, $schema), 'tableName' => $varSqlFrom));
        }
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO WHERE
     * @param string $varSqlWhere
     * @param string $condition
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function where($varSqlWhere, $condition = "AND") {
        $varArrayWhere = Array('where' => $varSqlWhere, 'condition' => $condition);
        array_push(self::$varSqlWhere, $varArrayWhere);
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO JOIN
     * @param string $varSqlTableJoin NOME DA TABELA
     * @param string $aliasTable      ALIAS DA TABELA
     * @param string $condition       CONDIÇÃO DO JOIN
     * @param string $schema          SCHEMA QUE SE LOCALIZA A TABELA
     * @param string $aliasTable      ALIAS DA TABELA
     * @param string $type            TIPO DO JOIN PODENDO ASSUMIR:
     *                                INNERJOIN - Necessita de condição
     *                                LEFTJOIN - Necessita de condição
     *                                RIGHTJOIN - Necessita de condição
     *                                OUTERJOIN - Necessita de condição
     *                                NULL (DEFINIÇÃO PADRÃO "INNERJOIN") - Necessita de condição
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function join($varSqlTableJoin, $aliasTable = null, $condition = null, $type = null, $schema = null) {
        $arraySqlJoinUsing = Array(
            'table' => new TableIdentifier($varSqlTableJoin, $schema),
            'tableName' => $varSqlTableJoin,
            'alias' => $aliasTable,
            'condition' => new Expression($condition),
            'type' => $type
        );
        array_push(self::$varSqlJoinUsing, $arraySqlJoinUsing);
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO GROUPBY
     * @param string $varSqlGroupBy
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function groupBy($varSqlGroupBy) {
        array_push(self::$varSqlGroupBy, $varSqlGroupBy);
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO HAVING
     * @param string $varSqlHaving
     * @param string $condition
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function having($varSqlHaving, $condition = "AND") {
        $varArrayHaving = Array('where' => $varSqlHaving, 'condition' => $condition);
        array_push(self::$varSqlHaving, $varArrayHaving);
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO ORDERBY
     * @param string $varSqlOrderBy
     * @param string $expr
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function orderBy($varSqlOrderBy, $expr = false) {
        if ($expr) {
            array_push(self::$varSqlOrderBy, new Expression($varSqlOrderBy));
        } else {
            array_push(self::$varSqlOrderBy, $varSqlOrderBy);
        }
        return $this;
    }

    /**
     * FUNCAO QUE DEFINE O PARAMETRO LIMIT e/ou OFFSET
     * @param integer $varSqlLimit
     * @param integer $varSqlOffset
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function limit($varSqlLimit, $varSqlOffset = null) {
        self::$varSqlLimit = $varSqlLimit;
        if ($varSqlOffset !== null) {
            self::$varSqlOffset = $varSqlOffset;
        }
        return $this;
    }

    /**
     * Função utilizada para definir chamada de TRANSACTION
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function transaction($adapterName = null) {
        $this->varStatusTransaction = true;
        if (!empty($adapterName)) {
            $this->setAdapter($adapterName);
        }
        $this->getAdapter($this->varConfigAdapter)->getDriver()->getConnection()->beginTransaction();
        return $this;
    }

    /**
     * Função de execução de COMMIT
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function commit($adapterName = null) {
        $this->varStatusTransaction = false;
        if (!empty($adapterName)) {
            $this->setAdapter($adapterName);
        }
        $this->getAdapter($this->varConfigAdapter)->getDriver()->getConnection()->commit();
        return $this;
    }

    /**
     * Função de execução de ROLLBACK
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function rollback($adapterName = null) {
        $this->varStatusTransaction = false;
        if (!empty($adapterName)) {
            $this->setAdapter($adapterName);
        }
        $this->getAdapter($this->varConfigAdapter)->getDriver()->getConnection()->rollback();
        return $this;
    }

    /**
     * Função utilizada para definir chamada de TRANSACTION identificada
     * @param string $transactionId
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function prepareTransaction($transactionId, $adapterName = null) {
        $this->varStatusTransaction = true;
        if (!empty($adapterName)) {
            $this->getAdapter($adapterName);
            $this->executeSqlQuery("PREPARE TRANSACTION '{$transactionId}';");
        } else {
            $this->getAdapter($this->varConfigAdapter);
            $this->executeSqlQuery("PREPARE TRANSACTION '{$transactionId}';");
        }
        return $this;
    }

    /**
     * Função de execução de COMMIT de transação identificada
     * @param string $transactionId
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function preparedCommit($transactionId, $adapterName = null) {
        $this->varStatusTransaction = false;
        if (!empty($adapterName)) {
            $this->getAdapter($adapterName);
            $this->executeSqlQuery("COMMIT PREPARED '{$transactionId}';");
        } else {
            $this->getAdapter($this->varConfigAdapter);
            $this->executeSqlQuery("COMMIT PREPARED '{$transactionId}';");
        }
        return $this;
    }

    /**
     * Função de execução de ROLLBACK de transação identificada
     * @param string $transactionId
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function preparedRollback($transactionId, $adapterName = null) {
        $this->varStatusTransaction = false;
        if (!empty($adapterName)) {
            $this->getAdapter($adapterName);
            $this->executeSqlQuery("ROLLBACK PREPARED '{$transactionId}';");
        } else {
            $this->getAdapter($adapterName);
            $this->executeSqlQuery("ROLLBACK PREPARED '{$transactionId}';");
        }
        return $this;
    }

    /**
     * Função de definição de commit
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    public function closeConnection() {
        if (!$this->varStatusTransaction) {
            $this->getAdapter($this->varConfigAdapter)->getDriver()->getConnection()->disconnect();
        }
        return $this;
    }

    /**
     * FUNCAO QUE EXECUTA O COMANDO SELECT NO BANCO DE DADOS OU BUSCA NO CACHE GRAVADO
     * @param  boolean $activationPaginator
     * @param  integer $pageNumber
     * @param  integer $limitPerPage
     * @return mixed
     * @throws \Exception
     */
    public function executeSelectQueryCache($activationPaginator = false, $pageNumber = 1, $limitPerPage = 10) {
        if (empty(self::$varCacheKey)) {
            throw new \Exception('Não foi definido a chave para gravação do cache!', 500);
        } else {
            $rsCache = \Cityware\Cache\Factory::factory();
            $rsCache->setModuleName($this->aSession['moduleName']);
            $rsCache->setControllerName($this->aSession['controllerName']);
            $rsCache->preapareCache();

            $cacheKeyName = self::$varCacheKey;
        }

        if ($rsCache->verifyCache($cacheKeyName . $pageNumber)) {
            $retorno = $rsCache->getCacheContent($cacheKeyName . $pageNumber);
        } else {
            $retorno = $this->executeSelectQuery($activationPaginator = false, $pageNumber, $limitPerPage);
            $rsCache->saveCache($cacheKeyName . $pageNumber, $retorno);
        }

        return $retorno;
    }

    /**
     * FUNCAO QUE EXECUTA O COMANDO SELECT NO BANCO DE DADOS
     * @param  boolean $activationPaginator
     * @param  integer $pageNumber
     * @param  integer $limitPerPage
     * @return mixed
     * @throws \Exception
     */
    public function executeSelectQuery($activationPaginator = false, $pageNumber = 1, $limitPerPage = 10) {

        /*
         * VERIFICA SE O PARAMETRO FROM DA QUERY FOI DEFINIDO
         */
        if (!empty(self::$varSqlFrom[0]) and count(self::$varSqlFrom) > 0) {

            /*
             * INICIALIZA A QUERY PELO ZEND_DB
             */
            $sql = new Sql($this->getAdapter($this->varConfigAdapter));
            $select = $sql->select();

            //$sql->getSqlPlatform();

            /*
             * DEFINE O PARAMETRO FROM DA QUERY
             */
            foreach (self::$varSqlFrom as $key => $value) {
                if (isset($value['alias']) and ! empty($value['alias'])) {
                    $select->from(Array($value['alias'] => $value['table']));
                } else {
                    $select->from($value['table']);
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO SELECT DA QUERY
             */
            if (!empty(self::$varSqlSelect) and count(self::$varSqlSelect) > 0) {
                foreach (self::$varSqlSelect as $key => $value) {
                    if (isset(self::$varSqlSelect[$key]['alias']) and ! empty(self::$varSqlSelect[$key]['alias'])) {
                        $this->prepareSelectColumns(self::$varSqlSelect[$key]['col'], self::$varSqlSelect[$key]['alias'], self::$varSqlSelect[$key]['expression']);
                    } else {
                        $this->prepareSelectColumns(self::$varSqlSelect[$key]['col'], null, self::$varSqlSelect[$key]['expression']);
                    }
                }
                $select->columns(self::$varSqlSelectFromColumns);
            } else {
                $select->columns(Array('*'));
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO DISTINCT DA QUERY
             */
            if (self::$varSqlDistinct) {
                $select->quantifier('DISTINCT');
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO INNERJOING DA QUERY
             */
            if (!empty(self::$varSqlJoinUsing)) {
                foreach (self::$varSqlJoinUsing as $key => $value) {
                    if (!empty($value['alias'])) {
                        $tableJoin = Array($value['alias'] => $value['table']);
                        $tableName = $value['table']->getTable();
                        $tableAlias = $value['alias'];
                    } else {
                        $tableJoin = $value['table'];
                        $tableName = $value['table']->getTable();
                        $tableAlias = null;
                    }

                    $selectJoin = (isset(self::$varSqlSelectJoinColumns[$tableAlias . "." . $tableName]) and ! empty(self::$varSqlSelectJoinColumns[$tableAlias . "." . $tableName])) ? self::$varSqlSelectJoinColumns[$tableAlias . "." . $tableName] : Array();

                    switch (strtolower($value['type'])) {
                        case 'innerjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'inner');
                            break;
                        case 'leftjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'left');
                            break;
                        case 'rightjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'right');
                            break;
                        case 'outerjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'outer');
                            break;
                        default:
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'inner');
                            break;
                    }
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO WHERE DA QUERY
             */
            if (!empty(self::$varSqlWhere) and count(self::$varSqlWhere) > 0) {
                foreach (self::$varSqlWhere as $key => $value) {
                    $select->where(self::$varSqlWhere[$key]['where'], self::$varSqlWhere[$key]['condition']);
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO GROUPBY DA QUERY
             */
            if (!empty(self::$varSqlGroupBy) and count(self::$varSqlGroupBy) > 0) {
                $select->group(self::$varSqlGroupBy);
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO HAVING DA QUERY
             */
            if (!empty(self::$varSqlHaving) and count(self::$varSqlHaving) > 0) {
                foreach (self::$varSqlHaving as $key => $value) {
                    $select->having(self::$varSqlHaving[$key]['where'], self::$varSqlHaving[$key]['condition']);
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO ORDERBY DA QUERY
             */
            if (!empty(self::$varSqlOrderBy)) {
                $select->order(self::$varSqlOrderBy);
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO LIMIT DA QUERY
             */
            if (!empty(self::$varSqlLimit)) {
                /*
                 * VERIFICA E DEFINE O PARAMETRO OFFSET (COMPLEMENTA O LIMIT) DA QUERY
                 */
                if (!empty(self::$varSqlOffset)) {
                    $select->limit(self::$varSqlLimit);
                    $select->offset(self::$varSqlOffset);
                } else {
                    $select->limit(self::$varSqlLimit);
                }
            }

            try {
                /*
                 * CASO O DEBUG ESTEJA ATIVO IMPRIME A QUERY (COMANDO) NA TELA
                 */
                if (self::$varDebug) {
                    $this->debugQuery($select->getSqlString());
                } elseif (self::$varExplan) {
                    $this->explainQuery($select->getSqlString());
                } else {
                    /*
                     * CASO CONTRARIO APENAS RETORNA O RESULTADO EM UM ARRAY
                     */
                    if ($activationPaginator) {
                        $retorno = Array();
                        $adapter = new \Zend\Paginator\Adapter\DbSelect($select, $sql);
                        $paginator = new \Zend\Paginator\Paginator($adapter);
                        $paginator->setItemCountPerPage($limitPerPage);
                        //$paginator->setDefaultPageRange(5);
                        $paginator->setPageRange(5);
                        $paginator->setCurrentPageNumber($pageNumber);
                        $retorno['db'] = self::$resultSetPrototype->initialize($paginator->getItemsByPage($pageNumber))->toArray();
                        $retorno['page'] = $paginator;
                        
                    } else {
                        $statement = $sql->prepareStatementForSqlObject($select);
                        $results = $statement->execute();
                        $retorno = self::$resultSetPrototype->initialize($results)->toArray();
                    }
                }
            } catch (\Exception $exc) {
                $this->closeConnection();
                $retorno = false;
                throw new \Exception('Nao foi possivel executar o comando SELECT no banco de dados!<br /><br />' . $exc->getMessage(), 500);
            }
        } else {
            $this->closeConnection();
            $retorno = false;
            throw new \Exception('O comando SELECT nao foi definido corretamente!');
        }
        $this->closeConnection();
        self::freeMemory();

        return $retorno;
    }

    /**
     * FUNCAO QUE MONTA O COMANDO SUB-SELECT NO BANCO DE DADOS E RETORNA A STRING
     * @return mixed
     * @throws Exception
     */
    public function executeSubSelectQuery() {

        /*
         * VERIFICA SE O PARAMETRO FROM DA QUERY FOI DEFINIDO
         */
        if (!empty(self::$varSqlFrom[0]) and count(self::$varSqlFrom) > 0) {

            /*
             * INICIALIZA A QUERY PELO ZEND_DB
             */
            $sql = new Sql($this->getAdapter($this->varConfigAdapter));
            $select = $sql->select();

            //$sql->getSqlPlatform();

            /*
             * DEFINE O PARAMETRO FROM DA QUERY
             */
            foreach (self::$varSqlFrom as $key => $value) {
                if (isset($value['alias']) and ! empty($value['alias'])) {
                    $select->from(Array($value['alias'] => $value['table']));
                } else {
                    $select->from($value['table']);
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO SELECT DA QUERY
             */
            if (!empty(self::$varSqlSelect) and count(self::$varSqlSelect) > 0) {
                foreach (self::$varSqlSelect as $key => $value) {
                    if (isset(self::$varSqlSelect[$key]['alias']) and ! empty(self::$varSqlSelect[$key]['alias'])) {
                        $this->prepareSelectColumns(self::$varSqlSelect[$key]['col'], self::$varSqlSelect[$key]['alias'], self::$varSqlSelect[$key]['expression']);
                    } else {
                        $this->prepareSelectColumns(self::$varSqlSelect[$key]['col'], null, self::$varSqlSelect[$key]['expression']);
                    }
                }
                $select->columns(self::$varSqlSelectFromColumns);
            } else {
                $select->columns(Array('*'));
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO DISTINCT DA QUERY
             */
            if (self::$varSqlDistinct) {
                $select->quantifier('DISTINCT');
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO INNERJOING DA QUERY
             */
            if (!empty(self::$varSqlJoinUsing)) {
                foreach (self::$varSqlJoinUsing as $key => $value) {
                    if (!empty($value['alias'])) {
                        $tableJoin = Array($value['alias'] => $value['table']);
                        $tableName = $value['table']->getTable();
                        $tableAlias = $value['alias'];
                    } else {
                        $tableJoin = $value['table'];
                        $tableName = $value['table']->getTable();
                        $tableAlias = null;
                    }

                    $selectJoin = (isset(self::$varSqlSelectJoinColumns[$tableAlias . "." . $tableName]) and ! empty(self::$varSqlSelectJoinColumns[$tableAlias . "." . $tableName])) ? self::$varSqlSelectJoinColumns[$tableAlias . "." . $tableName] : Array();

                    switch (strtolower($value['type'])) {
                        case 'innerjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'inner');
                            break;
                        case 'leftjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'left');
                            break;
                        case 'rightjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'right');
                            break;
                        case 'outerjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'outer');
                            break;
                        default:
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'inner');
                            break;
                    }
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO WHERE DA QUERY
             */
            if (!empty(self::$varSqlWhere) and count(self::$varSqlWhere) > 0) {
                foreach (self::$varSqlWhere as $key => $value) {
                    $select->where(self::$varSqlWhere[$key]['where'], self::$varSqlWhere[$key]['condition']);
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO GROUPBY DA QUERY
             */
            if (!empty(self::$varSqlGroupBy) and count(self::$varSqlGroupBy) > 0) {
                $select->group(self::$varSqlGroupBy);
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO HAVING DA QUERY
             */
            if (!empty(self::$varSqlHaving) and count(self::$varSqlHaving) > 0) {
                foreach (self::$varSqlHaving as $key => $value) {
                    $select->having(self::$varSqlHaving[$key]['where'], self::$varSqlHaving[$key]['condition']);
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO ORDERBY DA QUERY
             */
            if (!empty(self::$varSqlOrderBy)) {
                $select->order(self::$varSqlOrderBy);
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO LIMIT DA QUERY
             */
            if (!empty(self::$varSqlLimit)) {
                /*
                 * VERIFICA E DEFINE O PARAMETRO OFFSET (COMPLEMENTA O LIMIT) DA QUERY
                 */
                if (!empty(self::$varSqlOffset)) {
                    $select->limit(self::$varSqlLimit);
                    $select->offset(self::$varSqlOffset);
                } else {
                    $select->limit(self::$varSqlLimit);
                }
            }

            try {
                /*
                 * CASO O DEBUG ESTEJA ATIVO IMPRIME A QUERY (COMANDO) NA TELA
                 */
                if (self::$varDebug) {
                    $retorno = str_replace('"', '', $select->getSqlString());
                    $this->debugQuery($retorno);
                } elseif (self::$varExplan) {
                    $this->explainQuery($select->getSqlString());
                } else {
                    $retorno = str_replace('"', '', $select->getSqlString());
                }
            } catch (\Zend\Db\Exception $exc) {
                $this->closeConnection();
                $retorno = false;
                throw new \Exception('Nao foi possivel executar o comando SUB-SELECT no banco de dados!<br /><br />' . $exc->getMessage(), 500);
            }
        } else {
            $this->closeConnection();
            $retorno = false;
            throw new \Exception('O comando SUB-SELECT nao foi definido corretamente!');
        }
        $this->closeConnection();
        self::freeMemory();

        return $retorno;
    }

    /**
     * FUNCAO QUE EXECUTA O STATEMENT DO COMANDO SELECT NO BANCO DE DADOS
     * @return mixed
     * @throws \Exception
     */
    public function statementSelectQuery() {
        /*
         * VERIFICA SE O PARAMETRO FROM DA QUERY FOI DEFINIDO
         */
        if (!empty(self::$varSqlFrom[0]) and count(self::$varSqlFrom) > 0) {

            /*
             * INICIALIZA A QUERY PELO ZEND\DB\SQL
             */
            $sql = new Sql($this->getAdapter($this->varConfigAdapter));
            $select = $sql->select();

            //$sql->getSqlPlatform();

            /*
             * DEFINE O PARAMETRO FROM DA QUERY
             */
            foreach (self::$varSqlFrom as $key => $value) {
                if (isset($value['alias']) and ! empty($value['alias'])) {
                    $select->from(Array($value['alias'] => $value['table']));
                } else {
                    $select->from($value['table']);
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO SELECT DA QUERY
             */
            if (!empty(self::$varSqlSelect) and count(self::$varSqlSelect) > 0) {
                foreach (self::$varSqlSelect as $key => $value) {
                    if (isset(self::$varSqlSelect[$key]['alias']) and ! empty(self::$varSqlSelect[$key]['alias'])) {
                        $this->prepareSelectColumns(self::$varSqlSelect[$key]['col'], self::$varSqlSelect[$key]['alias']);
                    } else {
                        $this->prepareSelectColumns(self::$varSqlSelect[$key]['col']);
                    }
                }
                $select->columns(self::$varSqlSelectFromColumns);
            } else {
                $select->columns(Array('*'));
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO DISTINCT DA QUERY
             */
            if (self::$varSqlDistinct) {
                $select->quantifier('DISTINCT');
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO INNERJOING DA QUERY
             */
            if (!empty(self::$varSqlJoinUsing)) {
                foreach (self::$varSqlJoinUsing as $key => $value) {
                    if (!empty($value['alias'])) {
                        $tableJoin = Array($value['alias'] => $value['table']);
                        $tableName = $value['table']->getTable();
                        $tableAlias = $value['alias'];
                    } else {
                        $tableJoin = $value['table'];
                        $tableName = $value['table']->getTable();
                        $tableAlias = null;
                    }

                    $selectJoin = (isset(self::$varSqlSelectJoinColumns[$tableAlias . "." . $tableName]) and ! empty(self::$varSqlSelectJoinColumns[$tableAlias . "." . $tableName])) ? self::$varSqlSelectJoinColumns[$tableAlias . "." . $tableName] : Array();

                    switch (strtolower($value['type'])) {
                        case 'innerjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'inner');
                            break;
                        case 'leftjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'left');
                            break;
                        case 'rightjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'right');
                            break;
                        case 'outerjoin':
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'outer');
                            break;
                        default:
                            $select->join($tableJoin, $value['condition'], $selectJoin, 'inner');
                            break;
                    }
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO WHERE DA QUERY
             */
            if (!empty(self::$varSqlWhere) and count(self::$varSqlWhere) > 0) {
                foreach (self::$varSqlWhere as $key => $value) {
                    $select->where(self::$varSqlWhere[$key]['where'], self::$varSqlWhere[$key]['condition']);
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO GROUPBY DA QUERY
             */
            if (!empty(self::$varSqlGroupBy) and count(self::$varSqlGroupBy) > 0) {
                $select->group(self::$varSqlGroupBy);
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO HAVING DA QUERY
             */
            if (!empty(self::$varSqlHaving) and count(self::$varSqlHaving) > 0) {
                foreach (self::$varSqlHaving as $key => $value) {
                    $select->having(self::$varSqlHaving[$key]['where'], self::$varSqlHaving[$key]['condition']);
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO ORDERBY DA QUERY
             */
            if (!empty(self::$varSqlOrderBy)) {
                $select->order(self::$varSqlOrderBy);
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO LIMIT DA QUERY
             */
            if (!empty(self::$varSqlLimit)) {
                /*
                 * VERIFICA E DEFINE O PARAMETRO OFFSET (COMPLEMENTA O LIMIT) DA QUERY
                 */
                if (!empty(self::$varSqlOffset)) {
                    $select->limit(self::$varSqlLimit);
                    $select->offset(self::$varSqlOffset);
                } else {
                    $select->limit(self::$varSqlLimit);
                }
            }

            try {
                /*
                 * CASO O DEBUG ESTEJA ATIVO IMPRIME A QUERY (COMANDO) NA TELA
                 */
                if (self::$varDebug) {
                    $this->debugQuery($select->getSqlString());
                } elseif (self::$varExplan) {
                    $this->explainQuery($select->getSqlString());
                } else {
                    /*
                     * CASO CONTRARIO APENAS RETORNA O RESULTADO EM UM ARRAY
                     */
                    $retorno = $sql->prepareStatementForSqlObject($select);
                }
            } catch (\Zend\Db\Exception $exc) {
                $this->closeConnection();
                $retorno = false;
                throw new \Exception('Nao foi possivel executar o comando SELECT no banco de dados!<br /><br />' . $exc->getMessage(), 500);
            }
        } else {
            $this->closeConnection();
            $retorno = false;
            throw new \Exception('O comando SELECT nao foi definido corretamente!');
        }
        $this->closeConnection();
        self::freeMemory();

        return $retorno;
    }

    /**
     * Função que converte o resultado do EXECUTE em array
     * @param \Zend\Db\Adapter\Driver\Pdo\Result $results
     * @return type
     */
    public function toArrayFromExecute(\Zend\Db\Adapter\Driver\Pdo\Result $results) {
        $resultSet = new \Zend\Db\ResultSet\ResultSet();
        return $resultSet->initialize($results)->toArray();
    }

    /**
     * FUNCAO QUE EXECUTA UM COMANDO QUALQUER NO BANCO DE DADOS OU BUSCA NO CACHE GRAVADO
     * ESTA FUNCAO MASCARA O USO DO ZEND_DB NO SISTEMA
     * @param  string  $sqlQuery
     * @param  boolean $activationPaginator
     * @param  integer $pageNumber
     * @param  integer $limitPerPage
     * @return boolean
     * @throws \Exception
     */
    public function executeSqlQueryCache($sqlQuery, $activationPaginator = false, $pageNumber = 1, $limitPerPage = 10) {
        $varSqlQuery = trim(str_replace(array("\n", "\r", "\t", "     ", "    ", "   ", "  "), array(" ", " ", " ", " ", " ", " ", " "), $sqlQuery));
        $typeSelect = (bool) (substr(strtoupper($varSqlQuery), 0, 6) == "SELECT") ? true : false;

        if (empty(self::$varCacheKey)) {
            throw new \Exception('Não foi definido a chave para gravação do cache!', 500);
        } elseif (!$typeSelect) {
            throw new \Exception('A query executada não é um SELECT para ser gravado no cache!', 500);
        } else {
            $rsCache = \Cityware\Cache\Factory::factory();
            $rsCache->setModuleName($this->aSession['moduleName']);
            $rsCache->setControllerName($this->aSession['controllerName']);
            $rsCache->preapareCache();
            $cacheKeyName = self::$varCacheKey;
        }

        if ($rsCache->verifyCache($cacheKeyName . $pageNumber)) {
            $retorno = $rsCache->getCacheContent($cacheKeyName . $pageNumber);
        } else {
            $retorno = $this->executeSqlQuery($varSqlQuery, $activationPaginator, $pageNumber, $limitPerPage);
            $rsCache->saveCache($cacheKeyName . $pageNumber, $retorno);
        }

        return $retorno;
    }

    /**
     * FUNCAO QUE EXECUTA UM COMANDO QUALQUER NO BANCO DE DADOS
     * ESTA FUNCAO MASCARA O USO DO ZEND_DB NO SISTEMA
     * @param  string  $sqlQuery
     * @param  boolean $activationPaginator
     * @param  integer $pageNumber
     * @param  integer $limitPerPage
     * @return boolean
     * @throws \Exception
     */
    public function executeSqlQuery($sqlQuery, $activationPaginator = false, $pageNumber = 1, $limitPerPage = 10) {
        $sql = new Sql($this->getAdapter($this->varConfigAdapter));
        /*
         * RETIRA CARACTERES INVALIDOS DA QUERY
         */
        $varSqlQuery = trim(str_replace(array("\n", "\r", "\t", "     ", "    ", "   ", "  "), array(" ", " ", " ", " ", " ", " ", " "), $sqlQuery));

        /*
         * VERIFICA SE O TIPO DE COMANDO E UM SELECT
         */
        $typeSelect = (bool) (substr(strtoupper($varSqlQuery), 0, 6) == "SELECT") ? true : false;

        /*
         * VERIFICA SE O COMANDO FOI DEFINIDO
         */
        if (!empty($varSqlQuery)) {
            try {

                $query = $this->getAdapter($this->varConfigAdapter)->query($varSqlQuery);

                /*
                 * CASO O DEBUG ESTEJA ATIVO IMPRIME A QUERY (COMANDO) NA TELA
                 */
                if (self::$varDebug) {
                    $this->debugQuery($varSqlQuery);
                } elseif (self::$varExplan) {
                    $this->explainQuery($varSqlQuery);
                }

                /*
                 * CASO O COMANDO EXECUTADO SEJA DO TIPO SELECT RETORNA UM ARRAY COM O RESULTADO DA CONSULTA
                 */
                if ($typeSelect === true) {
                    /**
                     * VERIFICA SE HÁ CACHE GRAVADO E NAO EXPIRADO
                     */
                    $rowSet = $query->execute();
                    if ($activationPaginator) {
                        $retorno = Array();
                        $adapter = new \Zend\Paginator\Adapter\Iterator($rowSet, $sql);
                        $paginator = new \Zend\Paginator\Paginator($adapter);
                        $paginator->setItemCountPerPage($limitPerPage);
                        $paginator->setPageRange(5);
                        $paginator->setCurrentPageNumber($pageNumber);
                        
                        $retorno['db'] = self::$resultSetPrototype->initialize($paginator->getCurrentItems())->toArray();
                        $retorno['page'] = $paginator;
                        
                    } else {
                        $retorno = self::$resultSetPrototype->initialize($rowSet)->toArray();
                    }

                    if ((!is_array($retorno)) || (count($retorno) <= 0)) {
                        $retorno = false;
                    }
                } else {
                    $query->execute();
                    $retorno = true;
                }
            } catch (\Zend\Db\Exception $exc) {
                $this->closeConnection();
                $retorno = false;
                throw new \Exception('Nao foi possivel executar o comando SQL no banco de dados!<br /><br />' . $exc->getMessage(), 500);
            }
        } else {
            $this->closeConnection();
            $retorno = false;
            throw new \Exception('O comando SQL nao foi definido corretamente!');
        }
        $this->closeConnection();
        self::freeMemory();

        return $retorno;
    }

    /**
     * FUNCAO QUE EXECUTA O COMANDO UPDATE NO BANCO DE DADOS
     * @return mixed
     * @throws \Exception
     */
    public function executeUpdateQuery() {
        /*
         * VERIFICA SE OS PARAMETROS FROM E UPDATE (CAMPOS) FORAM DEFINIDOS
         */
        if ((!empty(self::$varSqlFrom)) && (!empty(self::$varSqlUpdate))) {

            /*
             * INICIALIZA A QUERY PELO ZEND_DB
             */
            $sql = new Sql($this->getAdapter($this->varConfigAdapter));
            $update = $sql->update();

            /*
             * DEFINE O PARAMETRO FROM DA QUERY
             */
            foreach (self::$varSqlFrom as $key => $value) {
                if (isset($value['alias']) and ! empty($value['alias'])) {
                    $update->table(Array($value['alias'] => $value['table']));
                } else {
                    $update->table($value['table']);
                }
            }

            /*
             * VERIFICA E DEFINE O PARAMETRO WHERE DA QUERY
             */
            if (!empty(self::$varSqlWhere) and count(self::$varSqlWhere) > 0) {
                $arrayWhere = Array();
                foreach (self::$varSqlWhere as $key => $value) {
                    $arrayWhere[] = self::$varSqlWhere[$key]['where'];
                }
                $update->where($arrayWhere);
            }

            /*
             * DEFINE OS PARAMETROS DE ATUALIZACAO NA QUERY
             */
            $update->set(self::$varSqlUpdate);

            /*
             * CASO O DEBUG ESTEJA ATIVO IMPRIME A QUERY (COMANDO) NA TELA
             */
            $queryString = $sql->getSqlStringForSqlObject($update);
            if (self::$varDebug) {
                $this->debugQuery($queryString);
            } elseif (self::$varExplan) {
                $this->explainQuery($queryString);
            }

            try {
                $statement = $sql->prepareStatementForSqlObject($update);
                $statement->execute();
                $retorno = true;
            } catch (\Zend\Db\Exception $exc) {
                $this->closeConnection();
                $retorno = false;
                throw new \Exception('Nao foi possível executar o comando UPDATE no banco de dados!<br /><br />' . $exc->getMessage(), 500);
            }
        } else {
            $this->closeConnection();
            $retorno = false;
            throw new \Exception('O comando UPDATE nao foi definido corretamente!', 500);
        }
        $this->closeConnection();
        self::freeMemory();

        if ($this->varExecuteLog) {
            $this->geraLogSistema($queryString, 'update');
        }

        return $retorno;
    }

    /**
     * FUNCAO QUE EXECUTA O COMANDO INSERT NO BANCO DE DADOS
     * @return mixed
     * @throws \Exception
     */
    public function executeInsertQuery() {
        $retorno = false;
        $id = null;

        /*
         * VERIFICA SE OS PARAMETROS FROM E UPDATE (CAMPOS) FORAM DEFINIDOS
         */
        if ((!empty(self::$varSqlFrom)) && (!empty(self::$varSqlInsert))) {

            /*
             * INICIALIZA A QUERY PELO ZEND_DB
             */
            $sql = new Sql($this->getAdapter($this->varConfigAdapter));

            $insert = $sql->insert();

            /*
             * DEFINE O PARAMETRO FROM DA QUERY
             */
            foreach (self::$varSqlFrom as $value) {
                $insert->into($value['table']);
            }

            if ($this->varStatusTransaction) {
                $id = $this->nextSequenceIdRawStateTable($insert->getRawState());
            }

            /*
             * DEFINE OS PARAMETROS DE ATUALIZACAO NA QUERY
             */
            $insert->values(self::$varSqlInsert);

            /*
             * CASO O DEBUG ESTEJA ATIVO IMPRIME A QUERY (COMANDO) NA TELA
             */
            $queryString = $sql->getSqlStringForSqlObject($insert);
            if (self::$varDebug) {
                $this->debugQuery($queryString);
            } elseif (self::$varExplan) {
                $this->explainQuery($queryString);
            }

            try {
                $rawState = $insert->getRawState();
                $statement = $sql->prepareStatementForSqlObject($insert);
                $statement->execute();
                if (empty($id)) {
                    $retorno = $this->getLastInsertId($rawState, $sql);
                } else {
                    $retorno = $id;
                }
            } catch (Exception $exc) {
                $this->closeConnection();
                throw new Exception('Nao foi possível executar o comando INSERT no banco de dados!<br /><br />' . $insert->getSqlString() . '<br /><br />' . $exc->getMessage(), 500);
            }
        } else {
            $this->closeConnection();
            throw new Exception('O comando INSERT nao foi definido corretamente!', 500);
        }
        $this->closeConnection();
        self::freeMemory();

        if ($this->varExecuteLog) {
            $this->geraLogSistema($queryString, 'insert');
        }

        return $retorno;
    }

    /**
     * FUNCAO QUE EXECUTA O COMANDO INSERT NO BANCO DE DADOS
     * @return mixed
     * @throws \Exception
     */
    public function statementInsertQuery() {
        $retorno = false;

        /*
         * VERIFICA SE OS PARAMETROS FROM E UPDATE (CAMPOS) FORAM DEFINIDOS
         */
        if ((!empty(self::$varSqlFrom)) && (!empty(self::$varSqlInsert))) {

            /*
             * INICIALIZA A QUERY PELO ZEND_DB
             */
            $sql = new Sql($this->getAdapter($this->varConfigAdapter));
            $insert = $sql->insert();

            /*
             * DEFINE O PARAMETRO FROM DA QUERY
             */
            foreach (self::$varSqlFrom as $value) {
                $insert->into($value['table']);
            }

            /*
             * DEFINE OS PARAMETROS DE ATUALIZACAO NA QUERY
             */
            $insert->values(array());
            $insert->columns(self::$varSqlInsert);

            /*
             * CASO O DEBUG ESTEJA ATIVO IMPRIME A QUERY (COMANDO) NA TELA
             */
            $queryString = $sql->getSqlStringForSqlObject($insert);
            if (self::$varDebug) {
                $this->debugQuery($queryString);
            } elseif (self::$varExplan) {
                $this->explainQuery($queryString);
            }

            try {
                $retorno = $sql->prepareStatementForSqlObject($insert);
            } catch (Exception $exc) {
                $this->closeConnection();
                throw new Exception('Nao foi possível executar o comando INSERT no banco de dados!<br /><br />' . $insert->getSqlString() . '<br /><br />' . $exc->getMessage(), 500);
            }
        } else {
            $this->closeConnection();
            throw new Exception('O comando INSERT nao foi definido corretamente!', 500);
        }
        $this->closeConnection();
        self::freeMemory();

        if ($this->varExecuteLog) {
            $this->geraLogSistema($queryString, 'insert');
        }

        return $retorno;
    }

    /*
     * FUNCAO QUE EXECUTA O COMANDO DELETE NO BANCO DE DADOS
     * @return boolean
     * @throws \Exception
     */

    public function executeDeleteQuery() {
        $retorno = false;
        /*
         * VERIFICA SE O PARAMETRO FROM DA QUERY FOI DEFINIDO
         */
        if (!empty(self::$varSqlFrom)) {
            /*
             * INICIALIZA A QUERY PELO ZEND_DB
             */
            $sql = new Sql($this->getAdapter($this->varConfigAdapter));
            $delete = $sql->delete();

            /*
             * VERIFICA E DEFINE O PARAMETRO WHERE DA QUERY
             */
            if (!empty(self::$varSqlWhere) and count(self::$varSqlWhere) > 0) {
                $arrayWhere = Array();
                foreach (self::$varSqlWhere as $key => $value) {
                    $arrayWhere[] = self::$varSqlWhere[$key]['where'];
                }
                $delete->where($arrayWhere);
            }

            /*
             * DEFINE O PARAMETRO FROM DA QUERY
             */
            foreach (self::$varSqlFrom as $key => $value) {
                if (isset($value['alias']) and ! empty($value['alias'])) {
                    $delete->from(Array($value['alias'] => $value['table']));
                } else {
                    $delete->from($value['table']);
                }
            }

            /*
             * MONTA QUERY PARA DEBUG
             */
            $queryString = $sql->getSqlStringForSqlObject($delete);
            if (self::$varDebug) {
                $this->debugQuery($queryString);
            } elseif (self::$varExplan) {
                $this->explainQuery($queryString);
            }

            try {
                $statement = $sql->prepareStatementForSqlObject($delete);
                $statement->execute();
                $retorno = true;
            } catch (\Zend\Db\Exception $exc) {
                $this->closeConnection();
                throw new \Exception('Não foi possivel executar o comando DELETE no banco de dados!<br /><br />' . $exc->getMessage(), 500);
            }
        } else {
            $this->closeConnection();
            throw new \Exception('O comando DELETE não foi definido corretamente!', 500);
        }
        $this->closeConnection();
        self::freeMemory();

        if ($this->varExecuteLog) {
            $this->geraLogSistema($queryString, 'delete');
        }

        return $retorno;
    }

    /*
     * FUNCAO QUE EXECUTA O COMANDO DELETE NO BANCO DE DADOS
     * @return boolean
     * @throws \Exception
     */

    public function statementDeleteQuery() {
        $retorno = false;
        /*
         * VERIFICA SE O PARAMETRO FROM DA QUERY FOI DEFINIDO
         */
        if (!empty(self::$varSqlFrom)) {
            /*
             * INICIALIZA A QUERY PELO ZEND_DB
             */
            $sql = new Sql($this->getAdapter($this->varConfigAdapter));
            $delete = $sql->delete();

            /*
             * VERIFICA E DEFINE O PARAMETRO WHERE DA QUERY
             */
            if (!empty(self::$varSqlWhere) and count(self::$varSqlWhere) > 0) {
                $arrayWhere = Array();
                foreach (self::$varSqlWhere as $key => $value) {
                    $arrayWhere[] = self::$varSqlWhere[$key]['where'];
                }
                $delete->where($arrayWhere);
            }

            /*
             * DEFINE O PARAMETRO FROM DA QUERY
             */
            foreach (self::$varSqlFrom as $key => $value) {
                if (isset($value['alias']) and ! empty($value['alias'])) {
                    $delete->from(Array($value['alias'] => $value['table']));
                } else {
                    $delete->from($value['table']);
                }
            }

            /*
             * MONTA QUERY PARA DEBUG
             */
            $queryString = $sql->getSqlStringForSqlObject($delete);
            if (self::$varDebug) {
                $this->debugQuery($queryString);
            } elseif (self::$varExplan) {
                $this->explainQuery($queryString);
            }

            try {
                $retorno = $sql->prepareStatementForSqlObject($delete);
            } catch (\Zend\Db\Exception $exc) {
                $this->closeConnection();
                throw new \Exception('Não foi possivel executar o comando DELETE no banco de dados!<br /><br />' . $exc->getMessage(), 500);
            }
        } else {
            $this->closeConnection();
            throw new \Exception('O comando DELETE não foi definido corretamente!', 500);
        }
        $this->closeConnection();
        self::freeMemory();

        if ($this->varExecuteLog) {
            $this->geraLogSistema($queryString, 'delete');
        }

        return $retorno;
    }

    /**
     * Função que pega previamente o ID do registro a ser inserido
     * @param  array   $rawState
     * @return array
     */
    private function nextSequenceIdRawStateTable(array $rawState) {
        if (self::$varReturnInsertId) {
            $sequenceResult = array();
            $retorno = null;

            $table = $rawState['table'];
            $tableMetadata = new \Zend\Db\Metadata\Metadata($this->getAdapter($this->varConfigAdapter));
            $tableInfo = $tableMetadata->getTable($table->getTable(), $table->getSchema());

            foreach ($tableInfo->getConstraints() as $key => $value) {
                if ($value->getType() == 'PRIMARY KEY') {
                    $temp = $value->getColumns();
                    $sequenceResult[$temp[0]] = true;
                }
            }

            foreach ($tableInfo->getColumns() as $key => $value) {
                if (isset($sequenceResult[$value->getName()]) and ( stripos(strtolower($value->getColumnDefault()), 'nextval') !== false)) {
                    $statement = $this->getAdapter($this->varConfigAdapter)->createStatement();
                    $statement->prepare("SELECT {$value->getColumnDefault()}");
                    $result = $statement->execute()->getResource()->fetch(\PDO::FETCH_ASSOC);
                    $this->insert($value->getName(), $result['nextval']);
                    $retorno = $result['nextval'];
                }
            }
            return $retorno;
        }
    }

    /**
     * Função que pega previamente o ID do registro a ser inserido
     * @param  string   $sequence
     * @return integer
     */
    public function executeNextSequenceId($sequence) {

        $statement = $this->getAdapter($this->varConfigAdapter)->createStatement();
        $statement->prepare("SELECT {$sequence}");
        $result = $statement->execute()->getResource()->fetch(\PDO::FETCH_ASSOC);

        return $result['nextval'];
    }

    /**
     * Função que pega a ID do registro inserido
     * @param  array   $rawState
     * @param  object  $sql
     * @return integer
     */
    private function getLastInsertId(array $rawState, $sql) {

        if (self::$varReturnInsertId) {
            $table = $rawState['table'];
            $tableMetadata = new \Zend\Db\Metadata\Metadata($this->getAdapter($this->varConfigAdapter));

            $tableInfo = $tableMetadata->getTable($table->getTable(), $table->getSchema());

            if (!empty(self::$varSqlSequence)) {
                return $this->getAdapter($this->varConfigAdapter)->getDriver()->getConnection()->getLastGeneratedValue(self::$varSqlSequence);
            } else {
                $primaryKeyColumn = null;
                foreach ($tableInfo->getConstraints() as $key => $value) {
                    if ($value->getType() == 'PRIMARY KEY') {
                        $temp = $value->getColumns();
                        $primaryKeyColumn = $temp[0];
                    }
                }

                $select = $sql->select($rawState['table']);
                foreach ($rawState['columns'] as $key => $value) {
                    if (!empty($rawState['values'][$key])) {
                        $select->where("{$value} = '{$rawState['values'][$key]}'");
                    } else {
                        $select->where("{$value} IS NULL");
                    }
                }

                $statement = $sql->prepareStatementForSqlObject($select);
                $results = $statement->execute();
            $retorno = self::$resultSetPrototype->initialize($results)->toArray();
                self::freeMemory();

                return $retorno[0][$primaryKeyColumn];
            }
        }
    }

    /**
     * Função de formatação e impressão do debug de query
     * @param type $query
     */
    private function debugQuery($query) {
        $return = null;
        $return .= "<pre>";
        $return .= \Cityware\Format\Sql::format($query);
        $return .= "</pre>";
        echo $return;
        exit;
    }

    /**
     * Função de formatação e impressão de plano de execução de query
     * @param type $query
     */
    private function explainQuery($query) {
        $explain = new ExecutionPlan($this->getAdapter($this->varConfigAdapter)->getDriver()->getConnection()->getConnectionParameters());
        $explain->explain($query);

        $return = null;

        $legenda = '
            <ul>
                <li><b>Startup Cost</b> - É o custo de execução da consulta até o inicio da saída dos resultados. Em outros casos, pode ser que o maior custo de execução de consulta seja exatamente este custo inicial.</li>
                <li><b>Total Cost</b> - Custo total estimado considerando que todas as linhas serão retornadas. O uso de LIMIT em consultas faz com que o custo total seja reduzido.</li>
                <li><b>Plan Rows</b> - Estimativa de quantidade de registros retornados.</li>
                <li><b>Plan Width</b> - Estimativa de quantidade de bytes retornados.</li>
                <li><b>Relation Name</b> - Nome da tabela de relacionamento.</li>
                <li><b>Index Name</b> - Indice utilizado referente à tabela de relacionamento.</li>
                <li><b>Index Cond</b> - Condição do Indice utilizado referente à tabela de relacionamento.</li>
            </ul>';

        $return .= "<html><body>";
        $return .= "<p><pre>";
        $return .= \Cityware\Format\Sql::format($query);
        $return .= "</pre></p><br />";
        $return .= $legenda . "<br />";
        $return .= $explain->render();
        //echo '<p><pre>'.$return['string'].'</pre></p><br />';
        $return .= "</body></html>";
        echo $return;
        exit;
    }

    /**
     * FUNCAO QUE LIBERA A MEMORIA DEPOIS DA UTILIZAÇÃO
     */
    private static function freeMemory() {
        $variaveis = get_class_vars(get_class());
        foreach ($variaveis as $nome => $valor) {
            $varChecker = new \ReflectionProperty(__CLASS__, $nome);
            if ($varChecker->isStatic()) {
                if (is_array($valor)) {
                    self::${$nome} = NULL;
                    self::${$nome} = Array();
                } else {
                    self::${$nome} = NULL;
                }
            }
        }
    }

    /**
     * FUNCAO QUE LIBERA A MEMORIA DEPOIS DA UTILIZAÇÃO
     */
    private static function freeMemoryUnion() {
        $variaveis = get_class_vars(get_class());
        foreach ($variaveis as $nome => $valor) {
            if ($nome != 'varSqlUnion') {
                $varChecker = new \ReflectionProperty(__CLASS__, $nome);
                if ($varChecker->isStatic()) {
                    if (is_array($valor)) {
                        self::${$nome} = NULL;
                        self::${$nome} = Array();
                    } else {
                        self::${$nome} = NULL;
                    }
                }
            }
        }
    }

    /**
     * Função que pega a coluna de PK
     * @param string $table
     * @param string $schema
     * @return string
     */
    public function getPrimaryColumn($table, $schema) {
        $metadata = new zendMetadata($this->getAdapter($this->varConfigAdapter));
        $tableColumns = $metadata->getConstraints($table, $schema);
        $returnPrimaryColumn = null;
        foreach ($tableColumns as $value) {
            if ($value->getType() == 'PRIMARY KEY') {
                $arrayColumns = $value->getColumns();
                $returnPrimaryColumn = $arrayColumns[0];
            }
        }

        return $returnPrimaryColumn;
    }

    /**
     * Função que retorna o post formatado de acordo com a tabela
     * @param array $arrayPost
     * @param string $table
     * @param string $schema
     * @return array
     */
    public function fromArray(array $arrayPost, $table, $schema) {
        $metadata = new zendMetadata($this->getAdapter($this->varConfigAdapter));
        $tableColumns = $metadata->getColumns($table, $schema);
        $returnPost = Array();

        if (count($arrayPost) > 0) {
            foreach ($tableColumns as $key => $value) {
                if (isset($arrayPost[$value->getName()])) {
                    if (trim($arrayPost[$value->getName()]) == '') {
                        $returnPost[$value->getName()] = null;
                    } else {
                        $returnPost[$value->getName()] = $arrayPost[$value->getName()];
                    }
                } else {
                    unset($arrayPost[$key]);
                }
            }
        }

        return $returnPost;
    }

    /**
     * Gera o Log das ações de INSERT, UPDATE e DELETE executados pla plataforma
     * @param string $query
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    private function geraLogSistema($query, $queryAction) {

        try {
            /* Pega o IP do usuário */
            $ip = new \Cityware\Utility\Ip\CaptureIp();

            /* Obtem o indece da sessão */
            $indexSession = strtoupper($this->aSession['moduleName']);

            $sessionNamespace = new SessionContainer($indexSession);
            $aSession = $sessionNamespace->getArrayCopy();

            $this->varExecuteLog = false;

            if (isset($aSession['acl'])) {
                unset($aSession['acl']);
            }

            if (isset($aSession['menu'])) {
                unset($aSession['menu']);
            }

            $this->insert('des_modulo', $this->aSession['moduleName']);
            $this->insert('des_acao', $this->aSession['actionName']);
            $this->insert('des_controlador', $this->aSession['controllerName']);

            $this->insert('des_session', json_encode($aSession));
            $this->insert('des_queryaction', strtoupper($queryAction));
            $this->insert('des_sqlquery', str_replace("'", '\"', $query));
            $this->insert('des_phpserver', json_encode($_SERVER));

            $this->insert('des_ip', $ip->IP['client']);
            $this->insert('des_proxy', $ip->IP['proxy']);
            $this->insert('dta_cadastro', date('Y-m-d H:i:s'));
            $this->insert('ind_status', 'A');
            $this->from('tab_db_log', null, 'log');
            $this->setDebug(false);
            $this->setReturnInsertId(false);
            $this->setAdapter('adapter');
            $this->executeInsertQuery();

            $this->varExecuteLog = true;
        } catch (\Exception $exc) {
            throw new \Exception('Erro ao tentar gravar o log do sistema - ' . $exc->getMessage());
        }
    }

}
