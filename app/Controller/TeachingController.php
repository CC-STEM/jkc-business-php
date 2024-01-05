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

use App\Service\TeachingService;

class TeachingController extends AbstractController
{
    /**
     * 线下课程排课列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingPlanList()
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $classStatus = $this->request->query('class_status');

            $params = ['class_status'=>$classStatus];
            $teachingService = new TeachingService();
            $teachingService->offset = $offset;
            $teachingService->limit = $pageSize;
            $result = $teachingService->teachingPlanList($params);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count'=>$result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teachingPlanList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

}
