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

use App\Service\CourseCategoryService;

class CourseCategoryController extends AbstractController
{
    /**
     * 线下课程分类列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineCategoryList()
    {
        try {
            $parentId = $this->request->query('parent_id',0);
            $themeType = $this->request->query('theme_type',1);

            $params = ['parent_id'=>$parentId,'theme_type'=>$themeType];
            $courseCategoryService = new CourseCategoryService();
            $result = $courseCategoryService->courseOfflineCategoryList($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineCategoryList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程分类列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineThemeTypeList()
    {
        try {
            $courseCategoryService = new CourseCategoryService();
            $result = $courseCategoryService->courseOfflineThemeTypeList();
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineThemeTypeList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
