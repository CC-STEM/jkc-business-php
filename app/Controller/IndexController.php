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

use App\Service\HomeService;

class IndexController extends AbstractController
{
    /**
     * 控制台
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function index()
    {
        try {
            $homeService = new HomeService();
            $result = $homeService->home();
        } catch (\Throwable $e) {
            return $this->responseError($e,'index');
        }

        return $this->responseSuccess($result['data']);
    }

    /**
     * 上课数据统计
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function classStatistics()
    {
        try {
            $dateTag = $this->request->query('date_tag');
            $dateMin = $this->request->query('date_min');
            $dateMax = $this->request->query('date_max');

            $params = [
                'date_tag'=>$dateTag,
                'date_min'=>$dateMin,
                'date_max'=>$dateMax,
            ];
            $homeService = new HomeService();
            $result = $homeService->classStatistics($params);
        } catch (\Throwable $e) {
            return $this->responseError($e,'classStatistics');
        }

        return $this->responseSuccess($result['data']);
    }

    /**
     * 老师点名
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teacherRollCall()
    {
        try {
            $params = $this->request->post();
            $homeService = new HomeService();
            $result = $homeService->teacherRollCall($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teacherRollCall');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
