<?php

namespace Subvitamine\Service;

use Subvitamine\DbTable;

abstract class AbstractService extends \Subvitamine\DbTable\AbstractDbTable {

    protected $_adapter = null;
    protected $_name = null;

    public function __construct($tableName = 'user', $adapter = null) {
        try {
            $this->_name = $tableName;

            if ($adapter == null) {
                $c = \Zend_Registry::get('Zend_Config');
                $this->_adapter = $c->multidbname;
            } else {
                $this->_adapter = $adapter;
            }

            parent::__construct();
        } catch (\Zend_Db_Adapter_Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

}
