<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\AdminsCache;
use App\Cache\AuthCache;
use App\Lib\QCloud\Sms;
use App\Logger\Log;
use App\Model\PhysicalStoreAdminsPhysicalStore;
use App\Model\Teacher;
use App\Token\Jwt;
use App\Constants\ErrorCode;
use App\Model\PhysicalStoreAdmins;
use Hyperf\Contract\SessionInterface;
use Hyperf\Di\Annotation\Inject;

class AuthService extends BaseService
{
    #[Inject]
    protected SessionInterface $session;

    /**
     * 发送短信验证码
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public function smsCodeSend(array $params)
    {
        $mobile = (int)$params['mobile'];

        $teacherInfo = Teacher::query()
            ->select(['id'])
            ->where(['mobile'=>$mobile,'is_deleted'=>0])
            ->first();
        $physicalStoreAdminsInfo = PhysicalStoreAdmins::query()
            ->select(['id'])
            ->where(['mobile'=>$mobile,'is_deleted'=>0])
            ->first();
        if($physicalStoreAdminsInfo === null && $teacherInfo === null){
            return ['code' => ErrorCode::WARNING, 'msg' => '发送失败', 'data' => null];
        }
        $authCache = new AuthCache();
        $existsSmsCode = $authCache->existsSmsCode($mobile);
        if($existsSmsCode === 1){
            return ['code' => ErrorCode::WARNING, 'msg' => '发送太频繁', 'data' => null];
        }

        mt_srand();
        $code = mt_rand(10000, 99999);
        $sms = new Sms();
        $sms->mobile = [$mobile];
        $sms->templId = 'loginTemplateId';
        $result = $sms->singleSmsSend([$code,2]);
        $rsp = json_decode($result,true);
        if ($rsp['errmsg'] !== 'OK') {
            Log::get()->info("mobile[{$mobile}]:{$result}");
            return ['code' => ErrorCode::WARNING, 'msg' => '验证码发送失败，请稍后重试', 'data' => null];
        }
        $authCache->setSmsCode($mobile,$code);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 手机号登录
     * @param array $params
     * @return array
     * @throws \RedisException
     * @throws \Exception
     */
    public function mobileLogin(array $params): array
    {
        $code = (int)$params['code'];
        $mobile = (int)$params['mobile'];
        $date = date('Y-m-d H:i:s');

        $authCache = new AuthCache();
        $localCode = $authCache->getSmsCode($mobile);
        if($localCode !== $code){
            return ['code' => ErrorCode::WARNING, 'msg' => '验证码错误', 'data' => null];
        }

        $teacherInfo = Teacher::query()
            ->select(['id','name'])
            ->where(['mobile'=>$mobile,'is_deleted'=>0])
            ->first();
        $teacherInfo = $teacherInfo?->toArray();
        $physicalStoreAdminsInfo = PhysicalStoreAdmins::query()
            ->select(['id','name','senior_admins'])
            ->where(['mobile'=>$mobile,'is_deleted'=>0])
            ->first();
        $physicalStoreAdminsInfo = $physicalStoreAdminsInfo?->toArray();
        $physicalStoreAdminsId = $physicalStoreAdminsInfo['id'] ?? 0;
        $teacherId = $teacherInfo['id'] ?? 0;
        if($physicalStoreAdminsId === 0 && $teacherId === 0){
            return ['code' => ErrorCode::WARNING, 'msg' => '账户不存在', 'data' => null];
        }
        $identity = 1;
        if($physicalStoreAdminsId === 0 && $teacherId !== 0){
            $identity = 2;
        }else if($physicalStoreAdminsId !== 0 && $teacherId !== 0){
            $identity = 3;
        }
        $adminsInfo = [
            'admins_id' => $physicalStoreAdminsId,
            'teacher_id' => $teacherId,
            'admins_name' => $physicalStoreAdminsInfo['name'],
            'teacher_name' => $teacherInfo['name'],
            'identity' => $identity
        ];
        if($identity !== 2){
            PhysicalStoreAdmins::where('id', $physicalStoreAdminsId)->update(['last_login_at'=>$date]);
            //管理员门店
            $physicalStoreAdminsPhysicalStoreInfo = PhysicalStoreAdminsPhysicalStore::query()
                ->select(['physical_store_id'])
                ->where(['physical_store_admins_id'=>$physicalStoreAdminsInfo['id']])
                ->first();
            $physicalStoreAdminsPhysicalStoreInfo = $physicalStoreAdminsPhysicalStoreInfo->toArray();
            $adminsInfo['store_id'] = $physicalStoreAdminsPhysicalStoreInfo['physical_store_id'];
        }
        // 生成token
        $jwt = new Jwt();
        $token = $jwt->getToken([]);
        //设置登录信息
        $adminsCache = new AdminsCache();
        $adminsCache->setAdminsInfo(md5($token),$adminsInfo);
        $this->session->set('token',$token);

        $returnData = [
            'identity' => $identity
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 指定管理员身份
     * @param array $params
     * @return array
     * @throws \RedisException
     */
    public function selectedAdminsIdentity(array $params): array
    {
        $identity = $params['identity'];
        $token = $params['token'];
        if(empty($token)){
            return ['code' => ErrorCode::WARNING, 'msg' => '登录失败', 'data' => null];
        }

        $adminsCache = new AdminsCache();
        $adminsCache->setAdminsInfoItem(md5($token),'identity',(string)$identity);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 退出登录
     * @return array
     */
    public function loginOut(): array
    {
        $this->session->clear();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

}

