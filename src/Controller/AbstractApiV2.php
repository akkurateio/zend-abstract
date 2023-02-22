<?php

namespace Subvitamine\Controller;

class AbstractApiV2 extends \Zend_Controller_Action
{

    protected $_config;
    protected $_routes;
    protected $_access_token;
    protected $_api_response;
    protected $_params;
    protected $_origin;
    protected $_module;
    protected $_controller;
    protected $_defaultSchema = 'application';
    protected $_customSchemas = array('service');
    protected $_schema;
    protected $_action;
    protected $_model;
    protected $_size;
    protected $_file;
    protected $_lang;
    protected $_authSchema = 'application';
    protected $_environment = 'web';

    public function init()
    {

        // get configs
        $this->_translate = \Zend_Registry::get('Zend_Translate');
        $this->_config = \Zend_Registry::get('Zend_Config');
        $this->_routes = \Zend_Registry::get('Zend_Routes');

        // get request
        $request = $this->getRequest();

        // récupération des paramètres de base
        $this->_origin = $request->getHeader('origin');
        $this->_module = $request->getModuleName();
        $this->_controller = $request->getControllerName();
        $this->_schema = $this->getParam('schema', 'application');
        $this->_action = $request->getActionName();
        $this->_model = $request->getParam('model');
        $this->_id = (int)$request->getParam('id');
        $this->_size = $request->getParam('size', null);
        $this->_file = $request->getParam('file', null);
        $this->_lang = $request->getParam('lang', $this->_config->resources->locale->lang);

        // get url params
        $this->_params = new \stdClass();
        $this->_params->order = (!empty($request->getParam('order'))) ? $request->getParam('order') : 'id';
        $this->_params->sort = (!empty($request->getParam('sort'))) ? $request->getParam('sort') : 'asc';
        $this->_params->page = (int)(!empty($request->getParam('page'))) ? (int)$request->getParam('page') : 1;
        $this->_params->limit = (!empty($request->getParam('limit'))) ? (int)$request->getParam('limit') : -1;
        $this->_params->limit = (empty($request->getParam('limit')) && !empty($request->getParam('page'))) ? (int)$this->_config->pagination->limit : $this->_params->limit;
        $this->_params->filters = (!empty($request->getParam('filter'))) ? $request->getParam('filter') : array();

        if ((int)$request->getParam('lightmode') === 1 || $request->getParam('lightmode') === true) {
            $this->_params->lightmode = true;
        } else {
            $this->_params->lightmode = false;
        }

        // on configure le schema
        $this->setSchema();

        // on construit le header
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json', true)
            ->setHeader('Access-Control-Allow-Origin', '*', true)
            ->setHeader('Access-Control-Allow-Methods', 'PUT, GET, POST, DELETE, OPTIONS', true)
            ->setHeader(
                'Access-Control-Allow-Headers',
                'Accept, Content-Type, Authorization, Auth, Environment, Origin, X-requested-with, Content-Range, Content-Disposition, Content-Description, From, X-Country-code, Accept-language'
                , true)
            ->setHeader('Pragma', 'no-cache', true)
            ->setHeader('Expires', '0', true)
            ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT')
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0', true);
        $this->getHelper('Layout')->disableLayout();
        $this->getHelper('ViewRenderer')->setNoRender();
    }

    public function preDispatch()
    {

        // get token
        $this->_authSchema = ($this->getRequest()->getHeader('Auth') ? $this->getRequest()->getHeader('Auth') : 'application');
        $this->_access_token = ($this->getRequest()->getHeader('Authorization') ? $this->getRequest()->getHeader('Authorization') : null);
        $this->_environment = ($this->getRequest()->getHeader('Environment') ? $this->getRequest()->getHeader('Environment') : null);

        // on initialise l'objet de réponse
        $this->_helper->contextSwitch()->addActionContext('foo', 'json')->initContext('json');
        $this->_api_response = new \stdClass();
    }

    public function postDispatch()
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(\Zend_Json::encode($this->_api_response));
    }

    public function getJsonResponseAction()
    {

        return;
    }

    public function validToken($bypassExpire = false)
    {

        $tokenMapperName = '\Application_Model_Mapper_' . \Subvitamine\Libs\LibString::camelize($this->_authSchema, true) . '_Token';
        $tokenMapper = new $tokenMapperName();
        $userMapperName = '\Application_Model_Mapper_' . \Subvitamine\Libs\LibString::camelize($this->_authSchema, true) . '_User';
        $userMapper = new $userMapperName();

        // token not found
        if (empty($this->_access_token)) {
            $apiResponse = new \General_Response_ApiResponse(403);
            $this->_api_response = $apiResponse->getResponse();
            return;
        }

        // get token
        $token = $tokenMapper->getByAccessToken($this->_access_token, true);

        // no token founded in db
        if (!$token) {
            $apiResponse = new \General_Response_ApiResponse(403);
            $this->_api_response = $apiResponse->getResponse();
            return;
        }

        // token is expired
        if (!$bypassExpire && strtotime(date('Y-m-d H:i:s')) > strtotime($token->date_expire)) {
            $apiResponse = new \General_Response_ApiResponse(405);
            $this->_api_response = $apiResponse->getResponse();
            return;
        }

        // get uset
        $this->_user = $userMapper->getById($token->id_user, true);

        // user not found
        if (!$this->_user) {
            $apiResponse = new \General_Response_ApiResponse(500);
            $this->_api_response = $apiResponse->getResponse();
            return;
        }

        return true;
    }

    public function setSchema()
    {
        if ($this->_schema == 'base') {
            $this->_schema = $this->_defaultSchema;
            return;
        }
        $valid = false;
        foreach ($this->_config->resources->multidb as $db) {
            if ($this->_schema == $db->get('adapter_name') || in_array($this->_schema, $this->_customSchemas)) {
                $valid = true;
            }
        }
        if (!$valid) {
            $this->_schema = $this->_defaultSchema;
        }
    }

    /**
     * On check si le user courant est un superadmin
     */
    public function isSuperAdmin()
    {
        $roleMapper = new \Application_Model_Mapper_Application_Role();
        $userRole = $roleMapper->getById($this->_user->getIdRole(), true);
        if ($userRole->getKey() != ROLE_SUPER_ADMIN) {
            $apiResponse = new \General_Response_ApiResponse(409, $this->_lang);
            $this->_api_response = $apiResponse->getResponse();
            return;
        }
        return true;
    }

    /**
     * On check si le user courant est un admin
     */
    public function isAdmin()
    {
        $roleMapper = new \Application_Model_Mapper_Application_Role();
        $userRole = $roleMapper->getById($this->_user->getIdRole(), true);
        if ($userRole->getKey() != ROLE_ADMIN) {
            $apiResponse = new \General_Response_ApiResponse(409, $this->_lang);
            $this->_api_response = $apiResponse->getResponse();
            return;
        }
        return true;
    }

}
