<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Classroom;
use App\Constants\ErrorCode;
use App\Snowflake\IdGenerator;
use Hyperf\Utils\Context;

class ClassroomService extends BaseService
{
    /**
     * ClassroomService constructor.
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 添加教室
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addClassroom(array $params): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];

        $insertClassroomData['id'] = IdGenerator::generate();
        $insertClassroomData['physical_store_id'] = $physicalStoreId;
        $insertClassroomData['name'] = $params['name'];
        $insertClassroomData['capacity'] = $params['capacity'];
        Classroom::query()->insert($insertClassroomData);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 编辑教室
     * @param array $params
     * @return array
     */
    public function editClassroom(array $params): array
    {
        $id = $params['id'];
        $physicalStoreId = $this->adminsInfo['store_id'];

        $updateClassroomData['name'] = $params['name'];
        $updateClassroomData['capacity'] = $params['capacity'];
        Classroom::query()->where(['id'=>$id,'physical_store_id'=>$physicalStoreId])->update($updateClassroomData);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 教室列表
     * @return array
     */
    public function classroomList(): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];

        $classroomList = Classroom::query()
            ->select(['id','name','capacity','created_at'])
            ->where(['physical_store_id'=>$physicalStoreId])
            ->get();
        $classroomList = $classroomList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $classroomList];
    }

    /**
     * 教室详情
     * @param int $id
     * @return array
     */
    public function classroomDetail(int $id): array
    {
        $classroomInfo = Classroom::query()->select(['id','name','capacity'])->where(['id'=>$id])->first();
        if(empty($classroomInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '数据错误', 'data' => null];
        }
        $classroomInfo = $classroomInfo->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $classroomInfo];
    }

    /**
     * 删除教室
     * @param int $id
     * @return array
     */
    public function deleteClassroom(int $id): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];

        Classroom::query()->where(['id'=>$id,'physical_store_id'=>$physicalStoreId])->delete();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }
}