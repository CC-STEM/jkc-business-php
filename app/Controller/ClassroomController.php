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

use App\Service\ClassroomService;

class ClassroomController extends AbstractController
{
    /**
     * 添加教室
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addClassroom()
    {
        try {
            $params = $this->request->post();
            $classroomService = new ClassroomService();
            $result = $classroomService->addClassroom($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'addClassroom');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 编辑教室
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function editClassroom()
    {
        try {
            $params = $this->request->post();
            $classroomService = new ClassroomService();
            $result = $classroomService->editClassroom($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'editClassroom');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 教室列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function classroomList()
    {
        try {
            $classroomService = new ClassroomService();
            $result = $classroomService->classroomList();
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'classroomList');
        }
        return $this->responseSuccess($data);
    }

    /**
     * 教室详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function classroomDetail()
    {
        try {
            $id = $this->request->query('id',0);
            $classroomService = new ClassroomService();
            $result = $classroomService->classroomDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'classroomDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 删除教室
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function deleteClassroom()
    {
        try {
            $params = $this->request->post();
            $id = $params['id'];
            $classroomService = new ClassroomService();
            $result = $classroomService->deleteClassroom((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'deleteClassroom');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
