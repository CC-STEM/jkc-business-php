<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\VipCardConstant;
use App\Model\CourseOfflineOrder;
use App\Model\Teacher;
use App\Model\VipCard;
use App\Model\VipCardOrder;
use App\Constants\ErrorCode;
use App\Model\VipCardOrderPhysicalStore;
use App\Model\VipCardOrderRefund;
use App\Model\VipCardPhysicalStore;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;
use Hyperf\Di\Annotation\Inject;
use Psr\EventDispatcher\EventDispatcherInterface;

class VipCardService extends BaseService
{
    #[Inject]
    private EventDispatcherInterface $eventDispatcher;

    /**
     * MemberEventService
     * @throws \RedisException
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 会员卡订单列表
     * @param array $params
     * @return array
     */
    public function vipCardOrderList(array $params): array
    {
        $mobile = $params['mobile'];
        $cardName = $params['card_name'];
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];
        $status = $params['status'] ?? 0;
        $orderType = $params['order_type'];
        $vipCardId = $params['vip_card_id'];
        $memberName = $params['member_name'];
        $physicalStoreId = $this->adminsInfo['store_id'];
        $offset = $this->offset;
        $limit = $this->limit;
        $nowDate = date('Y-m-d H:i:s');
        if($startDate === null || $endDate === null){
            $startDate = null;
            $endDate = null;
        }

        $vipCardOrderPhysicalStoreList = VipCardOrderPhysicalStore::query()
            ->leftJoin('vip_card_order','vip_card_order_physical_store.vip_card_order_id','=','vip_card_order.id')
            ->select(['vip_card_order_physical_store.vip_card_order_id'])
            ->where([['vip_card_order.pay_status','=',1],['vip_card_order_physical_store.physical_store_id','=',$physicalStoreId]])
            ->get();
        $vipCardOrderPhysicalStoreList = $vipCardOrderPhysicalStoreList->toArray();
        $vipCardOrderIdPhysicalStoreArray = array_column($vipCardOrderPhysicalStoreList,'vip_card_order_id');

        $model = VipCardOrder::query()
            ->leftJoin('member', 'vip_card_order.member_id', '=', 'member.id')
            ->select(['member.id as member_id','member.name','member.mobile','vip_card_order.id','vip_card_order.order_title','vip_card_order.price','vip_card_order.expire','vip_card_order.course1','vip_card_order.course2','vip_card_order.course3','vip_card_order.currency_course','vip_card_order.created_at','vip_card_order.order_counter as serial_number','vip_card_order.recommend_code','vip_card_order.order_status','vip_card_order.closed_at as refund_at','vip_card_order.course1_used','vip_card_order.course2_used','vip_card_order.course3_used','vip_card_order.currency_course_used','vip_card_order.applicable_store_type','vip_card_order.expire_at','vip_card_order.card_theme_type','vip_card_order.recommend_teacher_id','vip_card_order.commission_rate']);
        $where = [['vip_card_order.pay_status','=',1]];
        $whereRaw = '';

        switch ($status){
            case 1:
                //未开始
                $where[] = ['vip_card_order.expire_at','=',VipCardConstant::DEFAULT_EXPIRE_AT];
                $startDate = null;
                $endDate = null;
                break;
            case 2:
                //使用中
                if($endDate === null || $endDate<$nowDate){
                    $endDate = VipCardConstant::DEFAULT_EXPIRE_AT;
                }
                if($startDate>$nowDate){
                    $where[] = ['vip_card_order.expire_at','>',$startDate];
                }else{
                    $where[] = ['vip_card_order.expire_at','>',$nowDate];
                }
                $where[] = ['vip_card_order.expire_at','<',$endDate];
                $whereRaw = '(vip_card_order.course1>vip_card_order.course1_used OR vip_card_order.course2>vip_card_order.course2_used OR vip_card_order.course3>vip_card_order.course3_used OR vip_card_order.currency_course>vip_card_order.currency_course_used)';
                $startDate = null;
                $endDate = null;
                break;
            case 3:
                //已用完
                $whereRaw = 'vip_card_order.course1=vip_card_order.course1_used AND vip_card_order.course2=vip_card_order.course2_used AND vip_card_order.course3=vip_card_order.course3_used AND vip_card_order.currency_course=vip_card_order.currency_course_used';
                break;
            case 4:
                //已过期
                if($endDate>$nowDate || $endDate === null){
                    $where[] = ['vip_card_order.expire_at','<=',$nowDate];
                }else{
                    $where[] = ['vip_card_order.expire_at','<=',$endDate];
                }
                if($startDate !== null && $startDate<$nowDate){
                    $where[] = ['vip_card_order.expire_at','>=',$startDate];
                }
                $whereRaw = '(vip_card_order.course1>vip_card_order.course1_used OR vip_card_order.course2>vip_card_order.course2_used OR vip_card_order.course3>vip_card_order.course3_used OR vip_card_order.currency_course>vip_card_order.currency_course_used)';
                $startDate = null;
                $endDate = null;
                break;
        }
        if($mobile !== null){
            $where[] = ['member.mobile','=',$mobile];
        }
        if($cardName !== null){
            $where[] = ['vip_card_order.order_title','like',"%{$cardName}%"];
        }
        if($startDate !== null && $endDate !== null){
            $model->whereBetween('vip_card_order.expire_at',[$startDate,$endDate]);
        }
        if($vipCardId !== null){
            $where[] = ['vip_card_order.vip_card_id','=',$vipCardId];
        }
        if($memberName !== null){
            $where[] = ['member.name','like',"%{$memberName}%"];
        }
        if($orderType === null){
            $model->whereIn('vip_card_order.order_type',[1,2]);
        }else{
            $where[] = ['vip_card_order.order_type','=',$orderType];
        }

        if($whereRaw === ''){
            if(empty($vipCardOrderIdPhysicalStoreArray)){
                $where[] = ['vip_card_order.applicable_store_type','=',1];
                $count = $model->where($where)->count();
            }else{
                $count = $model->where($where)
                    ->where(function ($query)use($vipCardOrderIdPhysicalStoreArray) {
                        $query->whereIn('vip_card_order.id',$vipCardOrderIdPhysicalStoreArray)->orWhere(['vip_card_order.applicable_store_type'=>1]);
                    })
                    ->count();
            }
        }else{
            if(empty($vipCardOrderIdPhysicalStoreArray)){
                $where[] = ['vip_card_order.applicable_store_type','=',1];
                $count = $model->where($where)->whereRaw($whereRaw)->count();
            }else{
                $count = $model->where($where)->whereRaw($whereRaw)
                    ->where(function ($query)use($vipCardOrderIdPhysicalStoreArray) {
                        $query->whereIn('vip_card_order.id',$vipCardOrderIdPhysicalStoreArray)->orWhere(['vip_card_order.applicable_store_type'=>1]);
                    })
                    ->count();
            }
        }
        $vipCardOrderList = $model->orderBy('vip_card_order.id','desc')->offset($offset)->limit($limit)->get();
        $vipCardOrderList = $vipCardOrderList->toArray();

        foreach($vipCardOrderList as $key=>$value){
            $parentName = '系统';
            $parentMobile = '无';
            $commission = '无';
            $themeType = '常规班';
            $physicalStoreName = '全部门店';
            if(!empty($value['recommend_teacher_id'])){
                $parentMemberInfo = Teacher::query()->select(['name','mobile'])->where(['id'=>$value['recommend_teacher_id']])->first();
                $parentMemberInfo = $parentMemberInfo?->toArray();
                $parentName = $parentMemberInfo['name'] ?? '系统';
                $parentMobile = $parentMemberInfo['mobile'] ?? '无';
            }
            $refundAmount = 0;
            if($value['order_status'] == 3){
                $refundAmount = VipCardOrderRefund::query()->where(['vip_card_order_id'=>$value['id'],'status'=>25])->sum('amount');
            }
            $surplusSectionCourse1 = $value['course1']-$value['course1_used'];
            $surplusSectionCourse2 = $value['course2']-$value['course2_used'];
            $surplusSectionCourse3 = $value['course3']-$value['course3_used'];
            $surplusSectionCurrencyCourse = $value['currency_course']-$value['currency_course_used'];
            if($value['applicable_store_type'] == 2){
                $vipCardOrderPhysicalStoreList = VipCardOrderPhysicalStore::query()
                    ->leftJoin('physical_store','vip_card_order_physical_store.physical_store_id','=','physical_store.id')
                    ->select(['physical_store.name'])
                    ->where(['vip_card_order_physical_store.vip_card_order_id'=>$value['id']])
                    ->get();
                $vipCardOrderPhysicalStoreList = $vipCardOrderPhysicalStoreList->toArray();
                $physicalStoreName = implode(',',array_column($vipCardOrderPhysicalStoreList,'name'));
            }
            if($value['expire_at'] === VipCardConstant::DEFAULT_EXPIRE_AT){
                $statusText = '未使用';
            }else if($surplusSectionCourse1==0 && $surplusSectionCourse2==0 && $surplusSectionCourse3==0 && $surplusSectionCurrencyCourse==0){
                $statusText = '已用完';
            }else if($value['expire_at'] > $nowDate){
                $statusText = '使用中';
            }else{
                $statusText = '已过期';
            }
            if($value['card_theme_type'] == 2){
                $themeType = '精品小班';
            }else if($value['card_theme_type'] == 3){
                $themeType = '代码编程';
            }
            if($value['recommend_teacher_id'] != 0){
                $commissionRate = bcdiv($value['commission_rate'],'100',4);
                $commission = bcmul($value['price'],$commissionRate,2);
            }

            $vipCardOrderList[$key]['refund_amount'] = $refundAmount;
            $vipCardOrderList[$key]['parent_name'] = $parentName;
            $vipCardOrderList[$key]['parent_mobile'] = $parentMobile;
            $vipCardOrderList[$key]['surplus_course1'] = $surplusSectionCourse1;
            $vipCardOrderList[$key]['surplus_course2'] = $surplusSectionCourse2;
            $vipCardOrderList[$key]['surplus_course3'] = $surplusSectionCourse3;
            $vipCardOrderList[$key]['surplus_currency_course'] = $surplusSectionCurrencyCourse;
            $vipCardOrderList[$key]['physical_store_name'] = $physicalStoreName;
            $vipCardOrderList[$key]['status_text'] = $statusText;
            $vipCardOrderList[$key]['theme_type'] = $themeType;
            $vipCardOrderList[$key]['commission'] = $commission;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$vipCardOrderList,'count'=>$count]];
    }

    /**
     * 赠送会员卡订单列表
     * @param array $params
     * @return array
     */
    public function giftVipCardOrderList(array $params): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];

        $mobile = $params['mobile'] ?? null;
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        $status = $params['status'] ?? 0;
        $memberName = $params['member_name'] ?? null;
        $offset = $this->offset;
        $limit = $this->limit;
        $nowDate = date('Y-m-d H:i:s');
        if($startDate === null || $endDate === null){
            $startDate = null;
            $endDate = null;
        }

        $model = VipCardOrder::query()
            ->leftJoin('member', 'vip_card_order.member_id', '=', 'member.id')
            ->leftJoin('vip_card_order_physical_store', 'vip_card_order.id', '=', 'vip_card_order_physical_store.vip_card_order_id')
            ->select(['member.id as member_id','member.name','member.mobile','vip_card_order.id','vip_card_order.price','vip_card_order.order_title','vip_card_order.expire','vip_card_order.course1','vip_card_order.course2','vip_card_order.course3','vip_card_order.currency_course','vip_card_order.created_at','vip_card_order.order_status','vip_card_order.course1_used','vip_card_order.course2_used','vip_card_order.course3_used','vip_card_order.currency_course_used','vip_card_order.applicable_store_type','vip_card_order.expire_at','vip_card_order.card_theme_type']);
        $where = [['vip_card_order.order_type','=',4]];
        $whereRaw = '';

        switch ($status){
            case 1:
                //未开始
                $where[] = ['vip_card_order.expire_at','=',VipCardConstant::DEFAULT_EXPIRE_AT];
                $startDate = null;
                $endDate = null;
                break;
            case 2:
                //使用中
                if($endDate === null || $endDate<$nowDate){
                    $endDate = VipCardConstant::DEFAULT_EXPIRE_AT;
                }
                if($startDate>$nowDate){
                    $where[] = ['vip_card_order.expire_at','>',$startDate];
                }else{
                    $where[] = ['vip_card_order.expire_at','>',$nowDate];
                }
                $where[] = ['vip_card_order.expire_at','<',$endDate];
                $whereRaw = '(vip_card_order.course1>vip_card_order.course1_used OR vip_card_order.course2>vip_card_order.course2_used OR vip_card_order.course3>vip_card_order.course3_used OR vip_card_order.currency_course>vip_card_order.currency_course_used)';
                $startDate = null;
                $endDate = null;
                break;
            case 3:
                //已用完
                $whereRaw = 'vip_card_order.course1=vip_card_order.course1_used AND vip_card_order.course2=vip_card_order.course2_used AND vip_card_order.course3=vip_card_order.course3_used AND vip_card_order.currency_course=vip_card_order.currency_course_used';
                break;
            case 4:
                //已过期
                if($endDate>$nowDate || $endDate === null){
                    $where[] = ['vip_card_order.expire_at','<=',$nowDate];
                }else{
                    $where[] = ['vip_card_order.expire_at','<=',$endDate];
                }
                if($startDate !== null && $startDate<$nowDate){
                    $where[] = ['vip_card_order.expire_at','>=',$startDate];
                }
                $whereRaw = '(vip_card_order.course1>vip_card_order.course1_used OR vip_card_order.course2>vip_card_order.course2_used OR vip_card_order.course3>vip_card_order.course3_used OR vip_card_order.currency_course>vip_card_order.currency_course_used)';
                $startDate = null;
                $endDate = null;
                break;
        }
        if($mobile !== null){
            $where[] = ['member.mobile','=',$mobile];
        }
        if($startDate !== null && $endDate !== null){
            $model->whereBetween('vip_card_order.expire_at',[$startDate,$endDate]);
        }
        if($physicalStoreId !== null){
            $model->where(function($query) use ($physicalStoreId) {
                $query->where('vip_card_order.applicable_store_type', 1)
                    ->orWhere('vip_card_order_physical_store.physical_store_id', $physicalStoreId);
            });
        }
        if($memberName !== null){
            $where[] = ['member.name','like',"%{$memberName}%"];
        }

        if($whereRaw === ''){
            $count = $model->where($where)->count(Db::connection('jkc_edu')->raw('DISTINCT vip_card_order.id'));
        }else{
            $count = $model->where($where)->whereRaw($whereRaw)->count(Db::connection('jkc_edu')->raw('DISTINCT vip_card_order.id'));
        }
        $vipCardOrderList = $model
            ->orderBy('vip_card_order.id', 'desc')
            ->groupBy('vip_card_order.id')
            ->offset($offset)
            ->limit($limit)
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();

        foreach($vipCardOrderList as $key=>$value){
            $themeType = '常规班';
            $physicalStoreName = '全部门店';

            $surplusSectionCourse1 = $value['course1']-$value['course1_used'];
            $surplusSectionCourse2 = $value['course2']-$value['course2_used'];
            $surplusSectionCourse3 = $value['course3']-$value['course3_used'];
            $surplusSectionCurrencyCourse = $value['currency_course']-$value['currency_course_used'];
            $totalCourse = $value['course1'] + $value['course2'] + $value['course3'] + $value['currency_course'];
            $totalCourseUsed = $value['course1_used'] + $value['course2_used'] + $value['course3_used'] + $value['currency_course_used'];
            if ($value['applicable_store_type'] == 2) {
                $vipCardOrderPhysicalStoreList = VipCardOrderPhysicalStore::query()
                    ->leftJoin('physical_store', 'vip_card_order_physical_store.physical_store_id', '=', 'physical_store.id')
                    ->select(['physical_store.name'])
                    ->where(['vip_card_order_physical_store.vip_card_order_id' => $value['id']])
                    ->get();
                $vipCardOrderPhysicalStoreList = $vipCardOrderPhysicalStoreList->toArray();
                $physicalStoreName = implode(',', array_column($vipCardOrderPhysicalStoreList, 'name'));
            }
            if($value['expire_at'] === VipCardConstant::DEFAULT_EXPIRE_AT){
                $statusText = '未使用';
            }else if($surplusSectionCourse1==0 && $surplusSectionCourse2==0 && $surplusSectionCourse3==0 && $surplusSectionCurrencyCourse==0){
                $statusText = '已用完';
            }else if($value['expire_at'] > $nowDate){
                $statusText = '使用中';
            }else{
                $statusText = '已过期';
            }
            if($value['card_theme_type'] == 2){
                $themeType = '精品小班';
            }else if($value['card_theme_type'] == 3){
                $themeType = '代码编程';
            }

            $vipCardOrderList[$key]['total_course'] = $totalCourse;
            $vipCardOrderList[$key]['total_course_used'] = $totalCourseUsed;
            $vipCardOrderList[$key]['physical_store_name'] = $physicalStoreName;
            $vipCardOrderList[$key]['status_text'] = $statusText;
            $vipCardOrderList[$key]['theme_type'] = $themeType;
            $vipCardOrderList[$key]['created_at'] = date('Y.m.d H:i', strtotime($value['created_at']));
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$vipCardOrderList,'count'=>$count]];
    }


    /**
     * 平台赠送会员卡详情列表
     * @param array $query
     * @return array
     */
    public function giftVipCardOrderDetail(array $query): array
    {
        $id = $query['id'] ?? 0;

        $offset = $this->offset;
        $limit = $this->limit;

        $courseOfflineOrderModel = CourseOfflineOrder::query()
            ->leftJoin('course_offline_plan', 'course_offline_order.course_offline_plan_id', '=', 'course_offline_plan.id')
            ->where('course_offline_order.vip_card_order_id', $id)
            ->where('course_offline_order.order_status', 0)
            ->where('course_offline_order.pay_status', 1);

        $count = $courseOfflineOrderModel->count();

        $fields = [
            'course_offline_order.id', 'course_offline_order.course_name', 'course_offline_order.teacher_name',
            'course_offline_order.physical_store_name', 'course_offline_order.class_status',
            'course_offline_order.start_at', 'course_offline_order.created_at'
        ];
        $courseOfflineOrderList = $courseOfflineOrderModel->select($fields)
            ->offset($offset)
            ->limit($limit)
            ->orderByDesc('course_offline_order.id')
            ->get()
            ->toArray();

        foreach ($courseOfflineOrderList as $index => &$item) {
            $statusText = '已报名';
            if ($item['class_status'] == 1) {
                $statusText = '已上课';
            }

            $item['status_text'] = $statusText;
            $item['start_at'] = date('Y.m.d H:i', strtotime($item['start_at']));
            $item['created_at'] = date('Y.m.d H:i', strtotime($item['created_at']));
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list' => $courseOfflineOrderList, 'count' => $count]];
    }

    /**
     * 新人礼包会员卡列表
     * @return array
     */
    public function newcomerVipCardList(): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];

        $vipCardPhysicalStoreList = VipCardPhysicalStore::query()
            ->leftJoin('vip_card','vip_card_physical_store.vip_card_id','=','vip_card.id')
            ->select(['vip_card_physical_store.vip_card_id'])
            ->where(['vip_card_physical_store.physical_store_id'=>$physicalStoreId,'vip_card.type'=>3,'vip_card.is_deleted'=>0])
            ->get();
        $vipCardPhysicalStoreList = $vipCardPhysicalStoreList->toArray();
        $vipCardIdPhysicalStoreArray = array_column($vipCardPhysicalStoreList,'vip_card_id');

        if(empty($vipCardIdPhysicalStoreArray)){
            $vipCardList = VipCard::query()
                ->select(['id','name','price','expire','original_price','rule','grade','created_at','explain','start_at','end_at','applicable_store_type'])
                ->where(['type'=>3,'is_deleted'=>0,'applicable_store_type'=>1])
                ->get();
        }else{
            $vipCardList = VipCard::query()
                ->select(['id','name','price','expire','original_price','rule','grade','created_at','explain','start_at','end_at','applicable_store_type'])
                ->whereIn('id',$vipCardIdPhysicalStoreArray)->orWhere(function ($query) {
                    $query->where(['type'=>3,'is_deleted'=>0,'applicable_store_type'=>1]);
                })
                ->get();
        }
        $vipCardList = $vipCardList->toArray();

        foreach($vipCardList as $key=>$value){
            $id = $value['id'];
            $rule = json_decode($value['rule'],true);
            $currencyCourse = isset($rule['currency_course']) ? $rule['currency_course'] : 0;
            unset($vipCardList[$key]['rule']);
            $vipCardList[$key]['currency_course'] = $currencyCourse;

            $vipCardPhysicalStore = '全部门店';
            if($value['applicable_store_type'] == 2){
                $vipCardPhysicalStoreList = VipCardPhysicalStore::query()
                    ->leftJoin('physical_store', 'vip_card_physical_store.physical_store_id', '=', 'physical_store.id')
                    ->select(['physical_store.name'])
                    ->where(['vip_card_physical_store.vip_card_id'=>$id])
                    ->get();
                $vipCardPhysicalStoreList = $vipCardPhysicalStoreList->toArray();
                $vipCardPhysicalStore = implode('  ',array_column($vipCardPhysicalStoreList,'name'));
            }
            $vipCardList[$key]['physical_store'] = $vipCardPhysicalStore;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $vipCardList];
    }


}