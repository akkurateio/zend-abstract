<?php

namespace Subvitamine\Controller;

class AbstractApp extends \Zend_Controller_Action {

    protected $_config;
    protected $_routes;
    protected $_module;
    protected $_controller;
    protected $_action;
    protected $_lang;
    protected $_locale;

    public function init() {

        $request = $this->getRequest();
        
        // set configs
        $this->_config = \Zend_Registry::get('Zend_Config');
        $this->_routes = \Zend_Registry::get('Zend_Routes');
        $this->_module = $request->getModuleName();
        $this->_controller = $request->getControllerName();
        $this->_action = $request->getActionName();
        $this->_locale = $this->_config->resources->locale->default;
        $this->_lang = $request->getParam('lang', $this->_config->resources->locale->lang);

        // set views
        $this->view->module = $this->_module;
        $this->view->controller = $this->_controller;
        $this->view->action = $this->_action;
    }

    public function preDispatch() {

        // get user identity
        $identity = \Zend_Auth::getInstance()->hasIdentity();
        if (!$identity) {
            $this->_redirect('auth');
        } else {

            // get storage user
            $user = \Zend_Auth::getInstance()->getIdentity();

            // format user token
            $user->token = (is_array($user->getToken())) ? $user->getToken() : $user->getToken()->toArray();

            // set user
            $this->view->user = $user;

            // set other data
            $this->view->api = new \stdClass();
            $this->view->api->url = '//' . $this->_routes->routes->api->route;
        }

        $this->_helper->layout->setLayout('app');
    }

}
