<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Logger\Log;
use App\Model\Coupon;
use App\Model\CouponTemplate;
use App\Model\CouponTemplatePhysicalStore;
use App\Model\Member;
use App\Model\PhysicalStore;
use App\Model\PhysicalStoreCouponIssuedRecord;
use App\Model\PhysicalStoreCouponTemplate;
use App\Snowflake\IdGenerator;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;

class CouponService extends BaseService
{
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 优惠券模板列表
     * @return array
     */
    public function couponTemplateList(): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];
        $offset = $this->offset;
        $limit = $this->limit;

        $couponTemplateList = PhysicalStoreCouponTemplate::query()
            ->leftJoin('coupon_template','physical_store_coupon_template.coupon_template_id','=','coupon_template.id')
            ->select(['physical_store_coupon_template.id','physical_store_coupon_template.coupon_template_id','physical_store_coupon_template.issued_quantity','physical_store_coupon_template.totality','coupon_template.name','coupon_template.threshold_amount','coupon_template.amount','coupon_template.end_at','coupon_template.applicable_theme_type'])
            ->where(['coupon_template.is_deleted'=>0,'physical_store_coupon_template.physical_store_id'=>$physicalStoreId])
            ->offset($offset)->limit($limit)
            ->get();
        $couponTemplateList = $couponTemplateList->toArray();
        $count = PhysicalStoreCouponTemplate::query()->count();

        foreach($couponTemplateList as $key=>$value){
            $surplusQuantity = '无';
            $physicalStore = '无';
            if($value['totality']>0){
                $surplusQuantity = $value['totality']-$value['issued_quantity'];
            }
            $couponTemplatePhysicalStoreList = CouponTemplatePhysicalStore::query()
                ->select(['physical_store_id'])
                ->where(['coupon_template_id'=>$value['coupon_template_id']])
                ->get();
            $couponTemplatePhysicalStoreList = $couponTemplatePhysicalStoreList->toArray();
            if(!empty($couponTemplatePhysicalStoreList)){
                $physicalStoreIdArray = array_column($couponTemplatePhysicalStoreList,'physical_store_id');
                $physicalStoreList = PhysicalStore::query()
                    ->select(['name'])
                    ->whereIn('id',$physicalStoreIdArray)
                    ->get();
                $physicalStoreList = $physicalStoreList->toArray();
                $physicalStore = implode(' ',array_column($physicalStoreList,'name'));
            }

            $couponTemplateList[$key]['physical_store'] = $physicalStore;
            $couponTemplateList[$key]['surplus_quantity'] = $surplusQuantity;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$couponTemplateList,'count'=>$count]];
    }

    /**
     * 优惠券发放
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function issuedCoupon(array $params): array
    {
        $mobile = $params['mobile'];
        $physicalStoreCouponTemplateId = $params['id'];
        $physicalStoreId = $this->adminsInfo['store_id'];
        $mobile = explode(',',$mobile);

        $physicalStoreCouponTemplateInfo = PhysicalStoreCouponTemplate::query()
            ->select(['coupon_template_id'])
            ->where(['id'=>$physicalStoreCouponTemplateId])
            ->first();
        $physicalStoreCouponTemplateInfo = $physicalStoreCouponTemplateInfo->toArray();
        $couponTemplateId = $physicalStoreCouponTemplateInfo['coupon_template_id'];

        $memberList = Member::query()
            ->select(['id'])
            ->whereIn('mobile',$mobile)
            ->get();
        $memberList = $memberList->toArray();
        $issuedQuantity = count($memberList);

        $couponTemplateInfo = CouponTemplate::query()
            ->select(['id','name','threshold_amount','amount','end_at','totality','applicable_store_type','applicable_theme_type'])
            ->where(['id'=>$couponTemplateId,'is_deleted'=>0])
            ->first();
        $couponTemplateInfo = $couponTemplateInfo->toArray();

        $couponTemplatePhysicalStoreList = CouponTemplatePhysicalStore::query()
            ->select(['physical_store_id'])
            ->where(['coupon_template_id'=>$couponTemplateId])
            ->get();
        $couponTemplatePhysicalStoreList = $couponTemplatePhysicalStoreList->toArray();

        $insertCouponData = [];
        $insertCouponPhysicalStoreData = [];
        $insertPhysicalStoreCouponIssuedRecordData = [];
        foreach($memberList as $value){
            $couponId = IdGenerator::generate();
            $couponData['id'] = $couponId;
            $couponData['member_id'] = $value['id'];
            $couponData['coupon_template_id'] = $couponTemplateInfo['id'];
            $couponData['name'] = $couponTemplateInfo['name'];
            $couponData['threshold_amount'] = $couponTemplateInfo['threshold_amount'];
            $couponData['amount'] = $couponTemplateInfo['amount'];
            $couponData['end_at'] = $couponTemplateInfo['end_at'];
            $couponData['applicable_store_type'] = $couponTemplateInfo['applicable_store_type'];
            $couponData['applicable_theme_type'] = $couponTemplateInfo['applicable_theme_type'];
            $couponData['issuer_type'] = 2;
            $insertCouponData[] = $couponData;

            foreach($couponTemplatePhysicalStoreList as $item){
                $couponPhysicalStoreData['id'] = IdGenerator::generate();
                $couponPhysicalStoreData['coupon_id'] = $couponId;
                $couponPhysicalStoreData['physical_store_id'] = $item['physical_store_id'];
                $insertCouponPhysicalStoreData[] = $couponPhysicalStoreData;
            }
            $physicalStoreCouponIssuedRecordData['physical_store_id'] = $physicalStoreId;
            $physicalStoreCouponIssuedRecordData['physical_store_coupon_template_id'] = $physicalStoreCouponTemplateId;
            $physicalStoreCouponIssuedRecordData['coupon_id'] = $couponId;
            $insertPhysicalStoreCouponIssuedRecordData[] = $physicalStoreCouponIssuedRecordData;
        }

        Db::connection('jkc_edu')->beginTransaction();
        try{
            Db::connection('jkc_edu')->table('coupon')->insert($insertCouponData);
            if(!empty($insertCouponPhysicalStoreData)){
                Db::connection('jkc_edu')->table('coupon_physical_store')->insert($insertCouponPhysicalStoreData);
            }
            $physicalStoreCouponTemplateAffected = Db::connection('jkc_edu')->update("UPDATE physical_store_coupon_template SET issued_quantity=issued_quantity + ? WHERE id = ? AND totality >= issued_quantity+?", [$issuedQuantity,$physicalStoreCouponTemplateId,$issuedQuantity]);
            if(!$physicalStoreCouponTemplateAffected){
                Db::connection('jkc_edu')->rollBack();
                Log::get()->info("issuedCouponBatch:优惠券批量发行失败");
                return ['code' => ErrorCode::FAILURE, 'msg' => '优惠券发行失败', 'data' => null];
            }
            PhysicalStoreCouponIssuedRecord::query()->insert($insertPhysicalStoreCouponIssuedRecordData);
            Db::connection('jkc_edu')->commit();
        } catch(\Throwable $e){
            Db::connection('jkc_edu')->rollBack();
            throw new \Exception($e->getMessage(), 1);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 优惠券发放列表
     * @param array $params
     * @return array
     */
    public function issuedCouponList(array $params): array
    {
        $physicalStoreCouponTemplateId = $params['id'];
        $offset = $this->offset;
        $limit = $this->limit;

        $couponList = PhysicalStoreCouponIssuedRecord::query()
            ->leftJoin('coupon','physical_store_coupon_issued_record.coupon_id','=','coupon.id')
            ->leftJoin('member','coupon.member_id','=','member.id')
            ->select(['member.mobile','member.name as member_name','coupon.created_at','coupon.is_used'])
            ->where(['physical_store_coupon_issued_record.physical_store_coupon_template_id'=>$physicalStoreCouponTemplateId])
            ->offset($offset)->limit($limit)
            ->get();
        $couponList = $couponList->toArray();
        $count = PhysicalStoreCouponIssuedRecord::query()->where(['physical_store_coupon_template_id'=>$physicalStoreCouponTemplateId])->count();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$couponList,'count'=>$count]];
    }

}