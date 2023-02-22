<?php

namespace Subvitamine\Bean;

use Subvitamine\Libs;

/**
 * Classe de bean abstraite offrant les fonctionnalités génériques de manipulation des données Bean
 */
abstract class AbstractBeanV2 {

    protected $_vars = array();

    /*     * *************** */
    /* MAGIC FUNCTION */
    /*     * *************** */

    /**
     * Constructeur.
     * 
     * @param $options
     * @param boolean $objectComplete
     * @param string $schema
     */
    public function __construct($options = array(), $objectComplete = false) {
        $this->setOptions($options);

        if (is_object($options) && $objectComplete == true && get_class($options) == 'Zend_Db_Table_Row') {

            //Get references
            $this->setReferences($options);

            //Get dependencies
            $this->setDependencies($options);
        }
    }

    /**
     * Méthode magique. Raccourci pour que $obj->foo = 'bar' fonctionne.
     * 
     * @param   string  $name
     * @param   mixed   $value
     * @return  Application_Model_Abstract
     */
    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    /**
     * Méthode magique. Raccourci pour que $bar = $obj->foo fonctionne.
     * 
     * @param   string  $name
     * @return  mixed
     */
    public function __get($name) {
        return $this->get($name);
    }

    public function __call($method, $args) {
        if (is_callable($this->methods[$method])) {
            return call_user_func_array($this->methods[$method], $args);
        } else {
            if (preg_match('/^get/', $method)) {
                $key = strtolower(substr($method, 3));
                return($this->_vars[$key]);
            } else if (preg_match('/^set/', $method)) {
                $key = strtolower(substr($method, 3));
                $this->_vars[$key] = $args[0];
            }
        }
    }

    /*     * ******** */
    /* GETTERS */
    /*     * ******** */

    /**
     * Retourne la valeur de la propriété $name si celle-ci existe.
     * Sinon $default.
     * 
     * @param   string  $name
     * @param   mixed   $default
     * @return  mixed|$default
     */
    public function get($name, $default = null) {
        $methodName = 'get' . ucfirst($name);
        if (method_exists($this, $methodName)) {
            return $this->{$methodName}();
        } else if (isset($this->_vars[$name])) {
            return $this->_vars[$name];
        }
        return $default;
    }

    /*     * ****** */
    /* SETTERS */
    /*     * ****** */

    /**
     * Définie la propriété $name, avec la valeur $value.
     * 
     * @param   string  $name
     * @param   mixed   $value
     * @return  Application_Model_Abstract
     */
    public function set($name, $value) {
        $methodName = 'set' . ucfirst($name);

        if (method_exists($this, $methodName)) {
            $this->{$methodName}($value);
        } else {
            $this->_vars[$name] = $value;
        }

        return $this;
    }

    /**
     * Définie les propriétés du modele.
     * 
     * @param   $options
     * @return  Application_Model_Abstract
     */
    public function setOptions($options) {
        if (!empty($options)) {
            foreach ($options as $option => $value) {
                $methodName = 'set' . \Subvitamine\Libs\LibString::camelize($option);

                if (method_exists($this, $methodName)) {
                    $this->{$methodName}($value);
                }
            }
        }

        return $this;
    }

    /*     * ********** */
    /* FUNCTIONS */
    /*     * ********** */

    /**
     * Retourne un tableau contenant en index le nom des propriétés du modèle
     * et en valeur, les valeurs de ces propriétés.
     * @param array $ignoreList 
     * 
     * ex : Ignore id field in the current objet and nb_view field in his children company
     * array('id', 'company' => array('nb_view'))
     * 
     * @return  array
     */
    public function toArray($light = false, $ignoreList = array()) {
        if ($light == true) {
            if (isset($this->_ignoreList)) {
                $ignoreList = $this->_ignoreList;
            }
        }
        $vars = get_object_vars($this);

        $result = array();


        foreach ($vars as $var => $value) {
            $propertyName = substr($var, 1);
            //ignore fields in the list
            if (!in_array($propertyName, $ignoreList) && $propertyName != 'ignoreList') {
                if ($propertyName == 'vars' && !empty($propertyName)) {
                    //Construct the dependencies
                    foreach ($value as $name => $val) {
                        if (is_object($val)) {
                            $result[$name] = $val->toArray($light, (!empty($ignoreList[$name])) ? $ignoreList[$name] : array());
                        } else if (is_array($val)) {
                            if (empty($val)) {
                                $result[$name] = array();
                            } else {
                                foreach ($val as $key => $v) {
                                    if (is_object($v)) {
                                        $result[$name][$key] = $v->toArray($light, (!empty($ignoreList[$name])) ? $ignoreList[$name] : array());
                                    } else {
                                        $result[$name][$key] = $v;
                                    }
                                }
                            }
                        } else {
                            $result[$name] = $val;
                        }
                    }
                } else {
                    $getter = ucwords($var, "_");
                    $getter = str_replace('_', '', $getter);
                    $result[$propertyName] = $this->get($getter);
                }
            }
        }

        return $result;
    }

    /**
     * Formate un Bean en tableau ou stdClass en fonction de $toObject
     * 
     * @param Bean $bean
     * @param boolean $toObject
     * @return stdClass|Array
     */
    public function formatBean($bean, $toObject = false) {
        if ($toObject) {
            return \Subvitamine\Libs\LibArray::arrayToObject($bean->toArray());
        }
        return $bean->toArray();
    }

    /**
     * Generate references objects
     * @param \Zend_Db_Table_Row $option
     * @param array $accept => empty for all references or only specific
     */
    public function setReferences(\Zend_Db_Table_Row $option, $accept = array(), $where = array(), $order = array()) {
        //Get the DBTable
        $mapperName = '\\' . $option->getTableClass();

        if (class_exists($mapperName)) {
            //Get the mapper
            $mapper = new $mapperName();

            //Get the refence table
            $referencesMap = $mapper->getReferenceMap();

            //Get datas
            foreach ($referencesMap as $key => $reference) {
                if (empty($accept) || in_array($key, $accept)) {
                    // construct select
                    $tableClass = '\\' . $reference['refTableClass'];
                    $select = $this->_buildQueryForRelations(new $tableClass, $where, $order);

                    $row = $option->findParentRow($reference['refTableClass'], $key, $select);
                    $mapperReference = new $tableClass;
                    $this->$key = $mapperReference->makeOneObject($row, false);
                }
            }
        }
    }

    /**
     * Generate dependencies objects
     * @param \Zend_Db_Table_Row $option
     * @param array $accept => empty for all references or only specific
     */
    public function setDependencies(\Zend_Db_Table_Row $option, $accept = array(), $where = array(), $order = array()) {
        $tableNameCurrent = $option->getTable()->getTableName();
        //Get the dependents tables
        $dependentTables = $option->getTable()->getDependentTables();

        //Get parents datas
        foreach ($dependentTables as $mapperName) {
            $mapperName = '\\' . $mapperName;
            $mapper = new $mapperName();
            if (empty($accept) || in_array($mapper->getTableName(), $accept)) {
                $primaryKey = $mapper->getPrimaryKey();

                // Construct select for children
                $selectChildren = $this->_buildQueryForRelations($mapper, $where, $order);

                //Get dependencies
                $rows = $option->findDependentRowset($mapperName, null, $selectChildren);
                $this->{$mapper->getTableName()} = $mapper->makeObjects($rows, false);
                //Get Many to many          
                if (is_array($primaryKey) && in_array('id_' . $tableNameCurrent, $primaryKey)) {
                    $referencesMap = $mapper->getReferenceMap();
                    foreach ($referencesMap as $key => $reference) {
                        if ($key != $tableNameCurrent) {
                            // Construct select for many to many relations
                            try {
                                $tableClass = '\\' . $reference['refTableClass'];
                                $selectMany = $this->_buildQueryForRelations(new $tableClass, $where, $order);
                            } catch (Exception $ex) {
                                error_log($ex->getMessage());
                            }

                            $rows = $option->findManyToManyRowset($reference['refTableClass'], $mapperName, null, null, $selectMany);
                            $mapperReference = new $tableClass;
                            $this->$key = $mapperReference->makeObjects($rows, false);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Return mapper of current object
     * @param string $schema
     * @return Application_Model_Mapper_Schema_Model
     */
    public function getMapper($schema){
        if(!empty($this->_schema)){
            $schema = $this->_schema;
        }
        $beanName = get_class($this);
        $mapperName = '\\' . str_replace('_Bean_', '_Mapper_', $beanName);
        return new $mapperName();
    }

    /**
     * Generate all or specific children
     * @param array $childList List of children to retrieve (empty for all)
     * @param string $schema Schema where are the mappers
     */
    public function getChildren($childList = array(), $where = array(), $order = array(), $schema = 'Application') {
        //Get the current mapper
        $mapper = $this->getMapper($schema);

        //Get the current object in Zend_Db_Table_Row
        $row = $mapper->fetchRow(array('id = ?' => $this->getId()));
        $this->setDependencies($row, $childList, $where, $order);

        return $this;
    }

    /**
     * Get All or specific parents
     * @param array $parentList List of parent to retrieve (empty for all)
     * @param string $schema Schema where are the mappers
     */
    public function getParents($parentList = array(), $where = array(), $order = array(), $schema = 'Application') {
        //Get the current mapper
        $mapper = $this->getMapper($schema);

        //Get the current object in Zend_Db_Table_Row
        $row = $mapper->fetchRow(array('id = ?' => $this->getId()));

        $this->setReferences($row, $parentList, $where, $order);

        return $this;
    }

    /**
     * Get all relations (parents and children)
     */
    public function getAllRelations() {
        $this->getChildren();
        $this->getParents();

        return $this;
    }

    /**
     * Build query for all relations
     * @param Application_Model_Mapper_Application_... $mapper
     * @param array $where
     * @param array $order
     * @return \Zend_Db_Table_Select
     */
    private function _buildQueryForRelations($mapper, $where = array(), $order = array()) {
        $select = null;

        //Where clause
        if (!empty($where) && isset($where[$mapper->getTableName()])) {
            // Construct select for many to many relations
            $select = new \Zend_Db_Table_Select($mapper);
            $select->setIntegrityCheck(false);

            foreach ($where[$mapper->getTableName()] as $condition => $value) {
                $select->where($condition, $value);
            }
        }

        //Order 
        if (!empty($order) && isset($order[$mapper->getTableName()])) {
            if (empty($select)) {
                $select = new \Zend_Db_Table_Select($mapper);
                $select->setIntegrityCheck(false);
            }

            foreach ($order[$mapper->getTableName()] as $clause) {
                $select->order($clause);
            }
        }

        return $select;
    }

}
