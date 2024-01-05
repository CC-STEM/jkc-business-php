<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\AdminsCache;
use App\Constants\VipCardConstant;
use App\Model\OrderGoods;
use App\Model\CourseOfflineOrder;
use App\Model\VipCardOrder;
use App\Model\Member;
use App\Constants\ErrorCode;
use App\Snowflake\IdGenerator;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;

class MemberService extends BaseService
{
    /**
     * MemberService constructor.
     * @throws \RedisException
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 会员列表
     * @param array $params
     * @return array
     */
    public function memberList(array $params): array
    {
        $mobile = $params['mobile'];
        $name = $params['name'];
        $offset = $this->offset;
        $limit = $this->limit;
        $physicalStoreId = $this->adminsInfo['store_id'];

        $vipCardOrderMember = VipCardOrder::query()
            ->select(['member_id'])
            ->where(['physical_store_id'=>$physicalStoreId])
            ->get();
        $vipCardOrderMember = $vipCardOrderMember->toArray();
        $vipCardOrderMember = array_column($vipCardOrderMember,'member_id');
        $courseOfflineOrderMember = CourseOfflineOrder::query()
            ->select(['member_id'])
            ->where(['physical_store_id'=>$physicalStoreId])
            ->get();
        $courseOfflineOrderMember = $courseOfflineOrderMember->toArray();
        $courseOfflineOrderMember = array_column($courseOfflineOrderMember,'member_id');
        $memberIdArray = array_merge($vipCardOrderMember,$courseOfflineOrderMember);
        if(empty($memberIdArray)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>[],'count'=>0]];
        }
        $memberIdArray = array_values(array_unique($memberIdArray));

        $where = [];
        if($mobile !== null){
            $where[] = ['mobile', '=', $mobile];
        }
        if($name !== null){
            $where[] = ['name', 'like', "%{$name}%"];
        }
        $count = Member::query()->where($where)->whereIn('id',$memberIdArray)->count();
        $memberList = Member::query()
            ->select(['id','name','mobile','created_at','parent_id'])
            ->where($where)->whereIn('id',$memberIdArray)
            ->offset($offset)->limit($limit)
            ->get();
        $memberList = $memberList->toArray();

        foreach($memberList as $key=>$value){
            $memberId = $value['id'];

            $vipCardOrderList = VipCardOrder::query()->select(['course1','course2','course3','course1_used','course2_used','course3_used'])->where(['member_id'=>$memberId,'pay_status'=>1])->get();
            $course1Sum = 0;
            $course1UsedSum = 0;
            $course2Sum = 0;
            $course2UsedSum = 0;
            $course3Sum = 0;
            $course3UsedSum = 0;
            foreach($vipCardOrderList as $item){
                $course1Sum += $item['course1'];
                $course1UsedSum += $item['course1_used'];
                $course2Sum += $item['course2'];
                $course2UsedSum += $item['course2_used'];
                $course3Sum += $item['course3'];
                $course3UsedSum += $item['course3_used'];
            }
            if($value['parent_id'] != 0){
                $parentMemberInfo = Member::query()->select(['name','mobile','created_at'])->where(['id'=>$value['parent_id']])->first();
                $parentMemberInfo = $parentMemberInfo->toArray();
            }
            $memberList[$key]['course1'] = ['num1'=>$course1Sum,'num2'=>$course1UsedSum];
            $memberList[$key]['course2'] = ['num1'=>$course2Sum,'num2'=>$course2UsedSum];
            $memberList[$key]['course3'] = ['num1'=>$course3Sum,'num2'=>$course3UsedSum];
            $memberList[$key]['parent_name'] = $parentMemberInfo['name'] ?? '';
            $memberList[$key]['parent_mobile'] = $parentMemberInfo['mobile'] ?? '';
            $memberList[$key]['parent_created_at'] = $parentMemberInfo['created_at'] ?? '';
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$memberList,'count'=>$count]];
    }

    /**
     * 会员详情
     * @param int $id
     * @return array
     */
    public function memberDetail(int $id): array
    {
        $memberInfo = Member::query()->select(['name','avatar','mobile','age','created_at'])->where(['id'=>$id])->first();
        if(empty($memberInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '', 'data' => null];
        }
        $memberInfo = $memberInfo->toArray();

        //会员卡信息
        $vipCardOrderList = VipCardOrder::query()
            ->select(['id','course1','course1_used','course2','course2_used','course3','course3_used'])
            ->where(['member_id'=>$id,'pay_status'=>1])
            ->whereIn('order_type',[1,4])
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        $totalCourse1 = 0;
        $totalUsedCourse1 = 0;
        $totalCourse2 = 0;
        $totalUsedCourse2 = 0;
        $totalCourse3 = 0;
        $totalUsedCourse3 = 0;
        foreach($vipCardOrderList as $value){
            $surplusSectionCourse1 = $value['course1']-$value['course1_used'];
            $surplusSectionCourse2 = $value['course2']-$value['course2_used'];
            $surplusSectionCourse3 = $value['course3']-$value['course3_used'];

            $totalUsedCourse1 += $value['course1_used'];
            $totalUsedCourse2 += $value['course2_used'];
            $totalUsedCourse3 += $value['course3_used'];
            $totalCourse1 = $surplusSectionCourse1>0 ? $totalCourse1+$surplusSectionCourse1 : $totalCourse1;
            $totalCourse2 = $surplusSectionCourse2>0 ? $totalCourse2+$surplusSectionCourse2 : $totalCourse2;
            $totalCourse3 = $surplusSectionCourse3>0 ? $totalCourse3+$surplusSectionCourse3 : $totalCourse3;
        }
        $memberInfo['course1'] = $totalCourse1;
        $memberInfo['course1_used'] = $totalUsedCourse1;
        $memberInfo['course2'] = $totalCourse2;
        $memberInfo['course2_used'] = $totalUsedCourse2;
        $memberInfo['course3'] = $totalCourse3;
        $memberInfo['course3_used'] = $totalUsedCourse3;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $memberInfo];
    }

    /**
     * 线上课程收藏列表
     * @param array $params
     * @return array
     */
    public function courseOnlineCollectList(array $params): array
    {
        $memberId = $params['member_id'];
        $offset = $this->offset;
        $limit = $this->limit;

        $courseOnlineCollectList = Db::connection('jkc_edu')->table('course_online_collect')
            ->leftJoin('course_online', 'course_online_collect.course_online_id', '=', 'course_online.id')
            ->select(['course_online_collect.id','course_online_collect.total_section','course_online_collect.study_section','course_online_collect.created_at','course_online.name','course_online.id as course_id','course_online.suit_age_min','course_online.suit_age_max'])
            ->where(['course_online_collect.member_id'=>$memberId])
            ->offset($offset)->limit($limit)
            ->get();
        $courseOnlineCollectList = $courseOnlineCollectList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOnlineCollectList];
    }

    /**
     * 线上子课程收藏列表
     * @param array $params
     * @return array
     */
    public function courseOnlineChildCollectList(array $params): array
    {
        $courseOnlineCollectId = $params['course_online_collect_id'];

        $courseOnlineChildCollectList = Db::connection('jkc_edu')->table('course_online_child_collect')
            ->leftJoin('course_online_child', 'course_online_child_collect.course_online_child_id', '=', 'course_online_child.id')
            ->select(['course_online_child.name','course_online_child.id as course_child_id','course_online_child_collect.study_video_url','course_online_child_collect.study_at','course_online_child_collect.examine_at'])
            ->where(['course_online_child_collect.course_online_collect_id'=>$courseOnlineCollectId])
            ->get();
        $courseOnlineChildCollectList = $courseOnlineChildCollectList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOnlineChildCollectList];
    }

    /**
     * 线下课程订单列表
     * @param array $params
     * @return array
     */
    public function courseOfflineOrderList(array $params): array
    {
        $classStatus = $params['class_status'];
        $memberId = $params['member_id'];
        $offset = $this->offset;
        $limit = $this->limit;
        $nowDate = date('Y-m-d H:i:');

        if($classStatus == 0){
            $courseOfflineOrderList = CourseOfflineOrder::query()
                ->leftJoin('course_offline','course_offline_order.course_offline_id','=','course_offline.id')
                ->select(['course_offline_order.course_name','course_offline_order.course_offline_id','course_offline_order.course_type','course_offline_order.start_at','course_offline_order.end_at','course_offline_order.physical_store_name','course_offline_order.classroom_name','course_offline_order.teacher_name','course_offline_order.class_status','course_offline.video_url'])
                ->where([['course_offline_order.member_id','=',$memberId],['course_offline_order.end_at','>',$nowDate]])
                ->offset($offset)->limit($limit)
                ->get();
        }else{
            $courseOfflineOrderList = CourseOfflineOrder::query()
                ->leftJoin('course_offline','course_offline_order.course_offline_id','=','course_offline.id')
                ->select(['course_offline_order.course_name','course_offline_order.course_offline_id','course_offline_order.course_type','course_offline_order.start_at','course_offline_order.end_at','course_offline_order.physical_store_name','course_offline_order.classroom_name','course_offline_order.teacher_name','course_offline_order.class_status','course_offline.video_url'])
                ->where([['course_offline_order.member_id','=',$memberId],['course_offline_order.end_at','<=',$nowDate]])
                ->offset($offset)->limit($limit)
                ->get();
        }
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineOrderList];
    }

    /**
     * 教具订单列表
     * @param array $params
     * @return array
     */
    public function teachingAidsOrderList(array $params): array
    {
        $memberId = $params['member_id'];
        $offset = $this->offset;
        $limit = $this->limit;

        $orderGoodsList = OrderGoods::query()
            ->leftJoin('order_info', 'order_goods.order_info_id', '=', 'order_info.id')
            ->leftJoin('order_refund', 'order_goods.id', '=', 'order_refund.order_goods_id')
            ->select(['order_info.order_no','order_goods.goods_id','order_goods.goods_name','order_goods.goods_img','order_goods.prop_value_str','order_goods.quantity','order_goods.pay_price','order_goods.order_status','order_goods.shipping_status','order_refund.status as refund_status'])
            ->where(['order_goods.member_id'=>$memberId,'order_goods.pay_status'=>1])
            ->offset($offset)->limit($limit)
            ->orderBy('order_goods.id','desc')
            ->get();
        $orderGoodsList = $orderGoodsList->toArray();
        foreach($orderGoodsList as $key=>$value){

            //待发货
            $status = 1;
            if(!empty($value['refund_status']) && in_array($value['refund_status'],[10,15,20])){
                //售后中
                $status = 4;
            }else if($value['order_status'] == 0 && $value['shipping_status'] == 1){
                //待完成
                $status = 2;
            }else if($value['order_status'] == 0 && $value['shipping_status'] == 2){
                //已完成
                $status = 3;
            }else if($value['order_status'] != 0){
                //已关闭
                $status = 5;
            }
            $orderGoodsList[$key]['order_status'] = $status;
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $orderGoodsList];
    }

    /**
     * 会员卡订单列表
     * @param array $params
     * @return array
     */
    public function vipCardOrderList(array $params): array
    {
        $memberId = $params['member_id'];
        $offset = $this->offset;
        $limit = $this->limit;

        $model = VipCardOrder::query()
            ->select(['order_title','price','expire','course1','course2','course3','created_at','expire_at','grade','course1_used','course2_used','course3_used','card_type'])
            ->where(['member_id'=>$memberId,'pay_status'=>1])
            ->whereIn('order_type',[1,4]);
        $count = $model->count();
        $vipCardOrderList = $model->orderBy('vip_card_order.id','desc')->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        foreach($vipCardOrderList as $key=>$value){
            $surplusCourse1 = $value['course1']-$value['course1_used'];
            $surplusCourse2 = $value['course2']-$value['course2_used'];
            $surplusCourse3 = $value['course3']-$value['course3_used'];
            $vipCardOrderList[$key]['surplus_course1'] = $surplusCourse1;
            $vipCardOrderList[$key]['surplus_course2'] = $surplusCourse2;
            $vipCardOrderList[$key]['surplus_course3'] = $surplusCourse3;
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$vipCardOrderList,'count'=>$count]];
    }

    /**
     * 创建会员卡订单
     * @param array $params
     * @return array
     * @throws \Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function vipCardOrderCreate(array $params): array
    {
        $expire = $params['expire'];
        $cardThemeType = $params['card_theme_type'] ?? 1;

        //订单数据
        $orderNo = $this->functions->orderNo();
        $orderId = IdGenerator::generate();
        $insertOrder['id'] = $orderId;
        $insertOrder['member_id'] = $params['member_id'];
        $insertOrder['order_no'] = $orderNo;
        $insertOrder['price'] = $params['price'];
        $insertOrder['order_title'] = $params['name'];
        $insertOrder['expire'] = $expire;
        $insertOrder['expire_at'] = VipCardConstant::DEFAULT_EXPIRE_AT;
        $insertOrder['course1'] = $params['course1'] ?? 0;
        $insertOrder['course2'] = $params['course2'] ?? 0;
        $insertOrder['course3'] = $params['course3'] ?? 0;
        $insertOrder['order_type'] = 4;
        $insertOrder['pay_status'] = 1;
        $insertOrder['card_theme_type'] = $cardThemeType;

        VipCardOrder::query()->insert($insertOrder);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 邀请关系树
     * @param int $parentId
     * @return array
     */
    public function invitationRelationTree(int $parentId): array
    {
        $parentMemberInfo = Member::query()
            ->select(['name','mobile','created_at'])
            ->where(['id'=>$parentId])
            ->first();
        $parentMemberInfo = $parentMemberInfo->toArray();

        $childMemberList = Member::query()
            ->select(['name','mobile','created_at'])
            ->where(['parent_id'=>$parentId])
            ->orderBy('created_at')
            ->get();
        $childMemberList = $childMemberList->toArray();

        $returnData = [
            'parent' => $parentMemberInfo,
            'child' => $childMemberList
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 会员搜索列表
     * @param array $params
     * @return array
     */
    public function memberSearchList(array $params): array
    {
        $mobile = $params['mobile'] ?? '';
        $name = $params['name'] ?? '';

        $where = [];
        if(!empty($mobile)){
            $where[] = ['mobile', '=', $mobile];
        }
        if(!empty($name)){
            $where[] = ['name', 'like', "%{$name}%"];
        }
        $memberList = Member::query()
            ->select(['id', 'name', 'mobile'])
            ->where($where)
            ->get();
        $memberList = $memberList->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $memberList];
    }

}