<?php

namespace Cityware\Db\Models;

class CrudDb {

    private static $primaryColumn = null, $debug = false, $requestValues = null;

    public function setRequestValues($values) {
        foreach ($values as $key => $value) {
            if(empty($value)){
                unset($values[$key]);
            }
        }
        self::$requestValues = $values;
    }

    public function getRequestValues() {
        return self::$requestValues;
    }
    
    public function setRequestValue($key, $value) {
        self::$requestValues[$key] = $value;
    }
    
    public function getRequestValue($key) {
        return self::$requestValues[$key];
    }

    public function setPrimaryColumn($column) {
        self::$primaryColumn = $column;
    }

    public function getPrimaryColumn() {
        return self::$primaryColumn;
    }

    public function setDebug($debug = false) {
        self::$debug = $debug;
    }

    public function getDebug() {
        return self::$debug;
    }
    
    /**
     * C.R.U.D GENERICO
     * FUNCAO RESPONSAVEL PELA MANIPULACAO BASICA DAS INFORMACOES DISPONIVEIS NO BANCO DE DADOS
     * FORAM DEFINIDOS OS SEGUINTES CASOS: INSERIR, ATUALIZAR, ATIVAR, DESATIVAR, RECICLAR, RECUPERAR E EXCLUIR
     * 
     * @param string $varAction
     * @param array $options
     * @param integer $varPage
     * @param integer $varLimit
     * @return boolean
     * @throws \Exception
     */
    public function execute($varAction, array $options, $varPage = 1, $varLimit = 10) {
        
        if(isset($options['debug']) and $options['debug'] == 'true'){
            $this->setDebug(true);
        }

        /* Seleciona a tabela padrão do datagrid */
        $db = \Cityware\Db\Factory::factory();
        
        $primaryColumnName = $db->getPrimaryColumn($options['table'], $options['schema']);
        
        /** Define a coluna de chave primária */
        $this->setPrimaryColumn($primaryColumnName);

        /* RECUPERA O TIPO DA ACAO ATRAVES DA CHAMADA DA FUNCAO OU RECUPERANDO A VARIAVEL POST['action'] RESERVADA */
        $typeAction = (string) strtoupper((((isset($varAction)) && (!empty($varAction))) ? $varAction : (((isset($_POST['action'])) && (!empty($_POST['action']))) ? $_POST['action'] : null)));

        /* INSTANCIA E RECUPERA AS INFORMACOES DA TABELA SELECIONADA */
        if ($options['table'] !== null) {

            /*  DEFINE A TABELA SELECIONADA */
            $db->from($options['table'], null, $options['schema']);
            
            
            if ((strtolower($typeAction) != 'add') and (strtolower($typeAction) != 'select')) {
                if (is_numeric($this->getRequestValue('id'))) {
                    $id = $this->getRequestValue('id');
                } else if (is_numeric($this->getRequestValue($primaryColumnName))) {
                    $id = $this->getRequestValue($primaryColumnName);
                } else {
                    $return = false;
                    throw new \Exception('O identificador ID definido incorretamente', 500);
                }
            }

            /** Formatação de campos de data automaticamente para o banco de dados */
            $this->setRequestValues(\Cityware\Format\Date::formatDate($this->getRequestValues(), 'Y-m-d'));
            
            
            /** Definição de gravação das datas de cadastro e atualização */
            if (strtolower($typeAction) == 'add') {
                $this->setRequestValue('dta_cadastro', date('Y-m-d H:m:s'));
                $this->setRequestValue('dta_atualizacao', date('Y-m-d H:m:s'));
            } else {
                $this->setRequestValue('dta_atualizacao', date('Y-m-d H:m:s'));
            }
            
            /** Limpesa de campos não necessários para a tabela */
            $this->setRequestValues($db->fromArray($this->getRequestValues(), $options['table'], $options['schema']));
            
            if(!in_array($primaryColumnName, $this->getRequestValues())){
                //$this->setRequestValue($primaryColumnName, null);
            }
            
            switch (strtolower($typeAction)) {
                /** CASO PARA INSERIR NOVOS DADOS */
                case 'add':
                    try {
                        foreach ($this->getRequestValues() as $key => $value) {
                            $db->insert($key, $value);
                        }
                        /* Executa o debug da query */
                        $db->setDebug($this->getDebug());
                        $return = $db->executeInsertQuery();
                    } catch (\Zend\Db\Exception\ErrorException $exc) {
                        throw new \Exception('Ocorreu um erro ao tentar inserir dados na tabela: <b>' . $options['table'] . '</b> na ação de inserir<br /><br />' . $exc->getMessage(), 500);
                    }
                    break;
                /** CASO PARA ATUALIZAR OS DADOS SELECIONADOS */
                case 'edit':
                    try {
                        foreach ($this->getRequestValues() as $key => $value) {
                            $db->update($key, $value);
                        }
                        $db->where("{$this->getPrimaryColumn()} = '{$id}'");
                        $db->setDebug($this->getDebug());
                        $db->executeUpdateQuery();
                        $return = $id;
                    } catch (\Zend\Db\Exception\ErrorException $exc) {
                        throw new \Exception('Ocorreu erro ao tentar atualizar os dados da tabela: <b>' . $options['table'] . '</b> na ação de atualizar!<br /><br />' . $exc->getMessage(), 500);
                    }
                    break;
                /** CASO PARA ATIVAR (TORNAR ATIVO) OS DADOS SELECIONADOS */
                case 'active':
                    try {
                        $db->update('ind_status', "A");
                        $db->where("{$this->getPrimaryColumn()} = '{$id}'");
                        $db->setDebug($this->getDebug());
                        $db->executeUpdateQuery();
                    } catch (\Zend\Db\Exception\ErrorException $exc) {
                        throw new \Exception('Ocorreu erro ao tentar atualizar os dados da tabela: <b>' . $options['table'] . '</b> na ação de ativa!<br /><br />' . $exc->getMessage(), 500);
                    }
                    break;
                /** CASO PARA DESATIVAR (TORNAR INATIVO) OS DADOS SELECIONADOS */
                case 'deactivate':
                    try {
                        $db->update('ind_status', "B");
                        $db->where("{$this->getPrimaryColumn()} = '{$id}'");
                        $db->setDebug($this->getDebug());
                        $db->executeUpdateQuery();
                    } catch (\Zend\Db\Exception\ErrorException $exc) {
                        throw new \Exception('Ocorreu erro ao tentar atualizar os dados da tabela: <b>' . $options['table'] . '</b> na ação de bloquear!<br /><br />' . $exc->getMessage(), 500);
                    }
                    break;
                /** CASO PARA RESTAURAR (RECUPERAR DA LIXEIRA) OS DADOS SELECIONADOS */
                case 'restore':
                    try {
                        $db->update('ind_status', "B");
                        $db->where("{$this->getPrimaryColumn()} = '{$id}'");
                        $db->setDebug($this->getDebug());
                        $db->executeUpdateQuery();
                    } catch (\Zend\Db\Exception\ErrorException $exc) {
                        throw new \Exception('Ocorreu erro ao tentar atualizar os dados da tabela: <b>' . $options['table'] . '</b> na ação de restaurar!<br /><br />' . $exc->getMessage(), 500);
                    }
                    break;
                /** CASO PARA RECICLAR (ENVIAR PARA A LIXEIRA) OS DADOS SELECIONADOS */
                case 'trash':
                    try {
                        $db->update('ind_status', "L");
                        $db->where("{$this->getPrimaryColumn()} = '{$id}'");
                        $db->setDebug($this->getDebug());
                        $db->executeUpdateQuery();
                    } catch (\Zend\Db\Exception\ErrorException $exc) {
                        throw new \Exception('Ocorreu erro ao tentar atualizar os dados da tabela: <b>' . $options['table'] . '</b> na ação de reciclar!<br /><br />' . $exc->getMessage(), 500);
                    }
                    break;
                /** CASO PARA EXCLUIR DEFINITIVAMENTE OS DADOS SELECIONADOS */
                case 'delete':
                    try {
                        $db->update('ind_status', "I");
                        $db->where("{$this->getPrimaryColumn()} = '{$id}'");
                        $db->setDebug($this->getDebug());
                        $db->executeUpdateQuery();
                    } catch (\Zend\Db\Exception\ErrorException $exc) {
                        throw new \Exception('Ocorreu erro ao tentar atualizar os dados da tabela: <b>' . $options['table'] . '</b> na ação de excluir!<br /><br />' . $exc->getMessage(), 500);
                    }
                    break;
                /** CASO PARA SELECIONAR E PAGINAR OS DADOS SELECIONADOS */
                case 'pagination':
                    try {
                        $db->select("*");
                        $return = $db->executeSelectQuery(true, $varPage, $varLimit);
                    } catch (\Zend\Db\Exception\ErrorException $exc) {
                        throw new \Exception('Ocorreu erro ao tentar selecionar os dados da tabela: <b>' . $options['table'] . '</b> na ação de paginar!<br /><br />' . $exc->getMessage(), 500);
                    }
                    break;
                /** CASO PARA SELECIONAR OS DADOS */
                case 'select':
                    try {
                        $db->select("*");
                        $return = $db->executeSelectQuery();
                    } catch (\Zend\Db\Exception\ErrorException\ErrorException $exc) {
                        throw new \Exception('Ocorreu erro ao tentar selecionar os dados da tabela: <b>' . $options['table'] . '</b> na ação de selecionar!<br /><br />' . $exc->getMessage(), 500);
                    }
                    break;
                /** CASO PARA SELECIONAR OS DADOS POR ID */
                case 'selectid':
                    try {
                        $db->select("*");
                        $db->where("{$this->getPrimaryColumn()} = '{$id}'");
                        $return = $db->executeSelectQuery();
                    } catch (\Zend\Db\Exception\ErrorException\ErrorException $exc) {
                        throw new \Exception('Ocorreu erro ao tentar selecionar os dados da tabela: <b>' . $options['table'] . '</b> na ação de selecionar por id!<br /><br />' . $exc->getMessage(), 500);
                    }
                    break;
            }
        } else {
            $return = false;
            throw new \Exception('A tabela não foi definida!', 500);
        }
        self::freeMemory();
        return $return;
    }
    
    /**
     * FUNCAO QUE LIBERA A MEMORIA DEPOIS DA UTILIZAÇÃO
     */
    private static function freeMemory() {
        $variaveis = get_class_vars(get_class());

        foreach ($variaveis as $nome => $valor) {
            if (is_array($valor)) {
                eval('self::$' . $nome . ' = NULL;');
                eval('self::$' . $nome . ' = Array();');
            } else {
                eval('self::$' . $nome . ' = NULL;');
            }
        }
    }

}