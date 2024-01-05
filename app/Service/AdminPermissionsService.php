<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\PhysicalStoreAdminPermissions;
use App\Model\PhysicalStoreAdminPermissionsRoute;
use App\Model\PhysicalStoreAdminRoute;
use App\Snowflake\IdGenerator;
use App\Constants\ErrorCode;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;

class AdminPermissionsService extends BaseService
{
    /**
     * @throws \RedisException
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 添加权限
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addAdminPermissions(array $params): array
    {
        $adminRoute = $params['admin_route_id'];
        $storeId = $this->adminsInfo['store_id'];
        //管理后台权限数据
        $adminPermissionsId = IdGenerator::generate();
        $insertAdminPermissionsData['id'] = $adminPermissionsId;
        $insertAdminPermissionsData['physical_store_id'] = $storeId;
        $insertAdminPermissionsData['name'] = $params['name'];
        //管理后台权限路由数据
        $insertAdminPermissionsRouteData = [];
        foreach($adminRoute as $value){
            $adminPermissionsRouteData = [];
            $adminPermissionsRouteData['id'] = IdGenerator::generate();
            $adminPermissionsRouteData['physical_store_admin_permissions_id'] = $adminPermissionsId;
            $adminPermissionsRouteData['physical_store_admin_route_id'] = $value;
            $insertAdminPermissionsRouteData[] = $adminPermissionsRouteData;
        }

        PhysicalStoreAdminPermissions::query()->insert($insertAdminPermissionsData);
        PhysicalStoreAdminPermissionsRoute::query()->insert($insertAdminPermissionsRouteData);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 编辑权限
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function editAdminPermissions(array $params): array
    {
        $id = $params['id'];
        $adminRoute = $params['admin_route_id'];

        //管理后台权限数据
        $updateAdminPermissionsData['name'] = $params['name'];
        //管理后台权限路由数据
        $insertAdminPermissionsRouteData = [];
        foreach($adminRoute as $value){
            $adminPermissionsRouteData = [];
            $adminPermissionsRouteData['id'] = IdGenerator::generate();
            $adminPermissionsRouteData['physical_store_admin_permissions_id'] = $id;
            $adminPermissionsRouteData['physical_store_admin_route_id'] = $value;
            $insertAdminPermissionsRouteData[] = $adminPermissionsRouteData;
        }

        Db::connection('jkc_edu')->beginTransaction();
        try{
            Db::connection('jkc_edu')->table('physical_store_admin_permissions_route')->where(['physical_store_admin_permissions_id'=>$id])->delete();
            Db::connection('jkc_edu')->table('physical_store_admin_permissions')->where(['id'=>$id])->update($updateAdminPermissionsData);
            Db::connection('jkc_edu')->table('physical_store_admin_permissions_route')->insert($insertAdminPermissionsRouteData);
            Db::connection('jkc_edu')->commit();
        } catch(\Throwable $e){
            Db::connection('jkc_edu')->rollBack();
            throw new \Exception($e->getMessage(), 1);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 删除权限
     * @param int $id
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function deleteAdminPermissions(int $id): array
    {
        $storeId = $this->adminsInfo['store_id'];

        PhysicalStoreAdminPermissions::query()->where(['id'=>$id,'physical_store_id'=>$storeId])->delete();
        PhysicalStoreAdminPermissionsRoute::query()->where(['physical_store_admin_permissions_id'=>$id])->delete();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 权限列表
     * @return array
     */
    public function adminPermissionsList(): array
    {
        $storeId = $this->adminsInfo['store_id'];

        $adminPermissionsList = PhysicalStoreAdminPermissions::query()
            ->select(['id','name','created_at'])
            ->where(['physical_store_id'=>$storeId])
            ->get();
        $adminPermissionsList = $adminPermissionsList->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $adminPermissionsList];
    }

    /**
     * 权限详情
     * @param int $id
     * @return array
     */
    public function adminPermissionsDetail(int $id): array
    {
        $adminPermissionsInfo = PhysicalStoreAdminPermissions::query()->select(['id','name'])->where(['id'=>$id])->first();
        if(empty($adminPermissionsInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '信息错误', 'data' => null];
        }
        $adminPermissionsInfo = $adminPermissionsInfo->toArray();
        $adminPermissionsId = $adminPermissionsInfo['id'];

        $adminPermissionsRouteList = PhysicalStoreAdminPermissionsRoute::query()->select(['physical_store_admin_route_id'])->where(['physical_store_admin_permissions_id'=>$adminPermissionsId])->get();
        $adminPermissionsRouteList = $adminPermissionsRouteList->toArray();
        $adminPermissionsRouteList = array_column($adminPermissionsRouteList,'physical_store_admin_route_id');

        $adminPermissionsInfo['route'] = $adminPermissionsRouteList;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $adminPermissionsInfo];
    }

    /**
     * 路由列表
     * @return array
     */
    public function adminRouteList(): array
    {
        $adminRouteList = PhysicalStoreAdminRoute::query()->select(['id','parent_id','name','identify','path'])->get();
        $adminRouteList = $adminRouteList->toArray();
        $adminRouteListGroup = $this->functions->arrayGroupBy($adminRouteList,'parent_id');
        $parentAdminRoute = $adminRouteListGroup['0'];

        foreach($parentAdminRoute as $key=>$value){
            $id = $value['id'];
            $child = $adminRouteListGroup[$id] ?? [];
            unset($parentAdminRoute[$key]['parent_id']);
            $parentAdminRoute[$key]['child'] = $child;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $parentAdminRoute];
    }
}