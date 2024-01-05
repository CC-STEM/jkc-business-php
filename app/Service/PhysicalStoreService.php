<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\AdminsCache;
use App\Model\PhysicalStore;
use App\Model\PhysicalStoreAdminsPhysicalStore;
use App\Model\Teacher;
use App\Model\Classroom;
use App\Constants\ErrorCode;
use Hyperf\Utils\Context;

class PhysicalStoreService extends BaseService
{

    /**
     * PhysicalStoreService constructor.
     * @throws \RedisException
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 编辑门店
     * @param array $params
     * @return array
     */
    public function editPhysicalStore(array $params): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];

        $updatePhysicalStoreData['wechat_qr_code'] = $params['wechat_qr_code'];
        $updatePhysicalStoreData['store_phone'] = $params['store_phone'];
        PhysicalStore::query()->where(['id'=>$physicalStoreId])->update($updatePhysicalStoreData);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 门店详情
     * @return array
     */
    public function physicalStoreDetail(): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];
        $physicalStoreInfo = PhysicalStore::query()
            ->select(['id','name','province_name','city_name','district_name','address','wechat_qr_code','store_phone'])
            ->where(['id'=>$physicalStoreId])
            ->first();
        if(empty($physicalStoreInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '数据错误', 'data' => null];
        }
        $physicalStoreInfo = $physicalStoreInfo->toArray();

        $classroomCount = Classroom::query()->where(['physical_store_id'=>$physicalStoreId])->count('id');
        $teacherCount = Teacher::query()->where(['physical_store_id'=>$physicalStoreId])->count('id');
        $physicalStoreInfo['classroom_count'] = $classroomCount;
        $physicalStoreInfo['teacher_count'] = $teacherCount;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $physicalStoreInfo];
    }

    /**
     * 管理门店列表
     * @return array
     */
    public function adminsPhysicalStoreList(): array
    {
        $adminsId = $this->adminsInfo['admins_id'];

        $physicalStoreAdminsPhysicalStoreList = PhysicalStoreAdminsPhysicalStore::query()
            ->leftJoin('physical_store','physical_store_admins_physical_store.physical_store_id','=','physical_store.id')
            ->select(['physical_store.id','physical_store.name'])
            ->where(['physical_store_admins_id'=>$adminsId])
            ->get();
        $physicalStoreAdminsPhysicalStoreList = $physicalStoreAdminsPhysicalStoreList->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $physicalStoreAdminsPhysicalStoreList];
    }

    /**
     * 指定门店
     * @param array $params
     * @return array
     * @throws \RedisException
     */
    public function selectedPhysicalStore(array $params): array
    {
        $id = $params['id'];
        $token = $params['token'];

        $adminsCache = new AdminsCache();
        $adminsCache->setAdminsInfoItem(md5($token),'store_id',(string)$id);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }
}