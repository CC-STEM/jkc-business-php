<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\PhysicalStoreAdminPermissionsRoute;
use App\Model\PhysicalStoreAdmins;
use App\Constants\ErrorCode;
use Hyperf\Utils\Context;

class AdminsService extends BaseService
{
    /**
     * AdminsService
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 管理员信息
     * @return array
     */
    public function adminsInfo(): array
    {
        $adminsId = $this->adminsInfo['admins_id'];
        $name = $this->adminsInfo['name'];
        $storeId = $this->adminsInfo['store_id'];
        $identity = $this->adminsInfo['identity'];

        if($identity == 2){
            $returnData = [
                'id' => (string)$adminsId,
                'name' => $name,
                'permissions' => ['customer_setting'],
                'identity' => $identity
            ];
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
        }
        $adminsInfo = PhysicalStoreAdmins::query()
            ->select(['physical_store_admin_permissions_id','senior_admins'])
            ->where(['id'=>$adminsId])
            ->first();
        $adminsInfo = $adminsInfo->toArray();
        $adminPermissionsId = $adminsInfo['physical_store_admin_permissions_id'];

        $adminsPermissionsList = PhysicalStoreAdminPermissionsRoute::query()
            ->leftJoin('physical_store_admin_route','physical_store_admin_permissions_route.physical_store_admin_route_id','=','physical_store_admin_route.id')
            ->select(['physical_store_admin_route.identify'])
            ->where(['physical_store_admin_permissions_route.physical_store_admin_permissions_id'=>$adminPermissionsId])
            ->get();
        $adminsPermissionsList = $adminsPermissionsList->toArray();
        $adminsPermissionsList = array_column($adminsPermissionsList,'identify');

        $returnData = [
            'id' => (string)$adminsId,
            'name' => $name,
            'senior_admins' => $adminsInfo['senior_admins'],
            'store_id' => (string)$storeId,
            'permissions' => $adminsPermissionsList,
            'identity' => $identity
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

}