<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\CourseOfflinePlan;
use App\Model\PhysicalStoreAdmins;
use App\Model\Teacher;
use App\Constants\ErrorCode;
use Hyperf\Utils\Context;

class TeachingService extends BaseService
{

    /**
     * TeachingService constructor.
     * @throws \RedisException
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 线下课程排课列表
     * @param array $params
     * @return array
     */
    public function teachingPlanList(array $params): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];
        $adminsId = $this->adminsInfo['admins_id'];

        $classStatus = $params['class_status'];
        $nowTime = time();
        $offset = $this->offset;
        $limit = $this->limit;

        $physicalStoreAdminsInfo = PhysicalStoreAdmins::query()
            ->select(['mobile'])
            ->where(['id'=>$adminsId])
            ->first();
        $physicalStoreAdminsInfo = $physicalStoreAdminsInfo->toArray();

        $teacherInfo = Teacher::query()
            ->select(['id'])
            ->where(['physical_store_id'=>$physicalStoreId,'mobile'=>$physicalStoreAdminsInfo['mobile']])
            ->first();
        if(empty($teacherInfo)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>[],'count'=>0]];
        }
        $teacherInfo = $teacherInfo->toArray();
        $teacherId = $teacherInfo['id'];

        $model = CourseOfflinePlan::query()
            ->leftJoin('course_offline', 'course_offline_plan.course_offline_id', '=', 'course_offline.id')
            ->select(['course_offline.name','course_offline.video_url','course_offline.type','course_offline.suit_age_min','course_offline.suit_age_max','course_offline_plan.classroom_name','course_offline_plan.teacher_name','course_offline_plan.class_start_time','course_offline_plan.class_end_time']);

        $where = [['course_offline_plan.is_deleted','=',0],['course_offline_plan.physical_store_id','=',$physicalStoreId]];
        if($classStatus == 0){
            $where[] = ['course_offline_plan.class_end_time','>',$nowTime];
        }else{
            $where[] = ['course_offline_plan.class_end_time','<=',$nowTime];
        }
        if($teacherId !== null){
            $where[] = ['course_offline_plan.teacher_id','=',$teacherId];
        }
        $count = $model->where($where)->count();
        $courseOfflinePlanList = $model
            ->where($where)
            ->orderBy('course_offline_plan.id','desc')
            ->offset($offset)->limit($limit)
            ->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();

        foreach($courseOfflinePlanList as $key=>$value){
            $courseOfflinePlanList[$key]['class_start_time'] = date('Y-m-d H:i',$value['class_start_time']);
            $courseOfflinePlanList[$key]['class_end_time'] = date('Y-m-d H:i',$value['class_end_time']);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$courseOfflinePlanList,'count'=>$count]];
    }

}