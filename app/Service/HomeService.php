<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\AdminsCache;
use App\Constants\ErrorCode;
use App\Model\AsyncTask;
use App\Model\CourseOffline;
use App\Model\CourseOfflineOrder;
use App\Model\CourseOfflinePlan;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;

class HomeService extends BaseService
{
    /**
     * HomeService constructor.
     * @throws \RedisException
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 控制台
     * @return array
     */
    public function home(): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];
        //今天时间
        $key1 = date('Y-m-d');
        $todayStartDate = date('Y-m-d 00:00:00');
        $todayEndDate = date('Y-m-d 23:59:59');
        //明天时间
        $key2 = date("Y-m-d",strtotime("+1 day"));
        $after1DayStartDate = date("Y-m-d 00:00:00",strtotime("+1 day"));
        $after1DayEndDate = date('Y-m-d 23:59:59',strtotime("+1 day"));
        //后天时间
        $key3 = date("Y-m-d",strtotime("+2 day"));
        $after2DayStartDate = date("Y-m-d 00:00:00",strtotime("+2 day"));
        $after2DayEndDate = date('Y-m-d 23:59:59',strtotime("+2 day"));

        $courseOfflineOrderList1 = CourseOfflineOrder::query()
            ->leftJoin('member', 'course_offline_order.member_id', '=', 'member.id')
            ->select(['course_offline_order.id','course_offline_order.course_offline_plan_id','course_offline_order.classroom_name','course_offline_order.teacher_name','course_offline_order.course_name','member.name as member_name','member.mobile','course_offline_order.start_at','course_offline_order.end_at','course_offline_order.class_status'])
            ->where(['physical_store_id'=>$physicalStoreId,'order_status'=>0,'pay_status'=>1])->whereBetween('start_at',[$todayStartDate,$todayEndDate])
            ->get();
        $courseOfflineOrderList1 = $courseOfflineOrderList1->toArray();
        $courseOfflineOrderList1 = $this->functions->arrayGroupBy($courseOfflineOrderList1,'course_offline_plan_id');

        $todayCourse = [];
        foreach($courseOfflineOrderList1 as $courseArray){
            $course = [];
            $course['classroom_name'] = $courseArray[0]['classroom_name'];
            $course['teacher_name'] = $courseArray[0]['teacher_name'];
            $course['course_name'] = $courseArray[0]['course_name'];
            $course['start_at'] = $courseArray[0]['start_at'];
            $course['end_at'] = $courseArray[0]['end_at'];
            $studentArray = [];
            foreach($courseArray as $item){
                $student = [];
                $student['id'] = $item['id'];
                $student['member_name'] = $item['member_name'];
                $student['mobile'] = $item['mobile'];
                $student['class_status'] = $item['class_status'];
                $studentArray[] = $student;
            }
            $course['student'] = $studentArray;
            $todayCourse[] = $course;
        }
        $returnTodayCourse['date_text'] = $key1;
        $returnTodayCourse['course'] = $todayCourse;

        //明天课程
        $courseOfflineOrderList2 = CourseOfflineOrder::query()
            ->leftJoin('member', 'course_offline_order.member_id', '=', 'member.id')
            ->select(['course_offline_order.id','course_offline_order.course_offline_plan_id','course_offline_order.classroom_name','course_offline_order.teacher_name','course_offline_order.course_name','member.name as member_name','member.mobile','course_offline_order.start_at','course_offline_order.end_at','course_offline_order.class_status'])
            ->where(['physical_store_id'=>$physicalStoreId,'order_status'=>0,'pay_status'=>1])->whereBetween('start_at',[$after1DayStartDate,$after1DayEndDate])
            ->get();
        $courseOfflineOrderList2 = $courseOfflineOrderList2->toArray();
        $courseOfflineOrderList2 = $this->functions->arrayGroupBy($courseOfflineOrderList2,'course_offline_plan_id');

        $tomorrowCourse = [];
        foreach($courseOfflineOrderList2 as $courseArray){
            $course = [];
            $studentArray = [];
            $course['classroom_name'] = $courseArray[0]['classroom_name'];
            $course['teacher_name'] = $courseArray[0]['teacher_name'];
            $course['course_name'] = $courseArray[0]['course_name'];
            $course['start_at'] = $courseArray[0]['start_at'];
            $course['end_at'] = $courseArray[0]['end_at'];
            foreach($courseArray as $item){
                $student = [];
                $student['id'] = $item['id'];
                $student['member_name'] = $item['member_name'];
                $student['mobile'] = $item['mobile'];
                $student['class_status'] = $item['class_status'];
                $studentArray[] = $student;
            }
            $course['student'] = $studentArray;
            $tomorrowCourse[] = $course;
        }
        $returnTomorrowCourse['date_text'] = $key2;
        $returnTomorrowCourse['course'] = $tomorrowCourse;

        //后天课程
        $courseOfflineOrderList3 = CourseOfflineOrder::query()
            ->leftJoin('member', 'course_offline_order.member_id', '=', 'member.id')
            ->select(['course_offline_order.id','course_offline_order.course_offline_plan_id','course_offline_order.classroom_name','course_offline_order.teacher_name','course_offline_order.course_name','member.name as member_name','member.mobile','course_offline_order.start_at','course_offline_order.end_at','course_offline_order.class_status'])
            ->where(['physical_store_id'=>$physicalStoreId,'order_status'=>0,'pay_status'=>1])->whereBetween('start_at',[$after2DayStartDate,$after2DayEndDate])
            ->get();
        $courseOfflineOrderList3 = $courseOfflineOrderList3->toArray();
        $courseOfflineOrderList3 = $this->functions->arrayGroupBy($courseOfflineOrderList3,'course_offline_plan_id');

        $afterTomorrowCourse = [];
        foreach($courseOfflineOrderList3 as $courseArray){
            $course = [];
            $studentArray = [];
            $course['classroom_name'] = $courseArray[0]['classroom_name'];
            $course['teacher_name'] = $courseArray[0]['teacher_name'];
            $course['course_name'] = $courseArray[0]['course_name'];
            $course['start_at'] = $courseArray[0]['start_at'];
            $course['end_at'] = $courseArray[0]['end_at'];
            foreach($courseArray as $item){
                $student = [];
                $student['id'] = $item['id'];
                $student['member_name'] = $item['member_name'];
                $student['mobile'] = $item['mobile'];
                $student['class_status'] = $item['class_status'];
                $studentArray[] = $student;
            }
            $course['student'] = $studentArray;
            $afterTomorrowCourse[] = $course;
        }
        $returnAfterTomorrowCourse['date_text'] = $key3;
        $returnAfterTomorrowCourse['course'] = $afterTomorrowCourse;

        $returnData = [$returnTodayCourse,$returnTomorrowCourse,$returnAfterTomorrowCourse];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 上课数据统计
     * @param array $params
     * @return array
     */
    public function classStatistics(array $params): array
    {
        $searchDateTag = $params['date_tag'];
        $searchDateMin = $params['date_min'];
        $searchDateMax = $params['date_max'];
        $physicalStoreId = $this->adminsInfo['store_id'];
        $nowTime = time();

        $startTime = null;
        $endTime = null;
        if($searchDateTag !== null){
            switch ($searchDateTag){
                case 1:
                    $startTime = strtotime(date("Y-m-d 00:00:00",strtotime("-1 day")));
                    $endTime = strtotime(date("Y-m-d 23:59:59",strtotime("-1 day")));
                    break;
                case 2:
                    $startTime = strtotime(date("Y-m-d 00:00:00", strtotime("-7 day")));
                    $endTime = strtotime(date("Y-m-d 23:59:59",strtotime("-1 day")));
                    break;
                case 3:
                    $startTime = strtotime(date("Y-m-d 00:00:00",strtotime("-30 day")));
                    $endTime = strtotime(date("Y-m-d 23:59:59",strtotime("-1 day")));
                    break;
            }
        }
        if($searchDateMin !== null && $searchDateMax !== null){
            $startTime = strtotime(date("Y-m-d 00:00:00",strtotime($searchDateMin)));
            $endTime = strtotime(date("Y-m-d 23:59:59",strtotime($searchDateMax)));
        }
        if($startTime !== null && $endTime !== null){
            $startTime1 = $startTime;
            $endTime1 = $endTime;
            $startTime2 = $startTime;
            $endTime2 = $endTime;

            //已上课数据
            if($startTime1>$nowTime){
                $course1Count = 0;
                $course2Count = 0;
                $course3Count = 0;
            }else{
                if($endTime1>=$nowTime){
                    $endTime1 = $nowTime;
                }
                $course1Count = CourseOfflinePlan::query()
                    ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                    ->where(['course_offline_plan.physical_store_id'=>$physicalStoreId,'course_offline_plan.is_deleted'=>0,'course_offline.type'=>1])
                    ->whereBetween('course_offline_plan.class_start_time',[$startTime1,$endTime1])
                    ->count();
                $course2Count = CourseOfflinePlan::query()
                    ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                    ->where(['course_offline_plan.physical_store_id'=>$physicalStoreId,'course_offline_plan.is_deleted'=>0,'course_offline.type'=>2])
                    ->whereBetween('course_offline_plan.class_start_time',[$startTime1,$endTime1])
                    ->count();
                $course3Count = CourseOfflinePlan::query()
                    ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                    ->where(['course_offline_plan.physical_store_id'=>$physicalStoreId,'course_offline_plan.is_deleted'=>0,'course_offline.type'=>3])
                    ->whereBetween('course_offline_plan.class_start_time',[$startTime1,$endTime1])
                    ->count();
            }

            //待上课数据
            if($endTime2<=$nowTime){
                $notStartedCourse1Count = 0;
                $notStartedCourse2Count = 0;
                $notStartedCourse3Count = 0;
            }else{
                if($startTime2<=$nowTime){
                    $startTime2 = $nowTime+1;
                }
                $notStartedCourse1Count = CourseOfflinePlan::query()
                    ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                    ->where(['course_offline_plan.physical_store_id'=>$physicalStoreId,'course_offline_plan.is_deleted'=>0,'course_offline.type'=>1])
                    ->whereBetween('course_offline_plan.class_start_time',[$startTime2,$endTime2])
                    ->count();
                $notStartedCourse2Count = CourseOfflinePlan::query()
                    ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                    ->where(['course_offline_plan.physical_store_id'=>$physicalStoreId,'course_offline_plan.is_deleted'=>0,'course_offline.type'=>2])
                    ->whereBetween('course_offline_plan.class_start_time',[$startTime2,$endTime2])
                    ->count();
                $notStartedCourse3Count = CourseOfflinePlan::query()
                    ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                    ->where(['course_offline_plan.physical_store_id'=>$physicalStoreId,'course_offline_plan.is_deleted'=>0,'course_offline.type'=>3])
                    ->whereBetween('course_offline_plan.class_start_time',[$startTime2,$endTime2])
                    ->count();
            }
        }else{
            //已上课数据
            $course1Count = CourseOfflinePlan::query()
                ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                ->where([['course_offline_plan.physical_store_id','=',$physicalStoreId],['course_offline_plan.is_deleted','=',0],['course_offline.type','=',1],['course_offline_plan.class_start_time','<=',$nowTime]])
                ->count();
            $course2Count = CourseOfflinePlan::query()
                ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                ->where([['course_offline_plan.physical_store_id','=',$physicalStoreId],['course_offline_plan.is_deleted','=',0],['course_offline.type','=',2],['course_offline_plan.class_start_time','<=',$nowTime]])
                ->count();
            $course3Count = CourseOfflinePlan::query()
                ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                ->where([['course_offline_plan.physical_store_id','=',$physicalStoreId],['course_offline_plan.is_deleted','=',0],['course_offline.type','=',3],['course_offline_plan.class_start_time','<=',$nowTime]])
                ->count();

            //待上课数据
            $notStartedCourse1Count = CourseOfflinePlan::query()
                ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                ->where([['course_offline_plan.physical_store_id','=',$physicalStoreId],['course_offline_plan.is_deleted','=',0],['course_offline.type','=',1],['course_offline_plan.class_start_time','>',$nowTime]])
                ->count();
            $notStartedCourse2Count = CourseOfflinePlan::query()
                ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                ->where([['course_offline_plan.physical_store_id','=',$physicalStoreId],['course_offline_plan.is_deleted','=',0],['course_offline.type','=',2],['course_offline_plan.class_start_time','>',$nowTime]])
                ->count();
            $notStartedCourse3Count = CourseOfflinePlan::query()
                ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                ->where([['course_offline_plan.physical_store_id','=',$physicalStoreId],['course_offline_plan.is_deleted','=',0],['course_offline.type','=',3],['course_offline_plan.class_start_time','>',$nowTime]])
                ->count();
        }

        $returnData = [
            'course1'=>$course1Count,
            'course2'=>$course2Count,
            'course3'=>$course3Count,
            'not_started_course1'=>$notStartedCourse1Count,
            'not_started_course2'=>$notStartedCourse2Count,
            'not_started_course3'=>$notStartedCourse3Count,
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 老师点名
     * @param array $params
     * @return array
     * @throws \Throwable
     */
    public function teacherRollCall(array $params): array
    {
        $id = $params['id'];
        $classStatus = $params['class_status'];
        $nowDate = date('Y-m-d H:i:s');

        $courseOfflineOrderInfo = CourseOfflineOrder::query()
            ->select(['member_id','class_status','end_at'])
            ->where(['id'=>$id])
            ->first();
        $courseOfflineOrderInfo = $courseOfflineOrderInfo->toArray();
        $memberId = $courseOfflineOrderInfo['member_id'];
        if($courseOfflineOrderInfo['end_at']<$nowDate && $classStatus == 0 && $courseOfflineOrderInfo['class_status'] == 1){
            return ['code'=>ErrorCode::WARNING,'msg'=>"课程已结束，暂无法调整",'data'=>null];
        }
        $scanAt = $courseOfflineOrderInfo['end_at'];

        if($classStatus == 1){
            Db::connection('jkc_edu')->transaction(function () use($id,$memberId,$scanAt){
                $data = ['member_id'=>$memberId,'course_offline_order_id'=>$id];
                AsyncTask::query()->insert(['data'=>json_encode($data),'type'=>1,'scan_at'=>$scanAt]);
                CourseOfflineOrder::query()->where(['id'=>$id])->update(['class_status'=>1]);
            });
        }else if($classStatus == 0){
            CourseOfflineOrder::query()->where(['id'=>$id])->update(['class_status'=>0]);
        }
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>null];
    }
}