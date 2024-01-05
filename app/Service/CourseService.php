<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\AdminsCache;
use App\Model\Classroom;
use App\Model\CourseOfflineClassroomSituation;
use App\Model\CourseOfflineOrder;
use App\Model\CourseOfflinePlan;
use App\Model\PhysicalStore;
use App\Model\Teacher;
use App\Model\CourseOffline;
use App\Constants\ErrorCode;
use App\Model\VipCardOrder;
use App\Model\VipCardOrderDynamicCourse;
use App\Snowflake\IdGenerator;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;

class CourseService extends BaseService
{
    /**
     * CourseService constructor.
     * @throws \RedisException
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 线下课程列表
     * @param array $params
     * @return array
     */
    public function courseOfflineList(array $params): array
    {
        $courseCategoryId = $params['course_category_id'];

        $courseOfflineList = CourseOffline::query()
            ->select(['id','name','img_url','suit_age_min','suit_age_max','type','sign_up_num','duration'])
            ->where(['course_category_id'=>$courseCategoryId,'is_deleted'=>0])
            ->get();
        $courseOfflineList = $courseOfflineList->toArray();
        foreach($courseOfflineList as $key=>$value){
            $courseOfflineList[$key]['study_num'] = 0;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineList];
    }

    /**
     * 添加线下课程排课
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function addCourseOfflinePlan(array $params): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];
        $coursePlan = $params['course_plan'];

        foreach($coursePlan as $key=>$value){
            if(empty($value['course_offline_id']) || empty($value['classroom_id']) || empty($value['teacher_id'])){
                unset($coursePlan[$key]);
            }
        }
        if(empty($coursePlan)){
            return ['code' => ErrorCode::WARNING, 'msg' => '排课数据不能为空', 'data' => null];
        }
        $courseOfflineIdArray = array_column($coursePlan,'course_offline_id');
        $classroomIdArray = array_column($coursePlan,'classroom_id');
        $teacherIdArray = array_column($coursePlan,'teacher_id');

        $courseOfflineList = CourseOffline::query()->select(['id','duration','course_category_id','theme_type'])->whereIn('id',$courseOfflineIdArray)->get();
        $courseOfflineList = $courseOfflineList->toArray();
        $combineCourseOfflineKey = array_column($courseOfflineList,'id');
        $courseOfflineList = array_combine($combineCourseOfflineKey,$courseOfflineList);

        $classroomList = Classroom::query()->select(['id','name','capacity'])->whereIn('id',$classroomIdArray)->get();
        $classroomList = $classroomList->toArray();
        $combineClassroomKey = array_column($classroomList,'id');
        $classroomList = array_combine($combineClassroomKey,$classroomList);

        $teacherList = Teacher::query()->select(['id','name'])->whereIn('id',$teacherIdArray)->get();
        $teacherList = $teacherList->toArray();
        $combineTeacherKey = array_column($teacherList,'id');
        $teacherList = array_combine($combineTeacherKey,$teacherList);

        $batchNo = IdGenerator::generate();
        $insertCourseOfflinePlanData = [];
        foreach($coursePlan as $value){
            $courseOfflinePlanData = [];
            $courseOfflineId = $value['course_offline_id'];
            $classroomId = $value['classroom_id'];
            $teacherId = $value['teacher_id'];
            $classStartTime = strtotime($value['class_start_time']);
            $courseOfflineInfo = $courseOfflineList[$courseOfflineId];
            $classroomInfo = $classroomList[$classroomId];
            $teacherInfo = $teacherList[$teacherId];
            $classEndTime = $classStartTime + ($courseOfflineInfo['duration']*60);
            $classDate = strtotime(date('Y-m-d',$classStartTime));

            $courseOfflinePlanData['id'] = IdGenerator::generate();
            $courseOfflinePlanData['batch_no'] = $batchNo;
            $courseOfflinePlanData['course_category_id'] = $courseOfflineInfo['course_category_id'];
            $courseOfflinePlanData['course_offline_id'] = $courseOfflineId;
            $courseOfflinePlanData['physical_store_id'] = $physicalStoreId;
            $courseOfflinePlanData['classroom_id'] = $classroomId;
            $courseOfflinePlanData['teacher_id'] = $teacherId;
            $courseOfflinePlanData['classroom_name'] = $classroomInfo['name'];
            $courseOfflinePlanData['teacher_name'] = $teacherInfo['name'];
            $courseOfflinePlanData['class_start_time'] = $classStartTime;
            $courseOfflinePlanData['class_end_time'] = $classEndTime;
            $courseOfflinePlanData['class_date'] = $classDate;
            $courseOfflinePlanData['classroom_capacity'] = $classroomInfo['capacity'];
            $courseOfflinePlanData['theme_type'] = $courseOfflineInfo['theme_type'];
            $insertCourseOfflinePlanData[] = $courseOfflinePlanData;
        }
        array_multisort(array_column($insertCourseOfflinePlanData,'class_start_time'), SORT_ASC, $insertCourseOfflinePlanData);

        foreach($insertCourseOfflinePlanData as $key=>$value){
            $insertCourseOfflinePlanData[$key]['section_no'] = $key+1;
        }
        CourseOfflinePlan::query()->insert($insertCourseOfflinePlanData);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 编辑线上课程排课
     * @param array $params
     * @return array
     */
    public function editCourseOfflinePlan(array $params): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];

        $courseOfflinePlanId = $params['id'];
        $courseOfflineId = $params['course_offline_id'];
        $classroomId = $params['classroom_id'];
        $teacherId = $params['teacher_id'];
        $classStartTime = strtotime($params['class_start_time']);

        $courseOfflinePlanInfo = CourseOfflinePlan::query()
            ->select(['batch_no'])
            ->where(['id'=>$courseOfflinePlanId,'physical_store_id'=>$physicalStoreId])
            ->first();
        if(empty($courseOfflinePlanInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '排课信息错误', 'data' => null];
        }
        $courseOfflinePlanInfo = $courseOfflinePlanInfo->toArray();
        $batchNo = $courseOfflinePlanInfo['batch_no'];

        $courseOfflineInfo = CourseOffline::query()->select(['id','duration'])->where(['id'=>$courseOfflineId])->first();
        $courseOfflineInfo = $courseOfflineInfo->toArray();
        $classEndTime = $classStartTime + ($courseOfflineInfo['duration']*60);
        $classDate = strtotime(date('Y-m-d',$classStartTime));

        $classroomInfo = Classroom::query()->select(['id','name','capacity'])->where(['id'=>$classroomId])->first();
        $classroomInfo = $classroomInfo->toArray();

        $teacherInfo = Teacher::query()->select(['id','name'])->where(['id'=>$teacherId])->first();
        $teacherInfo = $teacherInfo->toArray();

        $updateCourseOfflinePlanData['classroom_id'] = $classroomId;
        $updateCourseOfflinePlanData['teacher_id'] = $teacherId;
        $updateCourseOfflinePlanData['classroom_name'] = $classroomInfo['name'];
        $updateCourseOfflinePlanData['teacher_name'] = $teacherInfo['name'];
        $updateCourseOfflinePlanData['class_start_time'] = $classStartTime;
        $updateCourseOfflinePlanData['class_end_time'] = $classEndTime;
        $updateCourseOfflinePlanData['class_date'] = $classDate;
        $updateCourseOfflinePlanData['classroom_capacity'] = $classroomInfo['capacity'];
        CourseOfflinePlan::query()->where(['id'=>$courseOfflinePlanId,'physical_store_id'=>$physicalStoreId])->update($updateCourseOfflinePlanData);

        $courseOfflinePlanList = CourseOfflinePlan::query()
            ->select(['id'])
            ->where(['batch_no'=>$batchNo,'physical_store_id'=>$physicalStoreId])
            ->orderBy('class_start_time')
            ->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();
        foreach($courseOfflinePlanList as $key=>$value){
            CourseOfflinePlan::query()->where(['id'=>$value['id']])->update(['section_no'=>$key+1]);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 删除线下课程排课
     * @param int $id
     * @return array
     */
    public function deleteCourseOfflinePlan(int $id): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];

        CourseOfflinePlan::query()->where(['id'=>$id,'physical_store_id'=>$physicalStoreId])->update(['is_deleted'=>1]);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 线下课程排课列表
     * @param array $params
     * @return array
     */
    public function courseOfflinePlanList(array $params): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];

        $teacherId = $params['teacher_id'];
        $themeType = $params['theme_type'];
        $searchWeek = $params['week'] ?? 0;
        $nowWeek = date('w') == 0 ? 7 : date('w');
        $nowWeekStart = ((int)date("d")-(int)$nowWeek+1)+($searchWeek*7);
        $nowWeekEnd = ((int)date("d")-(int)$nowWeek+7)+($searchWeek*7);
        //周开始/结束时间
        $classStartDateMin =  date("Y-m-d H:i:s", mktime(0,0,0,(int)date("m"),(int)$nowWeekStart,(int)date("Y")));
        $classStartDateMax =  date("Y-m-d H:i:s", mktime(23,59,59,(int)date("m"),(int)$nowWeekEnd,(int)date("Y")));
        $weekArray = ["日","一","二","三","四","五","六"];

        $model = CourseOfflinePlan::query()
            ->leftJoin('course_offline', 'course_offline_plan.course_offline_id', '=', 'course_offline.id')
            ->leftJoin('physical_store', 'course_offline_plan.physical_store_id', '=', 'physical_store.id')
            ->select(['course_offline.name','course_offline.suit_age_min','course_offline.suit_age_max','course_offline_plan.id','course_offline_plan.teacher_name','course_offline_plan.class_start_time','course_offline_plan.class_end_time','course_offline_plan.sign_up_num','course_offline_plan.theme_type']);

        $where = [['course_offline_plan.is_deleted','=',0],['course_offline_plan.physical_store_id','=',$physicalStoreId]];
        if($teacherId !== null){
            $where[] = ['course_offline_plan.teacher_id','=',$teacherId];
        }
        if($themeType !== null){
            $where[] = ['course_offline_plan.theme_type','=',$themeType];
        }
        $courseOfflinePlanList = $model
            ->where($where)
            ->whereBetween('course_offline_plan.class_start_time',[strtotime($classStartDateMin),strtotime($classStartDateMax)])
            ->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();

        foreach($courseOfflinePlanList as $key=>$value){
            $courseOfflineOrderCount2 = CourseOfflineOrder::query()->where(['course_offline_plan_id'=>$value['id'],'class_status'=>1,'order_status'=>0])->count();

            $courseOfflinePlanList[$key]['course_class_attendance_num'] = $courseOfflineOrderCount2;
            $courseOfflinePlanList[$key]['w'] = "周".$weekArray[date("w",$value['class_start_time'])];
            $courseOfflinePlanList[$key]['d'] = date('Y.m.d',$value['class_start_time']);
            $courseOfflinePlanList[$key]['class_time'] = date('H:i',$value['class_start_time']).'-'.date('H:i',$value['class_end_time']);
            unset($courseOfflinePlanList[$key]['class_start_time']);
            unset($courseOfflinePlanList[$key]['class_end_time']);
        }
        $courseOfflinePlanList = $this->functions->arrayGroupBy($courseOfflinePlanList,'w');

        $returnData = [
            '1'=>$courseOfflinePlanList['周一'] ?? [],
            '2'=>$courseOfflinePlanList['周二'] ?? [],
            '3'=>$courseOfflinePlanList['周三'] ?? [],
            '4'=>$courseOfflinePlanList['周四'] ?? [],
            '5'=>$courseOfflinePlanList['周五'] ?? [],
            '6'=>$courseOfflinePlanList['周六'] ?? [],
            '7'=>$courseOfflinePlanList['周日'] ?? []
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 线下课程排课详情
     * @param int $id
     * @return array
     */
    public function courseOfflinePlanDetail(int $id): array
    {
        $courseOfflinePlanInfo = CourseOfflinePlan::query()
            ->select(['id','course_offline_id','classroom_id','teacher_id','class_start_time','physical_store_id'])
            ->where(['id'=>$id])
            ->first();
        if(empty($courseOfflinePlanInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '信息异常', 'data' => null];
        }
        $courseOfflinePlanInfo = $courseOfflinePlanInfo->toArray();
        $courseOfflineId = $courseOfflinePlanInfo['course_offline_id'];
        $physicalStoreId = $courseOfflinePlanInfo['physical_store_id'];

        $courseOfflineInfo = CourseOffline::query()->select(['duration','name','suit_age_min','suit_age_max','video_url'])->where(['id'=>$courseOfflineId])->first();
        $courseOfflineInfo = $courseOfflineInfo->toArray();

        $physicalStoreInfo = PhysicalStore::query()->select(['name'])->where(['id'=>$physicalStoreId])->first();
        $physicalStoreInfo = $physicalStoreInfo->toArray();

        $courseOfflinePlanInfo['class_start_time'] = date('Y-m-d H:i',$courseOfflinePlanInfo['class_start_time']);
        $courseOfflinePlanInfo['name'] = $courseOfflineInfo['name'];
        $courseOfflinePlanInfo['duration'] = $courseOfflineInfo['duration'];
        $courseOfflinePlanInfo['suit_age_min'] = $courseOfflineInfo['suit_age_min'];
        $courseOfflinePlanInfo['suit_age_max'] = $courseOfflineInfo['suit_age_max'];
        $courseOfflinePlanInfo['video_url'] = $courseOfflineInfo['video_url'];
        $courseOfflinePlanInfo['physical_store_name'] = $physicalStoreInfo['name'];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflinePlanInfo];
    }

    /**
     * 线下课程排课信息
     * @param int $id
     * @return array
     */
    public function courseOfflinePlanInfo(int $id): array
    {
        $nowTime = time();

        $courseOfflinePlanInfo = CourseOfflinePlan::query()
            ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
            ->leftJoin('physical_store','course_offline_plan.physical_store_id','=','physical_store.id')
            ->select(['course_offline.suit_age_min','course_offline.img_url','course_offline.name as course_name','course_offline_plan.teacher_name','course_offline_plan.class_start_time','course_offline_plan.class_end_time','physical_store.name as physical_store_name'])
            ->where(['course_offline_plan.id'=>$id])
            ->first();
        $courseOfflinePlanInfo = $courseOfflinePlanInfo->toArray();
        $classEndTime = $courseOfflinePlanInfo['class_end_time'];

        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->leftJoin('member','course_offline_order.member_id','=','member.id')
            ->select(['member.name as member_name','member.mobile as member_mobile','member.gender as member_gender','member.avatar','course_offline_order.member_id','course_offline_order.course_unit_price','course_offline_order.class_status','course_offline_order.order_status'])
            ->where(['course_offline_order.course_offline_plan_id'=>$id])
            ->orderBy('course_offline_order.order_status')
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $courseOfflinePlanInfo['class_start_time'] = date('H:i',$courseOfflinePlanInfo['class_start_time']);
        $courseOfflinePlanInfo['class_end_time'] = date('H:i',$courseOfflinePlanInfo['class_end_time']);

        $courseOfflineOrderMemberIdArray = [];
        $classAttendanceNum = 0;
        $signUpNum = 0;
        foreach($courseOfflineOrderList as $key=>$value){
            if(in_array($value['member_id'],$courseOfflineOrderMemberIdArray)){
                unset($courseOfflineOrderList[$key]);
                continue;
            }
            $signUpNum++;
            $orderStatus = 1;
            if($value['order_status'] == 2){
                $orderStatus = 4;
            }else if($value['class_status'] == 1){
                $orderStatus = 2;
                $classAttendanceNum++;
            }else if($classEndTime<$nowTime && $value['class_status'] == 0){
                $orderStatus = 3;
            }
            unset($courseOfflineOrderList[$key]['class_status']);
            $courseOfflineOrderMemberIdArray[] = $value['member_id'];

            $courseOfflineOrderList[$key]['order_status'] = $orderStatus;
        }
        $courseOfflinePlanInfo['sign_up_num'] = $signUpNum;
        $courseOfflinePlanInfo['course_class_attendance_num'] = $classAttendanceNum;
        $courseOfflinePlanInfo['students'] = !empty($courseOfflineOrderList) ? array_values($courseOfflineOrderList) : [];

        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$courseOfflinePlanInfo];
    }

    /**
     * 排课报名学生
     * @param int $id
     * @return array
     */
    public function courseOfflinePlanSignUpStudent(int $id): array
    {
        $nowDate = date('Y-m-d H:i:s');

        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->leftJoin('member','course_offline_order.member_id','=','member.id')
            ->select(['member.id as member_id','member.name','member.mobile','course_offline_order.id'])
            ->where(['course_offline_order.course_offline_plan_id'=>$id,'course_offline_order.pay_status'=>1,'course_offline_order.order_status'=>0])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $memberIdArray = array_column($courseOfflineOrderList,'member_id');

        //会员卡信息
        $vipCardOrderList = VipCardOrder::query()
            ->select(['id','member_id','course1','course1_used','course2','course2_used','course3','course3_used','currency_course','currency_course_used'])
            ->where([['pay_status','=',1],['order_status','=',0],['expire_at','>',$nowDate]])
            ->whereIn('member_id',$memberIdArray)
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        $vipCardOrderIdArray = array_column($vipCardOrderList,'id');
        $vipCardOrderDynamicCourseList = VipCardOrderDynamicCourse::query()
            ->select(['vip_card_order_id','course','course_used'])
            ->whereIn('vip_card_order_id',$vipCardOrderIdArray)
            ->get();
        $vipCardOrderDynamicCourseList = $vipCardOrderDynamicCourseList->toArray();
        $vipCardOrderDynamicCourseList = $this->functions->arrayGroupBy($vipCardOrderDynamicCourseList,'vip_card_order_id');
        foreach($vipCardOrderList as $key=>$value){
            $surplusSectionDynamicCourse = 0;
            $vipCardOrderDynamicCourse = $vipCardOrderDynamicCourseList[$value['id']] ?? [];
            foreach($vipCardOrderDynamicCourse as $item){
                $surplusSectionDynamicCourse += $item['course']-$item['course_used'];
            }
            $surplusSectionCourse = $value['course1']-$value['course1_used']+$value['course2']-$value['course2_used']+$value['course3']-$value['course3_used']+$value['currency_course']-$value['currency_course_used'];
            $vipCardOrderList[$key]['total_course'] = $surplusSectionDynamicCourse+$surplusSectionCourse;
        }
        $vipCardOrderList = $this->functions->arrayGroupBy($vipCardOrderList,'member_id');

        foreach($courseOfflineOrderList as $key=>$value){
            $memberId = $value['member_id'];
            $vipCardOrder = $vipCardOrderList[$memberId] ?? [];
            $courseSum = array_sum(array_column($vipCardOrder,'total_course'));
            $courseOfflineOrderList[$key]['course'] = $courseSum;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineOrderList];
    }

    /**
     * 排课实到学生
     * @param int $id
     * @return array
     */
    public function courseOfflinePlanArriveStudent(int $id): array
    {
        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->leftJoin('member','course_offline_order.member_id','=','member.id')
            ->select(['member.name','member.mobile','course_offline_order.created_at'])
            ->where(['course_offline_order.course_offline_plan_id'=>$id,'course_offline_order.pay_status'=>1,'course_offline_order.class_status'=>1])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineOrderList];
    }

    /**
     * 排课课堂情况
     * @param int $id
     * @return array
     */
    public function courseOfflinePlanClassroomSituation(int $id): array
    {
        $courseOfflineClassroomSituationList = CourseOfflineClassroomSituation::query()
            ->select(['img_url'])
            ->where(['course_offline_plan_id'=>$id])
            ->get();
        $courseOfflineClassroomSituationList = $courseOfflineClassroomSituationList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineClassroomSituationList];
    }


}