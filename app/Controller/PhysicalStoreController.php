<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Controller;

use App\Service\PhysicalStoreService;

class PhysicalStoreController extends AbstractController
{
    /**
     * 编辑门店
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function editPhysicalStore()
    {
        try {
            $params = $this->request->post();
            $physicalStoreService = new PhysicalStoreService();
            $result = $physicalStoreService->editPhysicalStore($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'editPhysicalStore');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function physicalStoreDetail()
    {
        try {
            $physicalStoreService = new PhysicalStoreService();
            $result = $physicalStoreService->physicalStoreDetail();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'physicalStoreDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 管理门店列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function adminsPhysicalStoreList()
    {
        try {
            $physicalStoreService = new PhysicalStoreService();
            $result = $physicalStoreService->adminsPhysicalStoreList();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'adminsPhysicalStoreList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 指定门店
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function selectedPhysicalStore()
    {
        try {
            $params = $this->request->post();
            $token = $this->session->get('token');

            $params['token'] = $token;
            $physicalStoreService = new PhysicalStoreService();
            $result = $physicalStoreService->selectedPhysicalStore($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'selectedPhysicalStore');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
