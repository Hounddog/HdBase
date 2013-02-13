<?php
namespace HdBase\Controller;

use Zend\Mvc\Controller\AbstractRestfulController
    as ZendAbstractRestfulController;

abstract class AbstractRestfulController extends ZendAbstractRestfulController
{
    /**
     * Service
     * @var DysBase\Service\AbstractRestService
     */
    protected $service;

    /**
      * {@inheritDoc}
      */
    public function getList()
    {
        $userService = $this->getServiceLocator()->get('ModuleServiceLoader');

        $request = $this->getRequest();

        $start = null;
        $count = null;
        $range = $request->getHeaders()->get('Range');

        if ($range) {
            $range = explode('=', $range);
            $range = end($range);
            $range = explode('-', $range);
            $start = $range[0];
            $end = $range[1] != '' ? $range[1] : null;
            $count = $end ? $end - $start + 1 : null;
        }

        $order = $request->getPost()->get('orderBy');
        $orderBy = array();
        // check if one order statement have to be passed to the query
        if ($order) {
            $orderBy["order"] = (substr($order, 0, 1) == '-') ? 'desc' : 'asc';
            $orderBy["sort"] = substr($order, 1);
        }

        // filter criterias allowed by this->_criteres
        $query = $request->getQuery();
        foreach ($query as $key => $value) {
            if (!in_array($key, $this->criteria) || $key == 'orderBy') {
                unset($query[$key]);
            }
        }
        $service = $this->getService();

        try {
            $models = $service->getAll($start, $count, $query, $orderBy);
        } catch (DysBase\Service\Exception\ExceptionInterface $e) {
            throw new Zend_Controller_Action_Exception(
                $e->getMessage(),
                500,
                $e
            );
        }

        if ($range) {
            try {
                $total = $service->count($query);
            } catch (\DysBase\Service\Exception\ExceptionInterface $e) {
                throw new Zend_Controller_Action_Exception(
                    $e->getMessage(),
                    500,
                    $e
                );
            }
            $end = $total < $end ? $total : $end;
            $this->getResponse()->setHeader(
                'Content-Range', 'items ' . $start . '-' . $end . '/' . $total
            );
        }

        $data = array();
        if (count($models) != 0) {
            foreach ($models as $model) {
                // convert model to dto
                try {
                    $dto = $service->toDto($model);
                } catch (\DysBase\Service\ExceptionInterface $e) {
                    throw new \Zend\Mvc\Exception\ExceptionInterface(
                        $e->getMessage(),
                        500,
                        $e
                    );
                }
                $data[] = $dto;
            }
        }
        $result = new JsonModel($data);

        return $result;
    }

    /**
      * {@inheritDoc}
      */
    public function get($id)
    {
        $service = $this->getService();
        try {
            $model = $service->getById($id);
        } catch (\DysBase\Service\Exception\NotFound $e) {
            throw new \Zend\Mvc\Exception\ExceptionInterface(
                $e->getMessage(),
                404,
                $e
            );
        } catch (\DysBase\Service\Exception\ExceptionInterface $e) {
            throw new \Zend\Mvc\Exception\ExceptionInterface(
                $e->getMessage(),
                500,
                $e
            );
        }

        // convert model to dto
        try {
            $dto = $service->toDto($model);
        } catch (\DysBase\Service\Exception\ExceptionInterface $e) {
            throw new  \Zend\Mvc\Exception\ExceptionInterface(
                $e->getMessage(),
                500,
                $e
            );
        }

        $result = new JsonModel($dto);

        return $result;
    }

    /**
      * {@inheritDoc}
      */
    public function create($data)
    {
        $data = $this->request->getContent();

        $dto = $this->save($data);
        $result = new JsonModel($dto->toArray());

        return $result;
    }

    /**
      * {@inheritDoc}
      */
    public function update($id, $data)
    {
        return array("site-updated" => "yes");
    }

    /**
      * {@inheritDoc}
      */
    public function delete($id)
    {
        return array("site-deleted" => $id);
    }



    /**
     * Method for postAction and putAction
     * @param array $data
     * @return \DysBase\Dto\AbstractDto
     * @throws Zend_Controller_Action_Exception 500 for invalid json syntax
     * @throws Zend_Controller_Action_Exception 500 for dto error
     * @throws Zend_Controller_Action_Exception 404 for entity not found
     * @throws Zend_Controller_Action_Exception 500 for a service error
     */
    protected function save($data)
    {
        $service = $this->getService();

        // convert json to dto
        $dto = $this->getDto();

        try {
            $dto->fromJson($data);
        } catch (Pwb_Model_Dto_Exception $e) {
            throw new Zend_Controller_Action_Exception(
                $e->getMessage(),
                500,
                $e
            );
        }
        // convert dto to model
        try {
            $model = $service->fromDto($dto);
        } catch (Pwb_Service_Exception_NotFound $e) {
            throw new Zend_Controller_Action_Exception(
                $e->getMessage(),
                404,
                $e
            );
        } catch (Pwb_Service_Exception $e) {
            throw new Zend_Controller_Action_Exception(
                $e->getMessage(),
                500,
                $e
            );
        }

        // save the model
        try {
            $model = $service->save($model);
        } catch (Pwb_Service_Exception $e) {
            throw new Zend_Controller_Action_Exception(
                $e->getMessage(),
                500,
                $e
            );
        }

        // convertit le model en dto
        try {
            $dto = $service->toDto($model);
        } catch (Pwb_Service_Exception $e) {
            throw new Zend_Controller_Action_Exception(
                $e->getMessage(),
                500,
                $e
            );
        }

        /*try {
            $json = $dto->toJson();
        } catch (Pwb_Model_Dto_Exception $e) {
            throw new Zend_Controller_Action_Exception(
                $e->getMessage(),
                500,
                $e
            );
        }*/

        //$this->getResponse()->appendBody($json);
        return $dto;
    }

    /**
     * Get DTO
     * @return \DysBase\Model\AbstractDto
     */
    protected function getDto()
    {
        return $this->getService()->getDto();
    }

    /**
     * Set Service
     * @param \DysBase\Service\AbstractRestService $service
     */
    public function setService(\DysBase\Service\AbstractRestService $service)
    {
        $this->service = $service;
    }

    /**
     * Get Service
     * @return DysBase\Service\AbstractRestService
     */
    public function getService()
    {
        return $this->service;
    }
}