<?php

namespace Cityware\Db\Adapter;

abstract class AdapterAbstract
{
    /**
     * Conexão padrão
     * @param string $adapterName
     * @return object
     * @throws Exception
     */
    abstract public function getAdapter($adapterName = null);


    /**
     * Função que define a exibição do debug
     * @param type $debug
     */
    abstract public function setDebug($debug = false);
    
    /**
     * Função de definição de ativação de LOG
     * @param type $log
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function setLog($log = false);
    
    /**
     * Função de definição de sessão
     * @param type $session
     */
    abstract public static function setSession($session);
            
    /**
     * Função que define se haverá retorno no INSERT
     * @param type $varReturnInsertId
     */
    abstract public function setReturnInsertId($varReturnInsertId);


    /**
     * Função que define o adaptador de conexção a ser utilizado
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function setAdapter($adapterName = null);
    
    /**
     * Função que define a exibição do debug
     * @param boolean $explan
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function setExplan($explan = false);
    
    /**
     * FUNCAO QUE DEFINE A CHAVE DE VERIFICAÇÃO DE CACHE
     * @param string $cacheKey NOME DA CHAVE
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function cacheKey($cacheKey);

    /**
     * FUNCAO QUE DEFINE O PARAMETRO SELECT
     * @param string $varSqlSelect NOME DO CAMPO PARA REALIZAR O SELECT
     * @param string $varSqlSelectAlias
     * @param string $isExpression
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function select($varSqlSelect, $varSqlSelectAlias = null, $isExpression = false);

    /**
     * FUNCAO QUE DEFINE O PARAMETRO DISTINCT
     * Sempre pegará a pimeira coluna de seleção da query
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function distinct();

    /**
     * FUNCAO QUE DEFINE O PARAMETRO UPDATE
     * @param string $varSqlUpdate VALORES DOS UPDATES
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function update($varSqlUpdateCol, $varSqlUpdateValue);

    /**
     * FUNCAO QUE DEFINE O PARAMETRO INSERT
     * @param string $varSqlUpdate VALORES DOS UPDATES
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function insert($varSqlInsertCol, $varSqlInsertValue);

    /**
     * FUNCAO QUE DEFINE O PARAMETRO SCHEMA INSERT
     * @param string $varSqlSchema
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function schema($varSqlSchema);

    /**
     * FUNCAO QUE DEFINE O PARAMETRO SEQUENCE
     * @param string $varSqlSequence
     * @param string $schema
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function sequence($varSqlSequence, $schema = null);

    /**
     * FUNCAO QUE DEFINE O PARAMETRO FROM
     * @param string $varSqlFrom
     * @param string $alias
     * @param string $schema
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function from($varSqlFrom, $alias = null, $schema = null);

    /**
     * FUNCAO QUE DEFINE O PARAMETRO WHERE
     * @param string $varSqlWhere
     * @param string $condition
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function where($varSqlWhere, $condition = "AND");

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
    abstract public function join($varSqlTableJoin, $aliasTable = null, $condition = null, $type = null, $schema = null);

    /**
     * FUNCAO QUE DEFINE O PARAMETRO GROUPBY
     * @param string $varSqlGroupBy
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function groupBy($varSqlGroupBy);

    /**
     * FUNCAO QUE DEFINE O PARAMETRO HAVING
     * @param string $varSqlHaving
     * @param string $condition
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function having($varSqlHaving, $condition = "AND");

    /**
     * FUNCAO QUE DEFINE O PARAMETRO ORDERBY
     * @param string $varSqlOrderBy
     * @param string $expr
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function orderBy($varSqlOrderBy, $expr = false);

    /**
     * FUNCAO QUE DEFINE O PARAMETRO LIMIT e/ou OFFSET
     * @param integer $varSqlLimit  REGISTRO INICIO
     * @param integer $varSqlOffset REGISTRO FINAL
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function limit($varSqlLimit, $varSqlOffset = null);

    /**
     * Função utilizada para definir chamada de TRANSACTION
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function transaction($adapterName = null);

    /**
     * Função de execução de COMMIT
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function commit($adapterName = null);

    /**
     * Função de execução de ROLLBACK
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function rollback($adapterName = null);
    
    /**
     * Função utilizada para definir chamada de TRANSACTION identificada
     * @param string $transactionId
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function prepareTransaction($transactionId, $adapterName = null);

    /**
     * Função de execução de COMMIT de transação identificada
     * @param string $transactionId
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function preparedCommit($transactionId, $adapterName = null);

    /**
     * Função de execução de ROLLBACK de transação identificada
     * @param string $transactionId
     * @param string $adapterName
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function preparedRollback($transactionId, $adapterName = null);
    
    /**
     * Função de definição de commit
     * @return \Cityware\Db\Adapter\ZendAdapter
     */
    abstract public function closeConnection();
    
    /**
     * FUNCAO QUE EXECUTA O COMANDO SELECT NO BANCO DE DADOS OU BUSCA NO CACHE GRAVADO
     * @param  boolean $activationPaginator
     * @param  integer $pageNumber
     * @param  integer $limitPerPage
     * @return mixed
     * @throws \Exception
     */
    abstract public function executeSelectQueryCache($activationPaginator = false, $pageNumber = 1, $limitPerPage = 10);

    /**
     * FUNCAO QUE MONTA O COMANDO SUB-SELECT NO BANCO DE DADOS E RETORNA A STRING
     * @return mixed
     * @throws Exception
     */
    abstract public function executeSubSelectQuery();
    
    /**
     * FUNCAO QUE EXECUTA O COMANDO SELECT NO BANCO DE DADOS
     * @param  boolean $activationPaginator
     * @param  integer $pageNumber
     * @param  integer $limitPerPage
     * @return mixed
     * @throws \Exception
     */
    abstract public function executeSelectQuery($activationPaginator = false, $pageNumber = 1, $limitPerPage = 10);    

    /**
     * FUNCAO QUE EXECUTA O STATEMENT DO COMANDO SELECT NO BANCO DE DADOS
     * @return mixed
     * @throws \Exception
     */
    abstract public function statementSelectQuery();
    
    /**
     * Função que converte o resultado do EXECUTE em array
     * @param \Zend\Db\Adapter\Driver\Pdo\Result $results
     * @return type
     */
    abstract public function toArrayFromExecute(\Zend\Db\Adapter\Driver\Pdo\Result $results);
    
    /**
     * FUNCAO QUE EXECUTA UM COMANDO QUALQUER NO BANCO DE DADOS OU BUSCA NO CACHE GRAVADO
     * ESTA FUNCAO MASCARA O USO DO ZEND_DB NO SISTEMA
     * @param  string  $varSqlQuery
     * @param  boolean $activationPaginator
     * @param  integer $pageNumber
     * @param  integer $limitPerPage
     * @return boolean
     * @throws \Exception
     */
    abstract public function executeSqlQueryCache($varSqlQuery, $activationPaginator = false, $pageNumber = 1, $limitPerPage = 10);

    /**
     * FUNCAO QUE EXECUTA UM COMANDO QUALQUER NO BANCO DE DADOS
     * ESTA FUNCAO MASCARA O USO DO ZEND_DB NO SISTEMA
     * @param  string  $varSqlQuery
     * @param  boolean $activationPaginator
     * @param  integer $pageNumber
     * @param  integer $limitPerPage
     * @return boolean
     * @throws \Exception
     */
    abstract public function executeSqlQuery($varSqlQuery, $activationPaginator = false, $pageNumber = 1, $limitPerPage = 10);
    
    /**
     * FUNCAO QUE EXECUTA O COMANDO UPDATE NO BANCO DE DADOS
     * @return mixed
     * @throws \Exception
     */
    abstract public function executeUpdateQuery();

    /**
     * FUNCAO QUE EXECUTA O COMANDO INSERT NO BANCO DE DADOS
     * @return mixed
     * @throws \Exception
     */
    abstract public function executeInsertQuery();

    /*
     * FUNCAO QUE EXECUTA O COMANDO DELETE NO BANCO DE DADOS
     * @return boolean
     * @throws \Exception
     */
    abstract public function executeDeleteQuery();
    
    /*
     * FUNCAO QUE EXECUTA O COMANDO DELETE NO BANCO DE DADOS
     * @return boolean
     * @throws \Exception
     */
    abstract public function statementDeleteQuery();
    
    /**
     * Função que pega previamente o ID do registro a ser inserido
     * @param  string   $sequence
     * @return integer
     */
    abstract public function executeNextSequenceId($sequence);

    
    /**
     * Função que pega a coluna de PK
     * @param string $table
     * @param string $schema
     * @return string
     */
    abstract public function getPrimaryColumn($table, $schema);
    
    /**
     * Função que retorna o post formatado de acordo com a tabela
     * @param array $arrayPost
     * @param string $table
     * @param string $schema
     * @return array
     */
    abstract public function fromArray(array $arrayPost, $table, $schema);
}
