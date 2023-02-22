<?php

namespace Subvitamine\Controller;

class AbstractAuth extends \Zend_Controller_Action {

    protected $_translate;
    protected $_module;
    protected $_controller;
    protected $_action;
    protected $_config;
    protected $_routes;
    protected $_lang;

    public function init() {

        // request
        $request = $this->getRequest();

        // set configs
        $this->_translate = \Zend_Registry::get('Zend_Translate');

        $this->_config = \Zend_Registry::get('Zend_Config');
        $this->_routes = \Zend_Registry::get('Zend_Routes');

        // set params
        $this->_module = $request->getModuleName();
        $this->_controller = $request->getControllerName();
        $this->_action = $request->getActionName();

        // set views
        $this->view->translate = $this->_translate;
        $this->view->module = $this->_module;
        $this->view->controller = $this->_controller;
        $this->view->action = $this->_action;
        $this->view->lang = $this->_lang;
    }

    public function preDispatch() {
        // set template
        $this->_helper->layout->setLayout('brain-auth');
    }

}
