<?php

namespace Subvitamine\Controller;

class AbstractWww extends \Zend_Controller_Action {

    protected $_config;
    protected $_routes;
    protected $_module;
    protected $_controller;
    protected $_action;

    public function init() {

        // set configs
        $this->_config = \Zend_Registry::get('Zend_Config');
        $this->_routes = \Zend_Registry::get('Zend_Routes');

        // set params
        $this->_module = $this->getRequest()->getModuleName();
        $this->_controller = $this->getRequest()->getControllerName();
        $this->_action = $this->getRequest()->getActionName();

        // set views
        $this->view->module = $this->_module;
        $this->view->controller = $this->_controller;
        $this->view->action = $this->_action;
    }

    public function preDispatch() {

        // set template
        $this->_helper->layout->setLayout('www');
    }

}
