<?php

namespace Subvitamine\Controller;

class AbstractRest extends \Subvitamine\Controller\AbstractApiV2 {

    protected $_mapperName;
    protected $_mapper;
    protected $_data = array();
    protected $_id_parent;
    protected $_id_child;
    protected $_ignore_fk = array();
    protected $_sensible_fields = array();
    public static $_associations = array(
        'GET' => 'read',
        'POST' => 'create',
        'PUT' => 'update',
        'DELETE' => 'delete'
    );
    protected $_nbLike = 0;

    public function indexAction() {

        if ($this->validToken()) {

            $mapperFile = MODELS_FOLDER . '/mappers/' . \Subvitamine\Libs\LibString::camelize($this->_schema, true) . '/' . \Subvitamine\Libs\LibString::camelize($this->_model, true) . '.php';

            if (empty($this->_model) || !file_exists($mapperFile)) {

                // MODEL NOT FOUND
                $apiResponse = new \General_Response_ApiResponse(407, $this->_lang);
                $this->_api_response = $apiResponse->getResponse();
            } else {
                // set current mapper name
                $this->_mapperName = '\Application_Model_Mapper_' . \Subvitamine\Libs\LibString::camelize($this->_schema, true) . '_' . \Subvitamine\Libs\LibString::camelize($this->_model, true);

                // get current mapper
                $this->_mapper = new $this->_mapperName();

                // get request
                $request = $this->getRequest();

                if ($request->isPost()) {
                    // CREATE
                    $this->_post();
                } elseif ($request->isGet()) {
                    // READ
                    $this->_get();
                } elseif ($request->isPut()) {
                    // UPDATE
                    $this->_put();
                } elseif ($request->isDelete()) {
                    // DELETE
                    $this->_delete();
                } else {
                    // UNAUTHORIZED METHOD
                    $apiResponse = new \General_Response_ApiResponse(402, $this->_lang);
                    $this->_api_response = $apiResponse->getResponse();
                }
            }
        }
    }

    /**
     * CREATE
     */
    private function _post() {

        // get post params
        $params = \Zend_Json::decode($this->getRequest()->getRawBody(), \Zend_Json::TYPE_OBJECT);

        // update status
        try {

            /**
             * Insert data
             */
            try {
                // unset item data for update
                unset($params->schema);
                $itemID = (int) $this->_mapper->insert((array) $params);
                $this->_mapper->activity('post', $itemID, $this->_user->getId());
            } catch (Exception $e) {
                $apiResponse = new \General_Response_ApiResponse(404, $this->_lang);
                $this->_api_response = $apiResponse->getResponse();
                $this->_api_response->error->message = $e->getMessage();
                return;
            }
        } catch (\Zend_Db_Statement_Exception $e) {
            // get exception
            $exception = $e->getChainedException();
            // return error
            $apiResponse = new \General_Response_ApiResponse($exception->errorInfo[1], $this->_lang);
            $this->_api_response = $apiResponse->getResponse();
            $this->_api_response->error->message = $exception->errorInfo[2];
            return;
        }

        // get item
        $item = $this->_mapper->getById($itemID, true);
        if (!$this->_params->lightmode) {
            $item->getAllRelations();
        }

        // return response
        $apiResponse = new \General_Response_ApiResponse(200, $this->_lang);
        $response = $apiResponse->getResponse();
        $response->data = $item->toArray();
        $this->_api_response = $response;
    }

    /**
     * READ
     */
    private function _get() {
        $apiResponse = new \General_Response_ApiResponse(200, $this->_lang);
        $response = $apiResponse->getResponse();

        if ($this->_id) {

            // get item
            $item = $this->_mapper->getById($this->_id, true);
            if (empty($item)) {
                $apiResponse = new \General_Response_ApiResponse(404, $this->_lang);
                $this->_api_response = $apiResponse->getResponse();
                return;
            }

            if (!$this->_params->lightmode) {
                $item->getAllRelations();
            }

            // format item
            $response->data = $item->toArray();
        } else {

            try {
                // construc sql request
                $query = $this->_getQuery();
                $sql = $this->_mapper->select();

                if (!empty($query->or)) {

                    $i = 1;
                    $count = count($query->or);

                    foreach ($query->or as $key => $value) {
                        $keyLabel = $key;
                        if ($i == 1) {
                            $keyLabel = '(' . $keyLabel;
                        }
                        if ($i == $count) {
                            $keyLabel .= ')';
                        }
                        $sql->orWhere($keyLabel, $value);
                        $i++;
                    }
                }

                if (!empty($query->and)) {
                    foreach ($query->and as $key => $value) {
                        $sql->where($key, $value);
                    }
                }

                $sql->order($query->order);

                // set paginator
                $select = new \Zend_Paginator_Adapter_DbTableSelect($sql);
                $select->count($select);

                // paginator
                $paginator = new \Zend_Paginator($select);
                $paginator->setCurrentPageNumber($query->page);
                $paginator->setItemCountPerPage($query->limit);

                // get items
                $items = array();
                $result = $this->_mapper->makeObjects($paginator);

                // format items
                if (!empty($result)) {
                    foreach ($result as $item) {
                        if (!$this->_params->lightmode) {
                            $item->getAllRelations();
                        }
                        $items[] = $item->toArray($this->_params->lightmode);
                    }
                }

                // set response
                $response->data = $items;
                $response->pagin = \General_Paginator::getPagin($paginator);
            } catch (Exception $ex) {

                // error
                $apiResponse = new \General_Response_ApiResponse(404, $this->_lang);
                $this->_api_response = $apiResponse->getResponse();
                $this->_api_response->error->message = $ex->getMessage();
                return;
            }
        }

        // return response
        $this->_api_response = $response;
    }

    /**
     * UPDATE
     */
    private function _put() {

        // get post params
        $params = \Zend_Json::decode($this->getRequest()->getRawBody(), \Zend_Json::TYPE_OBJECT);

        // get item id
        $itemID = (int) $params->id;

        //  undefined item id not defined
        if (!$itemID) {
            $apiResponse = new \General_Response_ApiResponse(404, $this->_lang);
            $this->_api_response = $apiResponse->getResponse();
            return;
        }

        // get item
        $item = $this->_mapper->getById($itemID, true);

        // item not found
        if (empty($item)) {
            $apiResponse = new \General_Response_ApiResponse(404, $this->_lang);
            $this->_api_response = $apiResponse->getResponse();
            return;
        }

        // unset item data for update
        unset($params->id);
        unset($params->schema);
        unset($params->date_created);
        unset($params->date_updated);
        // update item
        try {

            /**
             * Update item on database
             */
            $this->_mapper->activity('put', $item->getId(), $this->_user->getId(), (array) $params);
            $this->_mapper->update((array) $params, array('id = ?' => $item->getId()));
        } catch (\Zend_Db_Statement_Exception $e) {
            // get exception
            $exception = $e->getChainedException();
            // return error
            $apiResponse = new \General_Response_ApiResponse($exception->errorInfo[1], $this->_lang);
            $this->_api_response = $apiResponse->getResponse();
            $this->_api_response->error->message = $exception->errorInfo[2];
            return;
        }

        // get updated item
        $updatedItem = $this->_mapper->getById($itemID, true);
        if (!$this->_params->lightmode) {
            $updatedItem->getAllRelations();
        }

        // return response
        $apiResponse = new \General_Response_ApiResponse(200, $this->_lang);
        $response = $apiResponse->getResponse();
        $response->data = $updatedItem->toArray();
        $this->_api_response = $response;
    }

    /**
     * DELETE
     */
    private function _delete() {

        //  undefined item id not defined
        if (!$this->_id) {
            $apiResponse = new \General_Response_ApiResponse(404, $this->_lang);
            $this->_api_response = $apiResponse->getResponse();
            return;
        }

        // get item
        $item = $this->_mapper->getById($this->_id, true);

        // item not found
        if (empty($item)) {
            $apiResponse = new \General_Response_ApiResponse(404, $this->_lang);
            $this->_api_response = $apiResponse->getResponse();
            return;
        }

        // delete item
        try {
            //insert activity before delete
            $this->_mapper->activity('delete', $this->_id, $this->_user->getId());
            $this->_mapper->delete('id = ' . $this->_id);
        } catch (\Zend_Db_Statement_Exception $e) {
            // get exception
            $exception = $e->getChainedException();
            // return error
            $apiResponse = new \General_Response_ApiResponse($exception->errorInfo[1], $this->_lang);
            $this->_api_response = $apiResponse->getResponse();
            $this->_api_response->error->message = $exception->errorInfo[2];
            return;
        }

        // return response
        $apiResponse = new \General_Response_ApiResponse(200, $this->_lang);
        $this->_api_response = $apiResponse->getResponse();
    }

    // Set conditions for fetchAll
    private function _getQuery() {

        $query = new \stdClass();

        // where
        $query->or = array();
        $query->and = array();

        if (!empty($this->_params->filters)) {
            foreach ($this->_params->filters as $filter) {

                // If the field is a foreing key, we save it
                // so we won't retrieve it, when we format
                if (strpos($filter['field'], 'id_') !== false) {
                    $this->_ignore_fk[] = $filter['field'];
                }

                // build query
                if ($filter['eq'] == "like") {
                    $query->or[$filter['field'] . ' ' . $filter['eq'] . ' ?'] = '%' . $filter['value'] . '%';
                } else {
                    $query->and[$filter['field'] . ' ' . $filter['eq'] . ' ?'] = $filter['value'];
                }

                // incrementation of like clause
                if ($filter['eq'] == 'like') {
                    $this->_nbLike++;
                }
            }
        }

        // order
        $query->order = $this->_params->order . ' ' . $this->_params->sort;

        // limit
        $query->limit = $this->_params->limit;

        // page
        $query->page = $this->_params->page;

        return $query;
    }

    // Create pagination data
    private function _getPagin($paginator) {
        $pagin = new \stdClass();
        $pagin->pages = ($paginator->getPages()->pageCount == 0) ? $paginator->getPages()->pageCount + 1 : $paginator->getPages()->pageCount; // nombre total de page
        $pagin->itemPerPage = $paginator->getPages()->itemCountPerPage; // nombre d'items sur la page courante
        $pagin->current = $paginator->getPages()->current; // page courante
        if (!empty($paginator->getPages()->previous)) {
            $pagin->prev = $paginator->getPages()->previous; // page précédente
        }
        if (!empty($paginator->getPages()->next)) {
            $pagin->next = $paginator->getPages()->next; // page suivante
        }
        $pagin->total = $paginator->getPages()->totalItemCount; // nombre total d'items
        // set next and prev urls
        $url = "/" . $this->_lang . "/crud/" . $this->_model;
        $url .= "?sort=" . $this->_params->sort;
        $url .= "&order=" . $this->_params->order;
        $url .= "&limit=" . $this->_params->limit;
        if (!empty($this->_params->filters)) {
            foreach ($this->_params->filters as $nb => $filter) {
                foreach ($filter as $key => $value) {
                    $url .= "&filter[" . $nb . "][" . $key . "]=" . $value;
                }
            }
        }

        $links = new \stdClass();
        if (!empty($pagin->prev)) {
            $links->prev = $url . "&page=" . $pagin->prev;
        }
        if (!empty($pagin->next)) {
            $links->next = $url . "&page=" . $pagin->next;
        }
        $pagin->links = $links;

        return $pagin;
    }

}
