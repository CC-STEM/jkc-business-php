<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\MemberBelongTo;
use App\Model\Teacher;
use App\Constants\ErrorCode;
use App\Snowflake\IdGenerator;
use Hyperf\Utils\Context;

class TeacherService extends BaseService
{

    /**
     * @throws \RedisException
     */
    public function __construct()
    {
        $this->adminsInfo = Context::get('AdminsInfo');
    }

    /**
     * 添加老师
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function addTeacher(array $params): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];
        $mobile = $params['mobile'];
        $identity = $params['identity'];

        $teacherExists = Teacher::query()->where(['mobile'=>$mobile,'is_deleted'=>0])->exists();
        if($teacherExists === true){
            return ['code' => ErrorCode::WARNING, 'msg' => '手机号已存在', 'data' => null];
        }
        $insertTeacherData['id'] = IdGenerator::generate();
        $insertTeacherData['physical_store_id'] = $physicalStoreId;
        $insertTeacherData['name'] = $params['name'];
        $insertTeacherData['mobile'] = $mobile;
        $insertTeacherData['identity'] = $identity;

        Teacher::query()->insert($insertTeacherData);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 编辑老师
     * @param array $params
     * @return array
     */
    public function editTeacher(array $params): array
    {
        $id = $params['id'];
        $physicalStoreId = $this->adminsInfo['store_id'];
        $mobile = $params['mobile'];
        $identity = $params['identity'];

        $teacherExists = Teacher::query()->where([['mobile','=',$mobile],['id','<>',$id],['is_deleted','=',0]])->exists();
        if($teacherExists === true){
            return ['code' => ErrorCode::WARNING, 'msg' => '手机号已存在', 'data' => null];
        }
        $updateTeacherData['name'] = $params['name'];
        $updateTeacherData['mobile'] = $mobile;
        $updateTeacherData['identity'] = $identity;
        Teacher::query()->where(['id'=>$id,'physical_store_id'=>$physicalStoreId])->update($updateTeacherData);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 老师列表
     * @param array $params
     * @return array
     */
    public function teacherList(array $params): array
    {
        $type = $params['type'];
        $mobile = $params['mobile'];
        $physicalStoreId = $this->adminsInfo['store_id'];

        $where = ['physical_store_id'=>$physicalStoreId,'is_deleted'=>0];
        if($mobile !== null){
            $where['mobile'] = $mobile;
        }
        if($type == 1){
            $where['identity'] = 1;
        }
        $teacherList = Teacher::query()
            ->select(['id','name','mobile','created_at','identity'])
            ->where($where)
            ->get();
        $teacherList = $teacherList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $teacherList];
    }

    /**
     * 老师详情
     * @param int $id
     * @return array
     */
    public function teacherDetail(int $id): array
    {
        $teacherInfo = Teacher::query()->select(['id','name','mobile','identity'])->where(['id'=>$id])->first();
        if(empty($teacherInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '数据错误', 'data' => null];
        }
        $teacherInfo = $teacherInfo->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $teacherInfo];
    }

    /**
     * 删除老师
     * @param int $id
     * @return array
     */
    public function deleteTeacher(int $id): array
    {
        $physicalStoreId = $this->adminsInfo['store_id'];
        $memberBelongToExists = MemberBelongTo::query()->where(['teacher_id'=>$id])->exists();
        if($memberBelongToExists === true){
            return ['code' => ErrorCode::WARNING, 'msg' => '老师暂时无法删除', 'data' => null];
        }

        Teacher::query()->where(['id'=>$id,'physical_store_id'=>$physicalStoreId])->update(['is_deleted'=>1]);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 老师搜索列表
     * @param array $params
     * @return array
     */
    public function teacherSearchList(array $params): array
    {
        $mobile = $params['mobile'];
        $physicalStoreId = $this->adminsInfo['store_id'];
        $offset = $this->offset;
        $limit = $this->limit;

        $where = ['physical_store_id'=>$physicalStoreId,'is_deleted'=>0];
        if($mobile !== null){
            $where['mobile'] = $mobile;
        }
        $teacherList = Teacher::query()
            ->select(['id','name','mobile','created_at','identity'])
            ->where($where)
            ->get();
        $teacherList = $teacherList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $teacherList];
    }

}