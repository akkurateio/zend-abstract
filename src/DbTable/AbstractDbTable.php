<?php

namespace Subvitamine\DbTable;

use Subvitamine\Libs;

abstract class AbstractDbTable extends \Zend_Db_Table_Abstract {

    protected $_adapter = null;
    protected $_object = null;
    protected $_ignoredFilters = array();
    protected $_emptyTagsPattern = "#<[^/>]+>[\xA0\s \n\r\t]*</[^>]+>#";

    public function __construct($config = array()) {
        try {
            if ($this->_adapter !== null) {
                $c = \Zend_Registry::get('Zend_Config');
                $db = $c->resources->multidb->{$this->_adapter}->toArray();
                $config = array(self::ADAPTER => \Zend_Db::factory($db['adapter'], $db));
                $this->getAdapter();

                parent::__construct($config);
            }
        } catch (\Zend_Db_Adapter_Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Get the primary key
     * @return string|array
     */
    public function getPrimaryKey() {
        return $this->_primary;
    }

    /**
     * Get the reference map
     * @return array
     */
    public function getReferenceMap() {
        return $this->_referenceMap;
    }

    /**
     * Get the fields list
     * @return array
     */
    public function getFieldsList() {
//        $db = Zend_Db_Table::getDefaultAdapter();
//        $metadatas = $db->describeTable($this->_tableName);

        $metadatas = $this->describeTable($this->_tableName);

//        var_dump($metadatas);
//        exit();

        $columns = array();
        $i = 0;
        foreach ($metadatas as $metadata) {

            $columns[$i] = new stdClass();

            // get field name
            $columns[$i]->field = $metadata['COLUMN_NAME'];

            // get field type 
            $type = $metadata['DATA_TYPE'];

            // format type in order to cast the bean properties, e.g. (int)
            switch ($type) {
                case 'int':
                case 'float':
                    $type = '(' . $type . ')';
                    break;
                case 'tinyint' :
                    $type = '(boolean)';
                    break;
                default:
                    $type = '';
            }

            $columns[$i]->type = $type;
            $i++;
        }

        return $columns;
    }

    /**
     * Récupération d'un attribut sous son objet
     * @param unknown $id
     */
    public function getObject($id, $objetComplete = false) {
        $row = $this->find($id);

        if (!isset($row[0])) {
            return null;
        } else {
            return $this->makeOneObject($row[0], $objetComplete);
        }
    }

    /**
     * Récupération de tous les attributs sous leur objet
     * @param string|array $order
     * @param boolean $objectComplete
     * @return array
     */
    public function getAllObjects($order = null, $objectComplete = false) {
        $query = $this->select();
        if (!empty($order)) {
            $query->order($order);
        }

        $list = $this->_makeObjects($this->fetchAll($query), $objectComplete);
        return $list;
    }

    public function getList() {
        $rows = $this->cleanfetchAll();
        if (!$rows) {
            return array();
        }
        return $rows;
    }

    /**
     * Permet de récupérer une ligne de données selon une clef et une valeur
     * 
     * @param mixed $val
     * @param string $key
     * @return array
     */
    public function get($val, $key = 'id') {
        $select = $this->select()
                ->where("$key = ?", $val);

        return $this->cleanFetchRow($select);
    }

    /**
     * Fonction qui retourne le nom de la table
     * 
     * @return string
     */
    public function getTableName() {
        return $this->_name;
    }

    public function getAdapterName() {
        return $this->_adapter;
    }

    public function makeObjects($rows, $objectComplete = false) {
        return $this->_makeObjects($rows, $objectComplete);
    }

    /**
     * Créattion d'objets
     * @param \Zend_Db_Table_Rowset|array $rows
     * @return array
     */
    protected function _makeObjects($rows, $objectComplete = false) {
        $result = null;
        if (!empty($rows)) {
            $result = array();
            foreach ($rows as $row) {
                $result[] = $this->_makeObject($row, $objectComplete);
            }
        }
        return $result;
    }

    public function makeOneObject($row, $objectComplete = false) {
        if ($row) {
            return $this->_makeObject($row, $objectComplete);
        } else {
            return null;
        }
    }

    /**
     * abstract protected function _makeObject($row);
     */
    protected function _makeObject($row, $objectComplete = false) {
        if ($row) {
            $object = null;
            if ($this->_object != null) {
                $objectName = '\\' . $this->_object;
                $object = new $objectName($row, $objectComplete);
            }

            return $object;
        } else {
            return null;
        }
    }

    public function cleanFetchAll($select = null, $object = false) {
        $rows = $this->fetchAll($select);
        $return = array();
        if (!empty($rows)) {
            $return = $rows->toArray();
        }
        unset($rows);

        if ($object === true) {
            return \Subvitamine\Libs\LibArray::arrayToObject($return);
        }

        return $return;
    }

    public function cleanFetchRow($select = null, $object = false) {

        $row = $this->fetchRow($select);
        $return = array();
        if (!empty($row)) {
            $return = $row->toArray();
        }
        unset($row);

        if ($object === true) {
            return \Subvitamine\Libs\LibArray::arrayToObject($return);
        }

        return $return;
    }

    /*
     * Truncate de la Table 
     */

    public function truncateTable() {
        $adapter = $this->getAdapter();
        // On commence par complètement vider la table
        $query = 'TRUNCATE TABLE ' . $this->getTableName();
        $adapter->query($query);
    }

    //Fonction d'affichage des options dans le select
    public function getListForSelect($field) {
        $select = $this->select()
                ->from($this->getTableName(), $field);

        $rows = $this->cleanfetchAll($select);
        if (!$rows) {
            return array();
        }

        $return = array('0' => '');
        foreach ($rows as $r) {
            $return[$r[$field]] = $r[$field];
        }
        return $return;
    }

    protected function setLastRequest($request) {
        $this->_lastRequest = $request;
    }

    /**
     * Replace function to execute a MySQL REPLACE.
     * @param array $data data array just as if it was for insert()
     * @return \Zend_Db_Statement_Mysqli
     */
    public function replace($data) {
        // get the columns for the table
        $tableInfo = $this->info();
        $tableColumns = $tableInfo['cols'];

        // columns submitted for insert
        $dataColumns = array_keys($data);

        // intersection of table and insert cols
        $valueColumns = array_intersect($tableColumns, $dataColumns);
        sort($valueColumns);

        // Check on fields
        foreach ($data as $key => $val) {
            if (!in_array($key, $valueColumns)) {
                throw new \Zend_Db_Table_Exception("REPLACE ERROR : Unknown column '$key' in 'field list'");
            }
        }

        // generate SQL statement
        $cols = '';
        $vals = '';
        foreach ($valueColumns as $col) {
            $cols .= $this->getAdapter()->quoteIdentifier($col) . ',';
            $vals .= (is_object($data[$col]) && get_class($data[$col]) == 'Zend_Db_Expr') ? '(' . $data[$col]->__toString() . ')' : $this->getAdapter()->quoteInto('?', $data[$col]);
            $vals .= ',';
        }
        $cols = rtrim($cols, ',');
        $vals = rtrim($vals, ',');
        $sql = 'INSERT INTO ' . $this->_name . ' (' . $cols . ') VALUES (' . $vals . ') ON DUPLICATE KEY UPDATE ';
        foreach ($valueColumns as $col) {
            $sql .= "$col = VALUES($col), ";
        }
        $sql = substr($sql, 0, -2);
        $sql .= ';';

        return $this->_db->query($sql);
    }

    /**
     * Mise à jour de toutes les données
     * @param array $data
     * @return int
     */
    public function updateAll(array $data) {
        return $this->update($data, null);
    }

    /**
     * Formatage des données avant insert ou update
     * @param array $data
     * @return array
     */
    private function _cleanData(&$data) {
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $data[$key] = ($value === true) ? 1 : 0;
            }
        }
        return $data;
    }

    /**
     * Surcharge de la fonction insert
     * {@inheritDoc}
     * @see Zend_Db_Table_Abstract::insert()
     */
    public function insert(array $data) {
        $data = $this->_filterStripTags($data);
        $data = $this->_cleanData($data);
        $data['date_created'] = $data['date_updated'] = date('Y-m-d H:i:s');
        return parent::insert($data);
    }

    /**
     * Surcharge de la fonction update
     * {@inheritDoc}
     * @see Zend_Db_Table_Abstract::insert()
     */
    public function update(array $data, $where) {
        $data = $this->_filterStripTags($data);
        $data = $this->_cleanData($data);
        $data['date_updated'] = date('Y-m-d H:i:s');
        return parent::update($data, $where);
    }

    private function _filterStripTags($data) {
        $filter = new \Zend_Filter_StripTags();
        foreach ($data as $key => &$value) {
            if ((!is_object($value)) && (!empty($value))) {
                if (!in_array($key, $this->_ignoredFilters)) {
                    $value = $filter->filter($value);
                }
            }
        }

        return $data;
    }

    /**
     * Get Paginator
     * @param Zend_Db_Table_Select $query
     * @return array
     */
    public function getPaginator($query, $page, $limit) {
        $select = new \Zend_Paginator_Adapter_DbTableSelect($query);
        $select->count($select);
        $paginator = new \Zend_Paginator($select);
        $paginator->setCurrentPageNumber($page);
        $paginator->setItemCountPerPage($limit);
        return $this->makeObjects($paginator);
    }

    /**
     * Création d'une activité si un tableau d'activité existe dans le mapper courant
     * @param string $method
     * @param integer $itemID
     * @param integer $userID
     * @param array $newItem
     */
    public function activity($method, $itemID, $userID, $newItem = null) {
        if (
                defined('LOGBOOK_ACTIVE') &&
                defined('LOGBOOK_CONFIG') &&
                (int) LOGBOOK_ACTIVE === 1 &&
                !empty(LOGBOOK_CONFIG['mapper']) &&
                !empty($this->_activities) &&
                !empty($this->_activities['enabled']) &&
                $this->_activities['enabled'] === true &&
                !empty($this->_activities['methods']) &&
                !empty($this->_activities['methods'][strtoupper($method)])
        ) {
            $mapperName = LOGBOOK_CONFIG['mapper'];
            $mapper = new $mapperName();
            $mapper->save(strtoupper($method), $this->_activities, $this->_adapter, $this->_name, $this, $itemID, $userID, $newItem);
        }
    }

}
