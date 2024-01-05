<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\PhysicalStoreAdmins;
use App\Constants\ErrorCode;
use App\Model\PhysicalStoreAdminsPhysicalStore;
use App\Snowflake\IdGenerator;
use Hyperf\Utils\Context;

class OrganizationService extends BaseService
{
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 添加管理员
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addAdmins(array $params): array
    {
        $storeId = $this->adminsInfo['store_id'];
        $mobile = $params['mobile'];

        $physicalStoreAdminsId = IdGenerator::generate();
        $insertAdminsData['id'] = $physicalStoreAdminsId;
        $insertAdminsData['physical_store_id'] = $storeId;
        $insertAdminsData['name'] = $params['name'];
        $insertAdminsData['mobile'] = $mobile;
        $insertAdminsData['physical_store_admin_permissions_id'] = $params['admin_permissions_id'];

        //门店管理员关联门店数据
        $insertPhysicalStoreAdminsPhysicalStoreData['id'] = IdGenerator::generate();
        $insertPhysicalStoreAdminsPhysicalStoreData['physical_store_admins_id'] = $physicalStoreAdminsId;
        $insertPhysicalStoreAdminsPhysicalStoreData['physical_store_id'] = $storeId;

        PhysicalStoreAdmins::query()->insert($insertAdminsData);
        PhysicalStoreAdminsPhysicalStore::query()->insert($insertPhysicalStoreAdminsPhysicalStoreData);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 编辑管理员
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function editAdmins(array $params): array
    {
        $id = $params['id'];
        $mobile = $params['mobile'];

        $updateAdminsData['name'] = $params['name'];
        $updateAdminsData['mobile'] = $mobile;
        $updateAdminsData['physical_store_admin_permissions_id'] = $params['admin_permissions_id'];

        PhysicalStoreAdmins::query()->where(['id'=>$id])->update($updateAdminsData);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 删除管理员
     * @param int $id
     * @return array
     */
    public function deleteAdmins(int $id): array
    {
        PhysicalStoreAdmins::query()->where(['id'=>$id])->delete();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 管理员列表
     * @param array $params
     * @return array
     */
    public function adminsList(array $params): array
    {
        $mobile = $params['mobile'];
        $storeId = $this->adminsInfo['store_id'];

        $where['physical_store_admins.physical_store_id'] = $storeId;
        if($mobile !== null){
            $where['physical_store_admins.mobile'] = $mobile;
        }
        $adminsList = PhysicalStoreAdmins::query()
            ->leftJoin('physical_store_admin_permissions','physical_store_admins.physical_store_admin_permissions_id','=','physical_store_admin_permissions.id')
            ->select(['physical_store_admins.id','physical_store_admins.name','physical_store_admins.mobile','physical_store_admins.created_at','physical_store_admins.physical_store_admin_permissions_id as admin_permissions_id','physical_store_admin_permissions.name as permissions_name'])
            ->where($where)
            ->get();
        $adminsList = $adminsList->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $adminsList];
    }

    /**
     * 管理员详情
     * @param int $id
     * @return array
     */
    public function adminsDetail(int $id): array
    {
        $storeId = $this->adminsInfo['store_id'];

        $adminsInfo = PhysicalStoreAdmins::query()
            ->select(['id','name','mobile','physical_store_admin_permissions_id as admin_permissions_id'])
            ->where(['id'=>$id,'physical_store_id'=>$storeId])
            ->first();
        if(empty($adminsInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '信息错误', 'data' => null];
        }
        $adminsInfo = $adminsInfo->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $adminsInfo];
    }

}