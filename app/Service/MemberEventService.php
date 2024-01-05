<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Constants\VipCardConstant;
use App\Event\MemberEventSwitchRegistered;
use App\Model\CourseOfflineOrder;
use App\Model\Member;
use App\Model\MemberBelongTo;
use App\Model\MemberEvent;
use App\Model\MemberFollowupBlacklist;
use App\Model\MemberEventComplete;
use App\Model\MemberEventCompleteAutoHandleJudgmentCriteria;
use App\Model\MemberEventCompleteFollowup;
use App\Model\MemberEventFeedbackDefine;
use App\Model\MemberFollowupNote;
use App\Model\PhysicalStore;
use App\Model\Teacher;
use App\Model\VipCardOrder;
use App\Model\VipCardOrderDynamicCourse;
use App\Snowflake\IdGenerator;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;
use Hyperf\Di\Annotation\Inject;
use Psr\EventDispatcher\EventDispatcherInterface;
use App\Logger\Log;

class MemberEventService extends BaseService
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
     * 所有待处理事项列表
     * @param array $query
     * @return array
     */
    public function allMemberEventList(array $query): array
    {
        $memberEventModel = MemberEvent::query();

        $fields = ['id', 'name'];
        $memberEventList = $memberEventModel->select($fields)
            ->orderByDesc('id')
            ->get()
            ->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $memberEventList];
    }


    /**
     * 客户管理table栏列表
     * @param
     * @return array
     */
    public function customerTableList($query): array
    {
        $identity = $this->adminsInfo['identity'];

        $query['only_need_count'] = 1;

        $data[] = [
            'count' => $this->customerList(array_merge($query, ['table_type' => 1]))['data']['count'] ?? 0,
            'table_type' => 1,
            'text' => '所有客户',
        ];
        if ($identity == 1) {
            $data[] = [
                'count' => $this->customerList(array_merge($query, ['table_type' => 2]))['data']['count'] ?? 0,
                'table_type' => 2,
                'text' => '待分配',
            ];
        }
        $data[] = [
            'count' => $this->customerList(array_merge($query, ['table_type' => 3]))['data']['count'] ?? 0,
            'table_type' => 3,
            'text' => '有新人礼包次数',
        ];
        $data[] = [
            'count' => $this->customerList(array_merge($query, ['table_type' => 4]))['data']['count'] ?? 0,
            'table_type' => 4,
            'text' => '有会员卡次数',
        ];
        $data[] = [
            'count' => $this->customerList(array_merge($query, ['table_type' => 5]))['data']['count'] ?? 0,
            'table_type' => 5,
            'text' => '3天内有过期次数',
        ];
        $data[] = [
            'count' => $this->customerList(array_merge($query, ['table_type' => 6]))['data']['count'] ?? 0,
            'table_type' => 6,
            'text' => '已付费但课次无',
        ];
        $data[] = [
            'count' => $this->customerList(array_merge($query, ['table_type' => 7]))['data']['count'] ?? 0,
            'table_type' => 7,
            'text' => '待处理事项',
        ];
        $data[] = [
            'count' => $this->customerList(array_merge($query, ['table_type' => 8]))['data']['count'] ?? 0,
            'table_type' => 8,
            'text' => '已处理',
        ];
        $data[] = [
            'count' => $this->customerList(array_merge($query, ['table_type' => 9]))['data']['count'] ?? 0,
            'table_type' => 9,
            'text' => '废弃池',
        ];

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $data];
    }


    /**
     * 客户管理列表
     * @param array $query
     * @return array
     */
    public function customerList(array $query): array
    {
        $storeId = $this->adminsInfo['store_id'] ?? 0;
        $teacherId = $this->adminsInfo['admins_id'];
        $identity = $this->adminsInfo['identity'];

        $tableType = $query['table_type'];

        $offset = $this->offset;
        $limit = $this->limit;

        $statistics = null;

        $where = 'WHERE 1=1';
        $bindingArray = [];
        if ($storeId) {
            $where .= ' AND c.physical_store_id=?';
            $bindingArray[] = $storeId;
        }
        if (!empty($query['mobile'])) {
            $where .= ' AND a.mobile=?';
            $bindingArray[] = $query['mobile'];
        }
        if ($identity == 2) {
            $where .= ' AND c.teacher_id=?';
            $bindingArray[] = $teacherId;
        } else if (!empty($query['teacher_id'])) {
            $where .= ' AND c.teacher_id=?';
            $bindingArray[] = $query['teacher_id'];
        }
        if (!empty($query['date'])) {
            $where .= ' AND b.created_at BETWEEN ? AND ?';
            [$startDate, $endDate] = explode('|', $query['date']);
            $bindingArray[] = $startDate . ' 00:00:00';
            $bindingArray[] = $endDate . ' 23:59:59';
        }
        if (!empty($query['member_name'])) {
            $where .= " AND a.name like ?";
            $bindingArray[] = "%{$query['member_name']}%";
        }
        $memberEventPlaceholderStr = '';
        if (!empty($query['member_event'])) {
            $placeholder = [];
            foreach ($query['member_event'] as $_memberEventId) {
                $placeholder[] = '?';
            }
            $memberEventPlaceholderStr = implode(',',$placeholder);
        }

        $fieldsStr = "a.id,a.mobile,a.`name` AS member_name,b.created_at,b.member_event_id,b.id AS member_event_complete_id,b.`name` AS member_event_name,c.teacher_id,c.physical_store_id";
        $orderBy = " ORDER BY b.created_at DESC";
        $groupBy = "";
        $having = "";

        if (in_array($tableType, [2, 3, 4, 5, 6, 7, 8, 9])) {
            if (!empty($query['member_event'])) {
                $bindingArray = array_merge($bindingArray, $query['member_event']);
                $where .= " AND b.member_event_id IN({$memberEventPlaceholderStr})";
            }

            $groupBy = " GROUP BY a.id";
        }

        $nowDate = date('Y-m-d H:i:s');
        switch ($tableType) {
            case 1:
                $sql = "SELECT {$fieldsStr} FROM member a LEFT JOIN (SELECT * FROM member_event_complete WHERE id IN(SELECT MAX(id) FROM member_event_complete GROUP BY member_id)) b ON a.id=b.member_id LEFT JOIN member_belong_to c ON a.id=c.member_id";
                if (!empty($query['member_event'])) {
                    $bindingArray = array_merge($query['member_event'], $bindingArray);
                    $_where = " WHERE member_event_id IN({$memberEventPlaceholderStr})";

                    $sql = "SELECT {$fieldsStr} FROM member a INNER JOIN (SELECT * FROM member_event_complete WHERE id IN(SELECT MAX(id) FROM member_event_complete {$_where} GROUP BY member_id)) b ON a.id=b.member_id LEFT JOIN member_belong_to c ON a.id=c.member_id";
                }

                break;
            case 2:
                $fieldsStr = "a.id,a.mobile,a.`name` AS member_name,d.created_at,d.member_event_id,d.id AS member_event_complete_id,d.`name` AS member_event_name,c.teacher_id,c.physical_store_id";
                $sql = "SELECT {$fieldsStr} FROM member a LEFT JOIN member_event_complete b ON a.id=b.member_id LEFT JOIN member_belong_to c ON a.id=c.member_id LEFT JOIN (SELECT * FROM member_event_complete WHERE id IN(SELECT MAX(id) FROM member_event_complete WHERE built_in_event_type IN(1000,1006) GROUP BY member_id)) d ON a.id=d.member_id";
                $orderBy = " ORDER BY d.created_at DESC";

                $where .= ' AND (c.teacher_id IS NULL OR c.teacher_id=0 OR c.physical_store_id IS NULL OR c.physical_store_id=0)';

                break;
            case 3:
                $sql = "SELECT {$fieldsStr} FROM member a LEFT JOIN member_event_complete b ON a.id=b.member_id LEFT JOIN member_belong_to c ON a.id=c.member_id LEFT JOIN vip_card_order d ON (a.id=d.member_id AND d.order_type=2 AND d.pay_status=1 AND d.order_status=0)";
                $having = ' HAVING SUM(d.course1+d.course2+d.course3+d.currency_course-d.course1_used-d.course2_used-d.course3_used-d.currency_course_used)>0';

                break;
            case 4:
                $sql = "SELECT {$fieldsStr} FROM member a LEFT JOIN member_event_complete b ON a.id=b.member_id LEFT JOIN member_belong_to c ON a.id=c.member_id LEFT JOIN vip_card_order d ON (a.id=d.member_id AND d.pay_status=1 AND d.order_status=0 AND d.expire_at>'{$nowDate}')";
                $having = ' HAVING SUM(d.course1+d.course2+d.course3+d.currency_course-d.course1_used-d.course2_used-d.course3_used-d.currency_course_used)>0';

                break;
            case 5:
                $after3Date = date('Y-m-d H:i:s', time() + 86400 * 3);
                $sql = "SELECT {$fieldsStr} FROM member a LEFT JOIN member_event_complete b ON a.id=b.member_id LEFT JOIN member_belong_to c ON a.id=c.member_id INNER JOIN vip_card_order d ON (a.id=d.member_id AND d.pay_status=1 AND d.order_status=0 AND d.expire_at BETWEEN '{$nowDate}' AND '{$after3Date}')";
                $having = " HAVING SUM(d.course1+d.course2+d.course3+d.currency_course-d.course1_used-d.course2_used-d.course3_used-d.currency_course_used)>0";

                break;
            case 6:
                $sql = "SELECT {$fieldsStr} FROM member a LEFT JOIN member_event_complete b ON a.id=b.member_id LEFT JOIN member_belong_to c ON a.id=c.member_id INNER JOIN vip_card_order d ON (a.id=d.member_id AND d.pay_status=1 AND d.order_status=0 AND d.expire_at>'{$nowDate}')";
                $having = ' HAVING SUM(d.course1+d.course2+d.course3+d.currency_course-d.course1_used-d.course2_used-d.course3_used-d.currency_course_used)=0 AND SUM(d.course1_used-d.course2_used-d.course3_used-d.currency_course_used)>0';

                break;
            case 7:
                $sql = "SELECT {$fieldsStr} FROM member_event_complete b LEFT JOIN member a ON a.id=b.member_id LEFT JOIN member_belong_to c ON a.id=c.member_id";
                $where .= ' AND b.member_event_id>0 AND b.handle_status=0';
                $groupBy = '';

                break;
            case 8:
                $sql = "SELECT {$fieldsStr} FROM member_event_complete b LEFT JOIN member a ON a.id=b.member_id LEFT JOIN member_belong_to c ON a.id=c.member_id";
                $where .= ' AND b.member_event_id>0 AND b.handle_status=1';
                $groupBy = '';
                $orderBy = " ORDER BY b.updated_at DESC";

                break;
            case 9:
                $fieldsStr = "a.id,a.mobile,d.created_at,c.teacher_id,c.physical_store_id,a.name AS member_name,'放弃跟进' AS member_event_name";
                $sql = "SELECT {$fieldsStr} FROM member a LEFT JOIN member_event_complete b ON a.id=b.member_id LEFT JOIN member_belong_to c ON a.id=c.member_id INNER JOIN member_followup_blacklist d ON a.id=d.member_id";
                $orderBy = " ORDER BY d.created_at DESC";

                break;
            default:
                return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list' => [], 'count' => 0, 'statistics' => $statistics]];
        }

        $countSql = "SELECT COUNT(1) counts FROM ({$sql} {$where}{$groupBy}{$having}) counts";
        $sql .= " {$where}{$groupBy}{$having}{$orderBy} LIMIT {$offset},{$limit}";
        $count = Db::connection('jkc_edu')->select($countSql, $bindingArray);
        $count = $count[0]['counts'] ?? 0;

        if (isset($query['only_need_count'])) {
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list' => [], 'count' => $count]];
        }


        if ($tableType == 8) {
            $countModel = MemberEventComplete::query()
                ->leftJoin('member', 'member_event_complete.member_id', '=', 'member.id')
                ->leftJoin('member_belong_to', 'member_event_complete.member_id', '=', 'member_belong_to.member_id')
                ->leftJoin('member_event_complete_followup', 'member_event_complete.id', '=', 'member_event_complete_followup.member_event_complete_id')
                ->where('member_event_complete.is_built_in', 0)
                ->where('member_belong_to.physical_store_id', $storeId);
            if (!empty($query['mobile'])) {
                $countModel->where('member.mobile', $query['mobile']);
            }
            if ($teacherId != 0) {
                $countModel->where('member_belong_to.teacher_id', $teacherId);
            } else if (!empty($query['teacher_id'])) {
                $countModel->where('member_belong_to.teacher_id', $query['teacher_id']);
            }
            if (!empty($query['date'])) {
                [$startDate, $endDate] = explode('|', $query['date']);
                $countModel->whereBetween('member_event_complete.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
            if (!empty($query['member_event'])) {
                $countModel->whereIn('member_event_complete.member_event_id', $query['member_event']);
            }

            $triggerModel = clone $countModel;
            $systemCompleteModel = clone $countModel;
            $systemUnCompleteModel = clone $countModel;
            $artificialCompleteModel = clone $countModel;
            $artificialUnCompleteModel = clone $countModel;

            $trigger = $triggerModel->count();
            $systemComplete = $systemCompleteModel
                ->where(['member_event_complete.is_need_feedback' => 1, 'member_event_complete.handle_type' => 1, 'member_event_complete_followup.result' => 1])
                ->where('member_event_complete.handle_status', 1)
                ->count();
            $systemUnComplete = $systemUnCompleteModel
                ->where(['member_event_complete.is_need_feedback' => 1, 'member_event_complete.handle_type' => 1, 'member_event_complete_followup.result' => 0])
                ->where('member_event_complete.handle_status', 1)
                ->count();
            $artificialComplete = $artificialCompleteModel
                ->where(['member_event_complete.is_need_feedback' => 1, 'member_event_complete.handle_type' => 2, 'member_event_complete_followup.result' => 1])
                ->where('member_event_complete.handle_status', 1)
                ->count();
            $artificialUnComplete = $artificialUnCompleteModel
                ->where(['member_event_complete.is_need_feedback' => 1, 'member_event_complete.handle_type' => 2, 'member_event_complete_followup.result' => 0])
                ->where('member_event_complete.handle_status', 1)
                ->count();

            $statistics = [
                'trigger' => $trigger,
                'system_complete' => $systemComplete,
                'system_uncompleted' => $systemUnComplete,
                'artificial_complete' => $artificialComplete,
                'artificial_uncompleted' => $artificialUnComplete,
            ];
        }


        $memberList = Db::connection('jkc_edu')->select($sql, $bindingArray);

        $nowDate = date('Y-m-d H:i:s');
        $nowTimestamp = time();

        $physicalStoreList = PhysicalStore::query()->whereIn('id', array_column($memberList, 'physical_store_id'))->get(['id', 'name'])->toArray();
        $physicalStoreList = array_column($physicalStoreList, 'name', 'id');

        $teacherList = Teacher::query()->whereIn('id', array_column($memberList, 'teacher_id'))->get(['id', 'name'])->toArray();
        $teacherList = array_column($teacherList, 'name', 'id');

        $blackList = MemberFollowupBlacklist::query()->whereIn('member_id', array_column($memberList, 'id'))->get('member_id')->toArray();
        $blackListMemberIdArray = [];
        if ($blackList) {
            $blackListMemberIdArray = array_column($blackList, 'member_id');
        }
        foreach ($memberList as $key => &$value) {
            $vipCardOrderList = VipCardOrder::query()
                ->select(['id', 'course1', 'course1_used', 'course2', 'course2_used', 'course3', 'course3_used', 'currency_course', 'currency_course_used', 'expire_at', 'order_status'])
                ->where(['member_id' => $value['id'], 'pay_status' => 1])
                ->get()
                ->toArray();

            $surplusCourse = 0;
            foreach($vipCardOrderList as $_vipCardOrderInfo){
                if ($_vipCardOrderInfo['expire_at'] > $nowDate && $_vipCardOrderInfo['order_status'] == 0) {
                    $surplusCourse += $_vipCardOrderInfo['course1'] + $_vipCardOrderInfo['course2'] + $_vipCardOrderInfo['course3'] + $_vipCardOrderInfo['currency_course'];
                    $surplusCourse -= $_vipCardOrderInfo['course1_used'] + $_vipCardOrderInfo['course2_used'] + $_vipCardOrderInfo['course3_used'] + $_vipCardOrderInfo['currency_course_used'];
                }
            }

            $apply = CourseOfflineOrder::query()
                ->leftJoin('course_offline_plan', 'course_offline_order.course_offline_plan_id', '=', 'course_offline_plan.id')
                ->where(['course_offline_order.member_id' => $value['id'], 'course_offline_order.pay_status' => 1, 'course_offline_order.order_status' => 0])
                ->where('course_offline_plan.class_start_time', '>', $nowTimestamp)
                ->count();

            $attendClass = CourseOfflineOrder::query()
                ->where(['member_id' => $value['id'], 'pay_status' => 1, 'order_status' => 0, 'class_status' => 1])
                ->count();

            $teacherName = $teacherList[$value['teacher_id']] ?? '未分配';
            $storeName = $physicalStoreList[$value['physical_store_id']] ?? '未分配';

            $memberEventId = $value['member_event_id'] ?? '0';
            $memberEventCompleteId = $value['member_event_complete_id'] ?? '0';
            $memberEventName = $value['member_event_name'] ?: '-';

            if (!empty($value['created_at'])) {
                $createdAt = date('Y.m.d H:i', strtotime($value['created_at']));
            } else {
                $createdAt = '-';
            }

            switch ($tableType) {
                case 3:
                    $memberEventName = '购买了新人礼包';
                    break;
                case 4:
                    $memberEventName = '购买了会员卡';
                    break;
                case 5:
                    $memberEventName = '还有3天即将过期';
                    break;
                case 6:
                    $memberEventName = '报名次数用完';
                    break;
                case 9:
                    $memberEventName = '放弃跟进';
                    break;
            }

            
            $value['created_at'] = $createdAt;
            $value['member_event_name'] = $memberEventName;
            $value['surplus_course'] = $surplusCourse;
            $value['apply_course'] = $apply;
            $value['attend_course'] = $attendClass;
            $value['teacher_name'] = $teacherName;
            $value['store_name'] = $storeName;
            $value['id'] = (string)$value['id'];
            $value['teacher_id'] = empty($value['teacher_id']) ? '' : (string)$value['teacher_id'];
            $value['physical_store_id'] = empty($value['physical_store_id']) ? '' : (string)$value['physical_store_id'];
            $value['member_event_id'] = (string)$memberEventId;
            $value['member_event_complete_id'] = (string)$memberEventCompleteId;
            $value['is_blacklist'] = in_array($value['id'], $blackListMemberIdArray) === true ? 1 : 0;
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list' => $memberList, 'count' => $count, 'statistics' => $statistics]];
    }

    /**
     * 会员归属分配
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function allocationMemberBelongTo(array $params): array
    {
        $memberId = $params['id'];
        $teacherId = $params['teacher_id'];
        $physicalStoreId = $this->adminsInfo['store_id'];

        $teacherMemberId = 0;
        if ($teacherId > 0) {
            $teacherInfo = Teacher::query()
                ->select(['mobile'])
                ->where(['id'=>$teacherId])
                ->first();
            $teacherInfo = $teacherInfo->toArray();

            $memberInfo = Member::query()
                ->select(['id'])
                ->where(['mobile'=>$teacherInfo['mobile']])
                ->first();
            $memberInfo = $memberInfo?->toArray();
            $teacherMemberId = $memberInfo['id'] ?? 0;
        }

        $memberBelongToExists = MemberBelongTo::query()->where(['member_id'=>$memberId])->exists();
        if($memberBelongToExists === false){
            $insertMemberBelongToData['id'] = IdGenerator::generate();
            $insertMemberBelongToData['member_id'] = $memberId;
            $insertMemberBelongToData['physical_store_id'] = $physicalStoreId;
            $insertMemberBelongToData['teacher_id'] = $teacherId;
            $insertMemberBelongToData['teacher_member_id'] = $teacherMemberId;
            MemberBelongTo::query()->insert($insertMemberBelongToData);
        }else{
            $updateMemberBelongToData['teacher_id'] = $teacherId;
            $updateMemberBelongToData['teacher_member_id'] = $teacherMemberId;

            MemberBelongTo::query()->where(['member_id'=>$memberId])->update($updateMemberBelongToData);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 完成事件跟进
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function completeEventFollowup(array $params): array
    {
        $id = $params['id'];
        $img = $params['img'] ?? '';
        $describe = $params['describe'] ?? '';
        $memberEventFeedbackDefineId = $params['member_event_feedback_define_id'] ?? 0;

        $memberEventCompleteInfo = MemberEventComplete::query()
            ->select(['member_id', 'handle_status', 'is_need_feedback'])
            ->where(['id'=>$id])
            ->first();
        if (empty($memberEventCompleteInfo)) {
            return ['code' => ErrorCode::WARNING, 'msg' => '传入的id有误： ' . $id, 'data' => null];
        }
        $memberEventCompleteInfo = $memberEventCompleteInfo->toArray();

        $memberBelongToInfo = MemberBelongTo::query()
            ->select(['teacher_id','physical_store_id'])
            ->where(['member_id'=>$memberEventCompleteInfo['member_id']])
            ->first();
        if(empty($memberBelongToInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '用户未分配老师', 'data' => null];
        }
        $memberBelongToInfo = $memberBelongToInfo->toArray();

        if($memberEventFeedbackDefineId != 0){
            $memberEventFeedbackDefineInfo = MemberEventFeedbackDefine::query()
                ->select(['name','result','describe'])
                ->where(['id'=>$memberEventFeedbackDefineId])
                ->first();
            $memberEventFeedbackDefineInfo = $memberEventFeedbackDefineInfo->toArray();
            $describe = $memberEventFeedbackDefineInfo['describe'];
        }
        $insertMemberEventCompleteTrackData['id'] = IdGenerator::generate();
        $insertMemberEventCompleteTrackData['member_event_complete_id'] = $id;
        $insertMemberEventCompleteTrackData['teacher_id'] = $memberBelongToInfo['teacher_id'];
        $insertMemberEventCompleteTrackData['physical_store_id'] = $memberBelongToInfo['physical_store_id'];
        $insertMemberEventCompleteTrackData['name'] = $memberEventFeedbackDefineInfo['name'] ?? '';
        $insertMemberEventCompleteTrackData['img'] = $img;
        $insertMemberEventCompleteTrackData['result'] = $memberEventFeedbackDefineInfo['result'] ?? '';
        $insertMemberEventCompleteTrackData['describe'] = $describe;

        $db = Db::connection('jkc_edu');
        $db->beginTransaction();
        try {
            MemberEventCompleteFollowup::query()->insert($insertMemberEventCompleteTrackData);
            MemberEventComplete::query()->where(['id'=>$id,'handle_status'=>0])->update(['handle_status'=>1]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw new \Exception($e->getMessage(), 1);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 更新进度
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function memberFollowupNote(array $params): array
    {
        $memberId = $params['member_id'];
        $img = $params['img'] ?? '';
        $describe = $params['describe'] ?? '';

        $memberBelongToInfo = MemberBelongTo::query()
            ->select(['teacher_id','physical_store_id'])
            ->where(['member_id'=>$memberId])
            ->first();
        if(empty($memberBelongToInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '用户未分配老师', 'data' => null];
        }
        $memberBelongToInfo = $memberBelongToInfo->toArray();

        $insertMemberFollowupNote['id'] = IdGenerator::generate();
        $insertMemberFollowupNote['member_id'] = $memberId;
        $insertMemberFollowupNote['teacher_id'] = $memberBelongToInfo['teacher_id'];
        $insertMemberFollowupNote['physical_store_id'] = $memberBelongToInfo['physical_store_id'];
        $insertMemberFollowupNote['img'] = $img;
        $insertMemberFollowupNote['describe'] = $describe;

        MemberFollowupNote::query()->insert($insertMemberFollowupNote);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 完成事件详情
     * @param int $id
     * @return array
     */
    public function memberEventCompleteDetail(int $id): array
    {
        $memberEventCompleteInfo = MemberEventComplete::query()
            ->select(['name','is_need_feedback','qualified_deadline_at','describe','handle_type','handle_status','member_event_id', 'auto_handle_judgment_type'])
            ->where(['id'=>$id])
            ->first();
        $memberEventCompleteInfo = $memberEventCompleteInfo->toArray();

        $memberEventCompleteFollowupInfo = MemberEventCompleteFollowup::query()
            ->select(['name','img','result','describe'])
            ->where(['member_event_complete_id'=>$id])
            ->first();
        $memberEventCompleteFollowupInfo = $memberEventCompleteFollowupInfo?->toArray();

        $memberEventCompleteFeedbackDefineList = [];
        $completeItem = [];
        $completeItemStr = '';
        if($memberEventCompleteInfo['is_need_feedback'] == 1){
            if($memberEventCompleteInfo['handle_type'] == 1){
                $completeItem = MemberEventCompleteAutoHandleJudgmentCriteria::query()
                    ->select(['id','name','is_complete as result'])
                    ->where(['member_event_complete_id'=>$id])
                    ->get();
                $completeItem = $completeItem->toArray();

                if ($memberEventCompleteFollowupInfo['result'] == 1) {
                    $separator = $memberEventCompleteInfo['auto_handle_judgment_type'] == 1 ? ' + ' : ' / ';
                    $completeItemStr = implode($separator, array_column($completeItem, 'name'));
                } else {
                    $completeItemStr = '未完成';
                }
            }else{
                $completeItem = MemberEventFeedbackDefine::query()
                    ->select(['id','name','result','describe'])
                    ->where(['member_event_id'=>$memberEventCompleteInfo['member_event_id'],'is_deleted'=>0])
                    ->get();
                $completeItem = $completeItem->toArray();
                if($memberEventCompleteInfo['handle_status'] == 0){
                    $memberEventCompleteFeedbackDefineList = $completeItem;
                }
            }
        }
        $memberEventCompleteInfo['member_event_complete_feedback_define_list'] = $memberEventCompleteFeedbackDefineList;
        $memberEventCompleteInfo['feedback_name'] = $memberEventCompleteFollowupInfo['name'] ?? '';
        $memberEventCompleteInfo['feedback_describe'] = $memberEventCompleteFollowupInfo['describe'] ?? '';
        $memberEventCompleteInfo['feedback_result'] = $memberEventCompleteFollowupInfo['result'] ?? '';
        $memberEventCompleteInfo['feedback_img'] = $memberEventCompleteFollowupInfo['img'] ?? '';
        $memberEventCompleteInfo['complete_item'] = $completeItem;
        $memberEventCompleteInfo['complete_item_str'] = $completeItemStr;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $memberEventCompleteInfo];
    }

    /**
     * 会员事件跟进开关
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function memberEventSwitch(array $params): array
    {
        $memberId = $params['member_id'];
        $isClose = $params['is_close'];

        if($isClose == 0){
            MemberFollowupBlacklist::query()->where(['member_id'=>$memberId])->delete();
        }else{
            $memberBelongToInfo = MemberBelongTo::query()
                ->select(['teacher_id','physical_store_id'])
                ->where(['member_id'=>$memberId])
                ->first();
            $memberBelongToInfo = $memberBelongToInfo?->toArray();
            $insertMemberFollowupBlacklistData['member_id'] = $memberId;
            $insertMemberFollowupBlacklistData['teacher_id'] = $memberBelongToInfo['teacher_id'] ?? 0;
            $insertMemberFollowupBlacklistData['physical_store_id'] = $memberBelongToInfo['physical_store_id'] ?? 0;

            MemberFollowupBlacklist::query()->insert($insertMemberFollowupBlacklistData);
            if(!empty($memberBelongToInfo)){
                MemberBelongTo::query()->where(['member_id'=>$memberId])->update(['teacher_id'=>0,'physical_store_id'=>0]);
            }
        }
        $this->eventDispatcher->dispatch(new MemberEventSwitchRegistered((int)$memberId,(int)$isClose));
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }


    /**
     * 进度详情列表
     * @param array $query
     * @return array
     */
    public function followupList(array $query): array
    {
        $memberId = $query['member_id'] ?? 0;

        $offset = $this->offset;
        $limit = $this->limit;

        $memberFollowupNoteModel = MemberFollowupNote::query()
            ->leftJoin('teacher', 'member_followup_note.teacher_id', '=', 'teacher.id')
            ->leftJoin('physical_store', 'member_followup_note.physical_store_id', '=', 'physical_store.id')
            ->where('member_followup_note.member_id', $memberId);

        $count = $memberFollowupNoteModel->count();

        $fields = [
            'member_followup_note.id', 'member_followup_note.img', 'member_followup_note.describe',
            'member_followup_note.created_at', 'teacher.name AS teacher_name', 'physical_store.name AS store_name'
        ];
        $memberFollowupNoteList = $memberFollowupNoteModel->select($fields)
            ->offset($offset)
            ->limit($limit)
            ->orderByDesc('member_followup_note.id')
            ->get()
            ->toArray();

        foreach ($memberFollowupNoteList as $index => &$item) {
            $item['created_at'] = date('Y.m.d H:i', strtotime($item['created_at']));
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list' => $memberFollowupNoteList, 'count' => $count]];
    }


    /**
     * 处理事项列表
     * @param array $query
     * @return array
     */
    public function memberEventCompleteList(array $query): array
    {
        $memberId = $query['member_id'] ?? 0;

        $offset = $this->offset;
        $limit = $this->limit;

        $memberEventCompleteModel = MemberEventComplete::query()
            ->leftJoin('member_event', 'member_event_complete.member_event_id', '=', 'member_event.id')
            ->leftJoin('teacher', 'member_event_complete.teacher_id', '=', 'teacher.id')
            ->leftJoin('physical_store', 'member_event_complete.physical_store_id', '=', 'physical_store.id')
            ->where('member_event_complete.member_id', $memberId)
            ->where('member_event_complete.member_event_id', '>', 0);

        $count = $memberEventCompleteModel->count();

        $fields = [
            'member_event_complete.id', 'member_event_complete.name', 'member_event_complete.created_at', 'teacher.name AS teacher_name',
            'physical_store.name AS store_name', 'member_event_complete.handle_status', 'member_event_complete.handle_type',
            'member_event_complete.is_need_feedback', 'member_event_complete.qualified_deadline_at', 'member_event_complete.describe'
        ];
        $memberEventCompleteList = $memberEventCompleteModel->select($fields)
            ->offset($offset)
            ->limit($limit)
            ->orderByDesc('member_event_complete.id')
            ->get()
            ->toArray();

        $nowDate = date('Y-m-d H:i:s');
        foreach ($memberEventCompleteList as $index => &$item) {
            $handleImg = '';
            $handleName = '';

            $memberEventCompleteFollowupInfo = MemberEventCompleteFollowup::query()
                ->where(['member_event_complete_id' => $item['id']])
                ->orderByDesc('id')
                ->first(['name', 'img', 'describe', 'created_at', 'result']);
            if (empty($memberEventCompleteFollowupInfo)) {
                $memberEventCompleteFollowupInfo = ['name' => '-', 'img' => '', 'describe' => '-', 'created_at' => $nowDate, 'result' => 0];
            } else {
                $memberEventCompleteFollowupInfo = $memberEventCompleteFollowupInfo->toArray();
            }

            if ($item['handle_status'] == 0) {
                $handleName = '待处理';
            } else {
                if (in_array($item['handle_type'], [0, 2])) {
                    if ($item['is_need_feedback'] == 0) {
                        $handleName = $memberEventCompleteFollowupInfo['describe'];
                        $handleImg = $memberEventCompleteFollowupInfo['img'];
                    } else {
                        $showDate = date('Y.m.d H:i', strtotime($memberEventCompleteFollowupInfo['created_at']));
                        $handleName = $memberEventCompleteFollowupInfo['name'] . "（{$showDate}）";
                    }
                } else if ($item['handle_type'] == 1) {
                    $memberEventCompleteAutoHandleJudgmentCriteriaList = MemberEventCompleteAutoHandleJudgmentCriteria::query()
                        ->where(['member_event_complete_id' => $item['id'], 'is_complete' => 1])
                        ->orderBy('id')
                        ->get(['name', 'created_at'])
                        ->toArray();

                    $handleName = implode(' + ', array_column($memberEventCompleteAutoHandleJudgmentCriteriaList, 'name'));

                    $criteriaCreatedAt = end($memberEventCompleteAutoHandleJudgmentCriteriaList)['created_at'] ?? '';
                    $criteriaCreatedAt && $criteriaCreatedAt = date('Y.m.d H:i', strtotime($criteriaCreatedAt));

                    $handleName .= "（{$criteriaCreatedAt}）";
                }
            }

            if ($item['handle_type'] == 1 && $memberEventCompleteFollowupInfo['result'] == 0 && $nowDate > $item['qualified_deadline_at']) {
                $showDate = date('Y.m.d H:i', strtotime($item['qualified_deadline_at']));
                $handleName = "未完成（{$showDate}）";
            }

            $item['handle_name'] = $handleName;
            $item['handle_img'] = $handleImg;
            $item['created_at'] = date('Y.m.d H:i', strtotime($item['created_at']));
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list' => $memberEventCompleteList, 'count' => $count]];
    }


    /**
     * 待报名列表
     * @param array $query
     * @return array
     */
    public function memberVipCardOrderList(array $query): array
    {
        $memberId = $query['member_id'] ?? 0;

        $offset = $this->offset;
        $limit = $this->limit;

        $vipCardOrderModel = VipCardOrder::query()
            ->where('member_id', $memberId)
            ->where('pay_status', 1);

        $count = $vipCardOrderModel->count();

        $fields = [
            'id', 'order_title', 'expire_at', 'course1', 'course2', 'course3', 'currency_course', 'order_type',
            'course1_used', 'course2_used', 'course3_used', 'currency_course_used', 'order_status', 'card_theme_type'
        ];
        $vipCardOrderList = $vipCardOrderModel->select($fields)
            ->offset($offset)
            ->limit($limit)
            ->orderByDesc('id')
            ->get()
            ->toArray();
        $vipCardOrderIdArray = array_column($vipCardOrderList,'id');
        $vipCardOrderDynamicCourseList = VipCardOrderDynamicCourse::query()
            ->select(['vip_card_order_id','course','course_used'])
            ->whereIn('vip_card_order_id',$vipCardOrderIdArray)
            ->get();
        $vipCardOrderDynamicCourseList = $vipCardOrderDynamicCourseList->toArray();
        $vipCardOrderDynamicCourseList = $this->functions->arrayGroupBy($vipCardOrderDynamicCourseList,'vip_card_order_id');

        $nowDate = date('Y-m-d H:i:s');
        $returnList = [];
        foreach ($vipCardOrderList as &$item) {
            $surplusSectionDynamicCourse = 0;
            $totalSectionDynamicCourse = 0;
            $vipCardOrderDynamicCourse = $vipCardOrderDynamicCourseList[$item['id']] ?? [];
            foreach($vipCardOrderDynamicCourse as $item1){
                $totalSectionDynamicCourse += $item1['course'];
                $surplusSectionDynamicCourse += $item1['course']-$item1['course_used'];
            }
            $totalSectionCourse = $item['course1']+$item['course2']+$item['course3']+$item['currency_course'];
            $surplusSectionCourse = $totalSectionCourse-$item['course1_used']+$item['course2_used']+$item['course3_used']+$item['currency_course_used'];
            $surplusSectionCourse += $surplusSectionDynamicCourse;
            $totalSectionCourse += $totalSectionDynamicCourse;

            

            if ($item['expire_at'] === VipCardConstant::DEFAULT_EXPIRE_AT) {
                $statusText = '未开始使用';
            } else if ($surplusSectionCourse == 0) {
                $statusText = '已用完';
            } else if ($item['expire_at'] > $nowDate) {
                $statusText = date('Y.m.d H:s', strtotime($item['expire_at'])) . '过期';
            } else {
                $statusText = '已过期';
            }

            if ($item['order_status'] != 0) {
                $statusText = '已退卡';
            }

            if (in_array($item['order_type'], [1, 4])) {
                $cardThemeText = [1 => '常规班', 2 => '精品小班', 3 => '代码编程'][$item['card_theme_type']];
            } else {
                $cardThemeText = '通用';
            }

            $returnList[] = [
                'id' => $item['id'],
                'name' => $item['order_title'],
                'card_theme_text' => $cardThemeText,
                'total_count' => $totalSectionCourse,
                'surplus_count' => $surplusSectionCourse,
                'status_text' => $statusText,
            ];
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list' => $returnList, 'count' => $count]];
    }


    /**
     * 已报名/已结束列表
     * @param array $query
     * @return array
     */
    public function memberCourseOfflineOrderList(array $query): array
    {
        $memberId = $query['member_id'] ?? 0;
        $type = $query['type'] ?? 0;

        $offset = $this->offset;
        $limit = $this->limit;

        $courseOfflineOrderModel = CourseOfflineOrder::query()
            ->leftJoin('course_offline_plan', 'course_offline_order.course_offline_plan_id', '=', 'course_offline_plan.id')
            ->where('course_offline_order.member_id', $memberId)
            ->where('course_offline_order.pay_status', 1);

        $nowTimestamp = time();
        if ($type == 1) {
            $courseOfflineOrderModel->where('course_offline_order.order_status', 0)->where('course_offline_plan.class_start_time', '>', $nowTimestamp);
        } else if ($type == 2) {
            $courseOfflineOrderModel->where(function ($query) use ($nowTimestamp) {
                $query->where('course_offline_plan.class_start_time', '<', $nowTimestamp)->orWhere(['order_status' => 2]);
            });
        } else {
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list' => [], 'count' => 0]];
        }

        $count = $courseOfflineOrderModel->count();

        $fields = [
            'course_offline_order.id', 'course_offline_order.course_name', 'course_offline_order.teacher_name',
            'course_offline_order.class_status', 'course_offline_order.order_status', 'course_offline_order.start_at',
            'course_offline_order.theme_type', 'course_offline_order.created_at', 'course_offline_plan.class_start_time'
        ];
        $courseOfflineOrderList = $courseOfflineOrderModel->select($fields)
            ->offset($offset)
            ->limit($limit)
            ->orderByDesc('course_offline_order.id')
            ->get()
            ->toArray();

        foreach ($courseOfflineOrderList as $index => &$item) {
            $statusText = '已报名';
            if ($item['order_status'] == 2) {
                $statusText = '已取消';
            } else if ($item['class_status'] == 1) {
                $statusText = '已上课';
            } else if ($item['class_start_time'] < $nowTimestamp) {
                $statusText = '已旷课';
            }

            $themeText = [1 => '常规班', 2 => '精品小班', 3 => '代码编程'][$item['theme_type']];


            $item['status_text'] = $statusText;
            $item['theme_text'] = $themeText;
            $item['start_at'] = date('Y.m.d H:i', strtotime($item['start_at']));
            $item['created_at'] = date('Y.m.d H:i', strtotime($item['created_at']));
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list' => $courseOfflineOrderList, 'count' => $count]];
    }


}