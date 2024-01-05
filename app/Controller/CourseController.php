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

use App\Service\CourseService;

class CourseController extends AbstractController
{
    /**
     * 线下课程列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineList()
    {
        try {
            $courseCategoryId = $this->request->query('course_category_id');

            $params = ['course_category_id'=>$courseCategoryId];
            $courseService = new CourseService();
            $result = $courseService->courseOfflineList($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 添加线下课程排课
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addCourseOfflinePlan()
    {
        try {
            $params = $this->request->post();
            $courseService = new CourseService();
            $result = $courseService->addCourseOfflinePlan($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'addCourseOfflinePlan');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 编辑线上课程排课
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function editCourseOfflinePlan()
    {
        try {
            $params = $this->request->post();
            $courseService = new CourseService();
            $result = $courseService->editCourseOfflinePlan($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'editCourseOfflinePlan');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 删除线下课程排课
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function deleteCourseOfflinePlan()
    {
        try {
            $params = $this->request->post();
            $id = $params['id'];
            $courseService = new CourseService();
            $result = $courseService->deleteCourseOfflinePlan((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'deleteCourseOfflinePlan');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程排课列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflinePlanList()
    {
        try {
            $teacherId = $this->request->query('teacher_id');
            $themeType = $this->request->query('theme_type');
            $week = $this->request->query('week');

            $params = [
                'theme_type'=>$themeType,
                'week'=>$week,
                'teacher_id'=>$teacherId,
            ];
            $courseService = new CourseService();
            $result = $courseService->courseOfflinePlanList($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflinePlanList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程排课详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflinePlanDetail()
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOfflinePlanDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflinePlanDetail');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程排课信息
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflinePlanInfo()
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOfflinePlanInfo((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflinePlanInfo');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 排课报名学生
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflinePlanSignUpStudent()
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOfflinePlanSignUpStudent((int)$id);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflinePlanSignUpStudent');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 排课实到学生
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflinePlanArriveStudent()
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOfflinePlanArriveStudent((int)$id);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflinePlanArriveStudent');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 排课课堂情况
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflinePlanClassroomSituation()
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOfflinePlanClassroomSituation((int)$id);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflinePlanClassroomSituation');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

}
