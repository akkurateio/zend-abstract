<?php

namespace Subvitamine\Controller;

class AbstractSitemap extends \Zend_Controller_Action {

    protected $_xml;
    protected $_routes;

    public function init()
    {
        
        // set contants
        $this->_routes = \Zend_Registry::get('Zend_Routes');
        $this->_xml = new \DOMDocument('1.0', 'utf-8');
        
        // disabled render
        $this->getHelper('layout')->disableLayout();
        $this->getHelper('viewRenderer')->setNoRender();
    }
    
    public function postDispatch() {
        
        // set response
        $this->_response->setHeader('Content-Type', 'text/xml; charset=utf-8')->setBody($this->_xml->saveXML());
    }


}
